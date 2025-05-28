<?php
session_start();
include '../includes/db_connection.php';

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
    <title>Admin Dashboard - Manage Exams</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-lg">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-blue-600 mb-8">Admin Panel</h2>
                <nav class="space-y-2">
                    <a href="admin_dashboard.php" class="flex items-center space-x-3 text-blue-600 bg-blue-50 p-3 rounded-lg">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <!-- <a href="manage_questions.php" class="flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-question-circle"></i>
                        <span>Manage Questions</span>
                    </a> -->
                    <a href="view_student_scores.php" class="flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Scores</span>
                    </a>
                    <!-- <a href="send_exam.php" class="flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-paper-plane"></i>
                        <span>Send Exam</span>
                    </a> -->
                </nav>
                <div class="mt-auto pt-8">
                    <a href="../logout.php" class="flex items-center space-x-3 text-red-600 hover:bg-red-50 p-3 rounded-lg">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
</div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">Manage Exams</h1>
                    <p class="text-gray-600 mt-2">Create and manage your exams here</p>
    </div>

    <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
    <?php endif; ?>

    <!-- Create Exam Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Create New Exam</h2>
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Exam Title</label>
                                <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                                <input type="number" name="duration_minutes" required min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                            <input type="datetime-local" name="due_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Questions Section -->
                        <div id="questions-container" class="space-y-4">
                            <div class="question-item bg-gray-50 p-4 rounded-lg">
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Question</label>
                                        <input type="text" name="questions[0][text]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Option A</label>
                                            <input type="text" name="questions[0][option_a]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Option B</label>
                                            <input type="text" name="questions[0][option_b]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Option C</label>
                                            <input type="text" name="questions[0][option_c]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Option D</label>
                                            <input type="text" name="questions[0][option_d]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer</label>
                                        <select name="questions[0][correct]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="A">Option A</option>
                                            <option value="B">Option B</option>
                                            <option value="C">Option C</option>
                                            <option value="D">Option D</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
            </div>

                        <div class="flex space-x-4">
                            <button type="button" onclick="addQuestion()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                Add Question
                            </button>
                            <button type="submit" name="create_exam" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Create Exam
                            </button>
                        </div>
        </form>
    </div>

                <!-- Existing Exams -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Existing Exams</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Questions</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
            <?php while ($exam = $exams_result->fetch_assoc()): ?>
            <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($exam['title']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500"><?php echo $exam['question_count']; ?> questions</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500"><?php echo $exam['duration_minutes']; ?> minutes</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500"><?php echo date('M d, Y H:i', strtotime($exam['due_date'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button onclick="openSendExamModal(<?php echo $exam['id']; ?>, '<?php echo htmlspecialchars($exam['title']); ?>')" 
                                                class="text-green-600 hover:text-green-900 mr-3">
                                                <i class="fas fa-paper-plane"></i> Send
                                            </button>
                                            <a href="?delete_exam=<?php echo $exam['id']; ?>" onclick="return confirm('Are you sure you want to delete this exam?')" 
                                                class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </a>
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
    <div id="sendExamModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Send Exam to Students</h3>
                <form id="sendExamForm" method="POST" class="space-y-4">
                    <input type="hidden" id="examId" name="exam_id">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select name="department" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                        <select name="year" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Year Level</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                        <select name="section" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section; ?>"><?php echo $section; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeSendExamModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Cancel
                        </button>
                        <button type="submit" name="send_exam" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Send Exam
                        </button>
                    </div>
                    </form>
            </div>
        </div>
</div>

<script>
        let questionCount = 1;

function addQuestion() {
    const container = document.getElementById('questions-container');
            const newQuestion = document.createElement('div');
            newQuestion.className = 'question-item bg-gray-50 p-4 rounded-lg mt-4';
            newQuestion.innerHTML = `
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Question</label>
                        <input type="text" name="questions[${questionCount}][text]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
                    <div class="grid grid-cols-2 gap-4">
            <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Option A</label>
                            <input type="text" name="questions[${questionCount}][option_a]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Option B</label>
                            <input type="text" name="questions[${questionCount}][option_b]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Option C</label>
                            <input type="text" name="questions[${questionCount}][option_c]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Option D</label>
                            <input type="text" name="questions[${questionCount}][option_d]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer</label>
                        <select name="questions[${questionCount}][correct]" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="A">Option A</option>
                            <option value="B">Option B</option>
                            <option value="C">Option C</option>
                            <option value="D">Option D</option>
                        </select>
                    </div>
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-red-600 hover:text-red-900 text-sm font-medium">
                        Remove Question
                    </button>
        </div>
    `;
            container.appendChild(newQuestion);
    questionCount++;
}

        function openSendExamModal(examId, examTitle) {
            document.getElementById('examId').value = examId;
            document.getElementById('sendExamModal').classList.remove('hidden');
        }

        function closeSendExamModal() {
            document.getElementById('sendExamModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('sendExamModal');
            if (event.target == modal) {
                closeSendExamModal();
            }
        }
</script>
</body>
</html>
