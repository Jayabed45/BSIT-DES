<?php
session_start();
include '../includes/db_connection.php';

// Only allow admin2
if (!isset($_SESSION['admin2_id'])) {
    header('Location: ../login.php');
    exit;
}

$admin2_id = $_SESSION['admin2_id'];
$error = '';
$success = '';

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

// --- HANDLE STUDENT EDIT (update)
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
            // redirect to clear POST data
            header("Location: admin2_dashboard.php");
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

// --- FETCH EXAMS LIST ---
$exams = [];
$stmt = $conn->prepare("SELECT * FROM exams ORDER BY created_at DESC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    $stmt->close();
}

// --- VIEW RESULTS: if exam_id is selected ---
$selected_exam_id = $_GET['exam_id'] ?? null;
$exam_results = [];
$selected_exam_name = '';

if ($selected_exam_id) {
    // Get exam name
    $stmt = $conn->prepare("SELECT title FROM exams WHERE id = ?");
    $stmt->bind_param("i", $selected_exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $selected_exam_name = $row['title'];
    }
    $stmt->close();

    // Get exam results
    $stmt = $conn->prepare("
        SELECT s.full_name, s.year_level, s.section, s.department, ea.score, ea.completed_at 
        FROM exam_attempts ea
        JOIN students s ON ea.student_id = s.id
        WHERE ea.exam_id = ?
        ORDER BY ea.score DESC
    ");
    $stmt->bind_param("i", $selected_exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exam_results[] = $row;
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin2 Dashboard - Manage Students & View Results</title>
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
                    <a href="admin2_dashboard.php" class="flex items-center space-x-3 text-blue-600 bg-blue-50 p-3 rounded-lg">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
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
                    <h1 class="text-3xl font-bold text-gray-800">Student Management</h1>
                    <p class="text-gray-600 mt-2">Add and manage students in the system</p>
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

                <!-- Add Student Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Student</h2>
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                <input type="text" name="full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                <select name="year_level" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Year Level</option>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                                <select name="section" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                                <select name="department" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Department</option>
                    <?php foreach ($departments_list as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                    <?php endforeach; ?>
                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="add_student" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Add Student
                            </button>
                        </div>
        </form>
    </div>

                <!-- Student List -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">Student List</h2>
                        <div class="flex space-x-4">
                            <select id="filterDepartment" onchange="filterStudents()" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="all">All Departments</option>
                <?php foreach ($departments_list as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                <?php endforeach; ?>
            </select>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['year_level']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['section']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['department']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)" class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="delete_student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" onclick="return confirm('Are you sure you want to delete this student?')" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
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
        function filterStudents() {
            const department = document.getElementById('filterDepartment').value;
            window.location.href = `admin2_dashboard.php?filter_department=${encodeURIComponent(department)}`;
        }

        function editStudent(student) {
            // Populate the form with student data
            const form = document.querySelector('form');
            form.querySelector('[name="full_name"]').value = student.full_name;
            form.querySelector('[name="email"]').value = student.email;
            form.querySelector('[name="year_level"]').value = student.year_level;
            form.querySelector('[name="section"]').value = student.section;
            form.querySelector('[name="department"]').value = student.department;

            // Add hidden input for student ID
            let hiddenInput = form.querySelector('input[name="student_id"]');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'student_id';
                form.appendChild(hiddenInput);
            }
            hiddenInput.value = student.id;

            // Change form action and button text
            form.querySelector('button[type="submit"]').textContent = 'Update Student';
            form.querySelector('button[type="submit"]').name = 'update_student';

            // Scroll to form
            form.scrollIntoView({ behavior: 'smooth' });
    }
</script>
</body>
</html>
