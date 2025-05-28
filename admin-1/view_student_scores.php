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

$sections = ['A', 'B', 'C', 'D', 'E'];
$years = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
$departments = [
    'College of Engineering',
    'College of Technology',
    'Bachelor of Science in Information Technology',
    'College Of Education',
    'College of Agriculture'
];

// Get filters from GET or default to empty
$filter_year = $_GET['year'] ?? '';
$filter_section = $_GET['section'] ?? '';
$filter_department = $_GET['department'] ?? '';

// We'll build the WHERE clause dynamically based on filters
$whereClauses = [];
$params = [];
$paramTypes = '';

if ($filter_year && in_array($filter_year, $years)) {
    $whereClauses[] = "year_level = ?";
    $params[] = $filter_year;
    $paramTypes .= 's';
}
if ($filter_section && in_array($filter_section, $sections)) {
    $whereClauses[] = "section = ?";
    $params[] = $filter_section;
    $paramTypes .= 's';
}
if ($filter_department && in_array($filter_department, $departments)) {
    $whereClauses[] = "department = ?";
    $params[] = $filter_department;
    $paramTypes .= 's';
}

$whereSQL = "";
if (count($whereClauses) > 0) {
    $whereSQL = "WHERE " . implode(' AND ', $whereClauses);
}

// The query will fetch students and their highest quiz scores (assuming tables exist)
// Assuming you have a table 'student_scores' with columns: student_id, exam_id, score
// and 'students' with id, full_name, year_level, section, department

// We get highest score per student by MAX(score) grouped by student_id
$sql = "
SELECT 
    s.id, s.full_name, s.year_level, s.section, s.department, 
    MAX(ss.score) as highest_score
FROM students s
LEFT JOIN student_scores ss ON s.id = ss.student_id
$whereSQL
GROUP BY s.id
ORDER BY highest_score DESC, s.full_name ASC
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

if (count($params) > 0) {
    $stmt->bind_param($paramTypes, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Student Scores</title>
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
                    <a href="admin_dashboard.php" class="flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <!-- <a href="manage_questions.php" class="flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-question-circle"></i>
                        <span>Manage Questions</span>
                    </a> -->
                    <a href="view_student_scores.php" class="flex items-center space-x-3 text-blue-600 bg-blue-50 p-3 rounded-lg">
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
                    <h1 class="text-3xl font-bold text-gray-800">View Student Scores</h1>
                    <p class="text-gray-600 mt-2">Filter and view student exam scores</p>
                </div>

                <!-- Filter Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <form method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                                <select name="department" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php if ($filter_department === $dept) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                <select name="year" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Year Level</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php if ($filter_year === $year) echo 'selected'; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                                <select name="section" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Section</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo $section; ?>" <?php if ($filter_section === $section) echo 'selected'; ?>>
                                            <?php echo $section; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fas fa-filter mr-2"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Results Table -->
                <?php if ($result && $result->num_rows > 0): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Highest Score</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while($row = $result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($row['full_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($row['year_level']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($row['section']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($row['department']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo is_null($row['highest_score']) ? 'N/A' : htmlspecialchars($row['highest_score']); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-md p-6 text-center">
                        <p class="text-gray-500">No students found for the selected filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
