<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../includes/db_connection.php';

// Debugging: Check if $conn is a valid database connection object
if ($conn) {
    error_log("Database connection successful in admin_dashboard.php");
    // Debugging: Check the connected database name
    $current_db = $conn->query("SELECT DATABASE()")->fetch_row()[0];
    error_log("Connected database: " . $current_db);
} else {
    error_log("Database connection failed in admin_dashboard.php");
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$admin_id = $_SESSION['admin_id'];
$error = '';
$success = '';

// Define arrays for filters
$sections = ['A', 'B', 'C', 'D', 'E'];
$years = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
$departments = [
    'College of Engineering',
    'College of Technology',
    'Bachelor of Science in Information Technology',
    'College Of Education',
    'College of Agriculture'
];

// Handle exam deletion
if (isset($_GET['delete_exam'])) {
    $exam_id = intval($_GET['delete_exam']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete related records first
        $tables = [
            'questions',
            'exam_assignments',
            'exam_sends',
            'notifications',
            'exam_attempts',
            'student_scores'
        ];
        
        foreach ($tables as $table) {
            $stmt = $conn->prepare("DELETE FROM $table WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Finally delete the exam
        $stmt = $conn->prepare("DELETE FROM exams WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $exam_id, $admin_id);
        $stmt->execute();
        $stmt->close();
        
        // If everything went well, commit the transaction
        $conn->commit();
        $success = "Exam deleted successfully.";
    } catch (Exception $e) {
        // If there was an error, rollback the transaction
        $conn->rollback();
        $error = "Failed to delete exam: " . $e->getMessage();
    }
}

// Handle exam creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_exam'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $duration = intval($_POST['duration_minutes']);
    $due_date = $_POST['due_date'];
    $questions = $_POST['questions'] ?? [];

    if (empty($title) || $duration <= 0 || empty($due_date)) {
        $error = "Please enter a valid title, duration, and due date.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert exam
            $stmt = $conn->prepare("INSERT INTO exams (title, description, duration_minutes, due_date, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssisi", $title, $description, $duration, $due_date, $admin_id);
            $stmt->execute();
            $exam_id = $conn->insert_id;
            
            // Insert questions
            if (!empty($questions)) {
                $stmt = $conn->prepare("INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($questions as $q) {
                    if (!empty($q['text']) && !empty($q['option_a']) && !empty($q['option_b']) && !empty($q['option_c']) && !empty($q['option_d'])) {
                        $stmt->bind_param("issssss", 
                            $exam_id,
                            $q['text'],
                            $q['option_a'],
                            $q['option_b'],
                            $q['option_c'],
                            $q['option_d'],
                            $q['correct']
                        );
                        $stmt->execute();
                    }
                }
            }
            
            $conn->commit();
            $success = "Exam '$title' created successfully with " . count($questions) . " questions.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error creating exam: " . $e->getMessage();
        }
        $stmt->close();
    }
}

// Handle sending exam to students
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_exam'])) {
    $exam_id = intval($_POST['exam_id']);
    $department = trim($_POST['department']);
    $year = trim($_POST['year']);
    $section = trim($_POST['section']);

    // Verify exam belongs to this admin and get exam title
    $stmtExam = $conn->prepare("SELECT id, title FROM exams WHERE id = ? AND created_by = ?");
    $stmtExam->bind_param("ii", $exam_id, $admin_id);
    $stmtExam->execute();
    $resultExam = $stmtExam->get_result();

    if ($resultExam->num_rows === 0) {
        $error = "Invalid exam or you don't have permission to send it.";
    } else {
        $exam = $resultExam->fetch_assoc();
        $exam_title = $exam['title'];

        $stmtStudents = $conn->prepare("SELECT id FROM students WHERE department = ? AND year_level = ? AND section = ?");
        if (!$stmtStudents) {
            $error = "Failed to prepare statement: " . $conn->error;
        } else {
            $stmtStudents->bind_param("sss", $department, $year, $section);
            $stmtStudents->execute();
            $students_result = $stmtStudents->get_result();

            if ($students_result->num_rows === 0) {
                $error = "No students found matching the selected criteria.";
            } else {
                $conn->begin_transaction();

                try {
                    $assign_stmt = $conn->prepare("INSERT IGNORE INTO exam_assignments (exam_id, student_id) VALUES (?, ?)");
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (student_id, exam_id, message) VALUES (?, ?, ?)");
                    $log_stmt = $conn->prepare("INSERT INTO exam_sends (exam_id, year, section, department) VALUES (?, ?, ?, ?)");

                    $assignedCount = 0;
                    while ($student = $students_result->fetch_assoc()) {
                        $student_id = $student['id'];

                        $assign_stmt->bind_param("ii", $exam_id, $student_id);
                        $executed = $assign_stmt->execute();

                        if ($executed && $assign_stmt->affected_rows > 0) {
                            $assignedCount++;

                            $message = "New exam assigned: " . $exam_title;
                            $notif_stmt->bind_param("iis", $student_id, $exam_id, $message);
                            $notif_stmt->execute();
                        }
                    }

                    $log_stmt->bind_param("isss", $exam_id, $year, $section, $department);
                    $log_stmt->execute();

                    $conn->commit();
                    $success = "Exam sent successfully to $assignedCount students.";
                    
                    $assign_stmt->close();
                    $notif_stmt->close();
                    $log_stmt->close();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error sending exam: " . $e->getMessage();
                }
            }
            $stmtStudents->close();
        }
    }
    $stmtExam->close();
}

// Get total students count for stats
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM students");
$stmt->execute();
$result = $stmt->get_result();
$total_students = $result->fetch_assoc()['total'];
$stmt->close();

// Fetch all exams created by this admin
$stmt = $conn->prepare("
    SELECT e.*, COUNT(q.id) as question_count 
    FROM exams e 
    LEFT JOIN questions q ON e.id = q.exam_id 
    WHERE e.created_by = ? 
    GROUP BY e.id 
    ORDER BY e.created_at DESC
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$exams_result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BSIT Exam System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .sidebar-item {
            transition: all 0.3s ease;
        }
        .sidebar-item:hover {
            transform: translateX(5px);
        }
        @media (max-width: 768px) {
            .mobile-menu {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }
            .mobile-menu.active {
                transform: translateX(0);
            }
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }
            .overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Menu Button -->
    <div class="lg:hidden fixed top-4 left-4 z-50">
        <button id="mobileMenuButton" class="p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
            <i class="fas fa-bars text-xl"></i>
        </button>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="overlay" class="overlay"></div>

    <div class="flex h-screen">
        <!-- Sidebar -->
        <div id="mobileMenu" class="mobile-menu fixed lg:static w-64 bg-white shadow-lg flex flex-col h-full z-50">
            <div class="p-6 flex-1">
                <div class="flex items-center space-x-3 mb-8">
                    <i class="fas fa-graduation-cap text-2xl text-blue-600"></i>
                    <h2 class="text-2xl font-bold text-blue-600">Admin Panel</h2>
                </div>
                <nav class="space-y-2">
                    <a href="admin_dashboard.php" class="sidebar-item flex items-center space-x-3 text-blue-600 bg-blue-50 p-3 rounded-lg">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="manage_students.php" class="sidebar-item flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-users"></i>
                        <span>Manage Students</span>
                    </a>
                    <a href="view_scores.php" class="sidebar-item flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Scores</span>
                    </a>
                    <a href="create_exam.php" class="sidebar-item flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create Exam</span>
                    </a>
                </nav>
            </div>
            <div class="p-6 border-t border-gray-200">
                <a href="../logout.php" class="sidebar-item flex items-center space-x-3 text-red-600 hover:bg-red-50 p-3 rounded-lg">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="p-4 lg:p-8">
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800">Welcome Back, Admin!</h1>
                    <p class="text-gray-600 mt-2">Manage your exams and students efficiently</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-4" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-4" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <p><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow-md p-4 lg:p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Students</p>
                                <p class="text-xl lg:text-2xl font-bold text-gray-900"><?php echo $total_students; ?></p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-4 lg:p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Active Exams</p>
                                <p class="text-xl lg:text-2xl font-bold text-gray-900"><?php echo $exams_result->num_rows; ?></p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-file-alt text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-4 lg:p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Completed Exams</p>
                                <p class="text-xl lg:text-2xl font-bold text-gray-900">0</p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department Statistics Chart -->
                <div class="bg-white rounded-lg shadow-md p-4 lg:p-6 mb-8">
                    <h2 class="text-lg lg:text-xl font-semibold text-gray-800 mb-4">Department Statistics</h2>
                    <div class="h-60 lg:h-80">
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>

                <!-- Existing Exams -->
                <div class="bg-white rounded-lg shadow-md p-4 lg:p-6">
                    <h2 class="text-lg lg:text-xl font-semibold text-gray-800 mb-4">Existing Exams</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Questions</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while ($exam = $exams_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($exam['title']); ?></div>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?php echo $exam['question_count']; ?> questions
                                            </span>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500"><?php echo $exam['duration_minutes']; ?> minutes</div>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500"><?php echo date('M d, Y H:i', strtotime($exam['due_date'])); ?></div>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" 
                                                    class="text-blue-600 hover:text-blue-900">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="openSendExamModal(<?php echo $exam['id']; ?>, '<?php echo htmlspecialchars($exam['title']); ?>')" 
                                                    class="text-green-600 hover:text-green-900">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                                <a href="?delete_exam=<?php echo $exam['id']; ?>" 
                                                    onclick="return confirm('Are you sure you want to delete this exam?')" 
                                                    class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Exam Modal -->
    <div id="sendExamModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Send Exam to Students</h3>
                    <button onclick="closeSendExamModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="sendExamForm" method="POST" class="space-y-4">
                    <input type="hidden" id="examId" name="exam_id">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select name="department" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                        <select name="year" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Year Level</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                        <select name="section" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section; ?>"><?php echo $section; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeSendExamModal()" 
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit" name="send_exam" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                            Send Exam
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Mobile menu functionality
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('overlay');

        function toggleMobileMenu() {
            mobileMenu.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        mobileMenuButton.addEventListener('click', toggleMobileMenu);
        overlay.addEventListener('click', toggleMobileMenu);

        // Fetch department statistics
        fetch('get_department_stats.php')
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById('departmentChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.departments,
                        datasets: [{
                            label: 'Total Students per Department',
                            data: data.counts,
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: 'Student Distribution Across Departments'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => console.error('Error loading department statistics:', error));

        function openSendExamModal(examId, examTitle) {
            document.getElementById('examId').value = examId;
            document.getElementById('sendExamModal').classList.remove('hidden');
        }

        function closeSendExamModal() {
            document.getElementById('sendExamModal').classList.add('hidden');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('sendExamModal');
            if (event.target == modal) {
                closeSendExamModal();
            }
        }
    </script>
</body>
</html>
