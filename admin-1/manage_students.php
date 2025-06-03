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

// Fixed department options
$departments_list = [
    'College of Engineering',
    'College of Technology',
    'Bachelor of Science in Information Technology',
    'College Of Education',
    'College of Agriculture',
];

// --- HANDLE STUDENT ADD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $year_level = $_POST['year_level'];
    $section = $_POST['section'];
    $department = $_POST['department'];

    if (!$full_name || !$email || !$year_level || !$section || !$department) {
        $error = "Please fill all student fields.";
    } elseif (!in_array($department, $departments_list)) {
        $error = "Invalid department selected.";
    } else {
        $stmt = $conn->prepare("INSERT INTO students (full_name, email, year_level, section, department) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $full_name, $email, $year_level, $section, $department);
        if ($stmt->execute()) {
            $success = "Student added successfully.";
        } else {
            $error = "Failed to add student: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- HANDLE STUDENT DELETE ---
if (isset($_POST['delete_student_id'])) {
    $delete_id = intval($_POST['delete_student_id']);
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success = "Student deleted successfully.";
    } else {
        $error = "Failed to delete student.";
    }
    $stmt->close();
}

// --- HANDLE STUDENT EDIT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $id = intval($_POST['student_id']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $year_level = $_POST['year_level'];
    $section = $_POST['section'];
    $department = $_POST['department'];

    if (!$full_name || !$email || !$year_level || !$section || !$department) {
        $error = "Please fill all student fields.";
    } elseif (!in_array($department, $departments_list)) {
        $error = "Invalid department selected.";
    } else {
        $stmt = $conn->prepare("UPDATE students SET full_name = ?, email = ?, year_level = ?, section = ?, department = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $full_name, $email, $year_level, $section, $department, $id);
        if ($stmt->execute()) {
            $success = "Student updated successfully.";
            header("Location: manage_students.php");
            exit;
        } else {
            $error = "Failed to update student: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- HANDLE DEPARTMENT FILTER ---
$filter_department = $_GET['filter_department'] ?? 'all';

// --- FETCH STUDENTS LIST with filtering ---
$students = [];

if ($filter_department && $filter_department !== 'all') {
    $stmt = $conn->prepare("SELECT * FROM students WHERE department = ? ORDER BY full_name ASC");
    $stmt->bind_param("s", $filter_department);
} else {
    $stmt = $conn->prepare("SELECT * FROM students ORDER BY full_name ASC");
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - BSIT Exam System</title>
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
                    <a href="admin_dashboard.php" class="sidebar-item flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="manage_students.php" class="sidebar-item flex items-center space-x-3 text-blue-600 bg-blue-50 p-3 rounded-lg">
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
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800">Manage Students</h1>
                    <p class="text-gray-600 mt-2">Add, edit, or remove students from the system</p>
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

                <!-- Add Student Form -->
                <div class="bg-white rounded-lg shadow-md p-4 lg:p-6 mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg lg:text-xl font-semibold text-gray-800">Add New Student</h2>
                        <button onclick="toggleStudentForm()" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <form method="POST" class="space-y-4" id="studentForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                <input type="text" name="full_name" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                <select name="year_level" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select Year Level</option>
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                                <select name="section" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select Section</option>
                                    <option value="A">Section A</option>
                                    <option value="B">Section B</option>
                                    <option value="C">Section C</option>
                                    <option value="D">Section D</option>
                                    <option value="E">Section E</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                                <select name="department" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments_list as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="add_student" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>Add Student
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Students List -->
                <div class="bg-white rounded-lg shadow-md p-4 lg:p-6">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-4">
                        <h2 class="text-lg lg:text-xl font-semibold text-gray-800 mb-4 lg:mb-0">Students List</h2>
                        <div class="w-full lg:w-auto">
                            <form method="GET" class="flex flex-col lg:flex-row gap-4">
                                <select name="filter_department" class="w-full lg:w-64 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="all">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($filter_department === $dept) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="w-full lg:w-auto px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                                    Filter
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['email']); ?></div>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['year_level']); ?></div>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['section']); ?></div>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['department']); ?></div>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($student)); ?>)" 
                                                    class="text-blue-600 hover:text-blue-900">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this student?');">
                                                    <input type="hidden" name="delete_student_id" value="<?php echo $student['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleStudentForm() {
            const form = document.getElementById('studentForm');
            form.classList.toggle('hidden');
        }

        function openEditModal(student) {
            const form = document.querySelector('form');
            form.querySelector('[name="full_name"]').value = student.full_name;
            form.querySelector('[name="email"]').value = student.email;
            form.querySelector('[name="year_level"]').value = student.year_level;
            form.querySelector('[name="section"]').value = student.section;
            form.querySelector('[name="department"]').value = student.department;

            let hiddenInput = form.querySelector('input[name="student_id"]');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'student_id';
                form.appendChild(hiddenInput);
            }
            hiddenInput.value = student.id;

            form.querySelector('button[type="submit"]').textContent = 'Update Student';
            form.querySelector('button[type="submit"]').name = 'update_student';

            form.scrollIntoView({ behavior: 'smooth' });
        }

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
    </script>
</body>
</html> 