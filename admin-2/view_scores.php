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
    <title>View Exam Scores - Admin2 Dashboard</title>
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
                    <a href="admin2_dashboard.php" class="flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="view_scores.php" class="flex items-center space-x-3 text-blue-600 bg-blue-50 p-3 rounded-lg">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Scores</span>
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
                    <h1 class="text-3xl font-bold text-gray-800">View Exam Scores</h1>
                    <p class="text-gray-600 mt-2">View and filter exam results</p>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <form method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Select Exam</label>
                                <select name="exam_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select an Exam</option>
                                    <?php foreach ($exams as $exam): ?>
                                        <option value="<?php echo $exam['id']; ?>" <?php echo ($selected_exam_id == $exam['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($exam['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                                <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                                <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Years</option>
                                    <option value="1st Year" <?php echo ($filter_year == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo ($filter_year == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo ($filter_year == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo ($filter_year == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                                <select name="section" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Results Table -->
                <?php if ($selected_exam_id): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">
                            Results for: <?php echo htmlspecialchars($selected_exam_name); ?>
                        </h2>
                        <?php if (!empty($exam_results)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed At</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($exam_results as $result): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($result['full_name']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($result['year_level']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($result['section']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($result['department']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($result['score']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500"><?php echo date('M d, Y H:i', strtotime($result['completed_at'])); ?></div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-gray-500 py-4">
                                No results found for the selected filters.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 