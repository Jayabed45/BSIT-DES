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

// Fixed department options
$departments_list = [
    'College of Engineering',
    'College of Technology',
    'Bachelor of Science in Information Technology',
    'College Of Education',
    'College of Agriculture',
];

// Get filters from GET
$selected_exam_id = $_GET['exam_id'] ?? '';
$filter_department = $_GET['department'] ?? '';
$filter_year = $_GET['year'] ?? '';
$filter_section = $_GET['section'] ?? '';

// Fetch all exams for dropdown
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

// Fetch exam results with filters
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

    // Build the query with filters
    $query = "
        SELECT s.full_name, s.year_level, s.section, s.department, ea.score, ea.completed_at 
        FROM exam_attempts ea
        JOIN students s ON ea.student_id = s.id
        WHERE ea.exam_id = ?
    ";
    $params = [$selected_exam_id];
    $types = "i";

    if ($filter_department) {
        $query .= " AND s.department = ?";
        $params[] = $filter_department;
        $types .= "s";
    }
    if ($filter_year) {
        $query .= " AND s.year_level = ?";
        $params[] = $filter_year;
        $types .= "s";
    }
    if ($filter_section) {
        $query .= " AND s.section = ?";
        $params[] = $filter_section;
        $types .= "s";
    }

    $query .= " ORDER BY ea.score DESC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $exam_results[] = $row;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Exam Scores - BSIT Exam System</title>
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
                    <a href="manage_students.php" class="sidebar-item flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-users"></i>
                        <span>Manage Students</span>
                    </a>
                    <a href="view_scores.php" class="sidebar-item flex items-center space-x-3 text-blue-600 bg-blue-50 p-3 rounded-lg">
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
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800">Exam Scores Analysis</h1>
                    <p class="text-gray-600 mt-2">View and analyze student performance</p>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow-md p-4 lg:p-6 mb-8">
                    <form method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Select Exam</label>
                                <select name="exam_id" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select an exam</option>
                                    <?php foreach ($exams as $exam): ?>
                                        <option value="<?php echo $exam['id']; ?>" <?php echo ($selected_exam_id == $exam['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($exam['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                                <select name="department" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments_list as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($filter_department == $dept) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                <select name="year" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">All Years</option>
                                    <option value="1st Year" <?php echo ($filter_year == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo ($filter_year == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo ($filter_year == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo ($filter_year == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                                <select name="section" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">All Sections</option>
                                    <option value="A" <?php echo ($filter_section == 'A') ? 'selected' : ''; ?>>Section A</option>
                                    <option value="B" <?php echo ($filter_section == 'B') ? 'selected' : ''; ?>>Section B</option>
                                    <option value="C" <?php echo ($filter_section == 'C') ? 'selected' : ''; ?>>Section C</option>
                                    <option value="D" <?php echo ($filter_section == 'D') ? 'selected' : ''; ?>>Section D</option>
                                    <option value="E" <?php echo ($filter_section == 'E') ? 'selected' : ''; ?>>Section E</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <?php if ($selected_exam_id): ?>
                    <!-- Results Table -->
                    <div class="bg-white rounded-lg shadow-md p-4 lg:p-6">
                        <div class="mb-4">
                            <h2 class="text-lg lg:text-xl font-semibold text-gray-800">Results for: <?php echo htmlspecialchars($selected_exam_name); ?></h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                        <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed At</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($exam_results as $result): ?>
                                        <tr>
                                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($result['full_name']); ?></div>
                                            </td>
                                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($result['year_level']); ?></div>
                                            </td>
                                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($result['section']); ?></div>
                                            </td>
                                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($result['department']); ?></div>
                                            </td>
                                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $result['score'] >= 70 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo $result['score']; ?>%
                                                </span>
                                            </td>
                                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500">
                                                    <?php echo date('M d, Y H:i', strtotime($result['completed_at'])); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

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
    </script>
</body>
</html> 