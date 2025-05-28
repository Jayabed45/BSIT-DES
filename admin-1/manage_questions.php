<?php
session_start();
include '../includes/db_connection.php';

// Check admin login
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$admin_id = $_SESSION['admin_id'];

if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    die("Invalid Exam ID.");
}

$exam_id = intval($_GET['exam_id']);

// Verify this exam belongs to this admin
$stmt = $conn->prepare("SELECT title FROM exams WHERE id = ? AND created_by = ?");
$stmt->bind_param("ii", $exam_id, $admin_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Exam not found or you don't have permission to manage it.");
}
$exam = $result->fetch_assoc();
$stmt->close();

$error = '';
$success = '';

// Handle add new question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $question_text = trim($_POST['question_text']);
    $option_a = trim($_POST['option_a']);
    $option_b = trim($_POST['option_b']);
    $option_c = trim($_POST['option_c']);
    $option_d = trim($_POST['option_d']);
    $correct_option = $_POST['correct_option'] ?? '';

    if (!$question_text || !$option_a || !$option_b || !$option_c || !$option_d || !in_array($correct_option, ['A','B','C','D'])) {
        $error = "Please fill in all fields correctly.";
    } else {
        $stmt = $conn->prepare("INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $exam_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option);
        if ($stmt->execute()) {
            $success = "Question added successfully.";
        } else {
            $error = "Failed to add question: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle delete question
if (isset($_GET['delete_question']) && is_numeric($_GET['delete_question'])) {
    $question_id = intval($_GET['delete_question']);

    // Verify question belongs to this exam
    $stmt = $conn->prepare("SELECT id FROM questions WHERE id = ? AND exam_id = ?");
    $stmt->bind_param("ii", $question_id, $exam_id);
    $stmt->execute();
    $q_result = $stmt->get_result();
    if ($q_result->num_rows > 0) {
        $stmt_del = $conn->prepare("DELETE FROM questions WHERE id = ?");
        $stmt_del->bind_param("i", $question_id);
        $stmt_del->execute();
        $stmt_del->close();
        $success = "Question deleted.";
    } else {
        $error = "Question not found or does not belong to this exam.";
    }
    $stmt->close();
}

// Handle edit question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_question'])) {
    $question_id = intval($_POST['question_id']);
    $question_text = trim($_POST['question_text']);
    $option_a = trim($_POST['option_a']);
    $option_b = trim($_POST['option_b']);
    $option_c = trim($_POST['option_c']);
    $option_d = trim($_POST['option_d']);
    $correct_option = $_POST['correct_option'] ?? '';

    if (!$question_text || !$option_a || !$option_b || !$option_c || !$option_d || !in_array($correct_option, ['A','B','C','D'])) {
        $error = "Please fill in all fields correctly.";
    } else {
        // Verify question belongs to this exam
        $stmt = $conn->prepare("SELECT id FROM questions WHERE id = ? AND exam_id = ?");
        $stmt->bind_param("ii", $question_id, $exam_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        if ($check_result->num_rows > 0) {
            $stmt_update = $conn->prepare("UPDATE questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ? WHERE id = ?");
            $stmt_update->bind_param("ssssssi", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $question_id);
            if ($stmt_update->execute()) {
                $success = "Question updated successfully.";
            } else {
                $error = "Failed to update question: " . $conn->error;
            }
            $stmt_update->close();
        } else {
            $error = "Question not found or does not belong to this exam.";
        }
        $stmt->close();
    }
}

// Fetch all questions for this exam
$stmt = $conn->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Questions for "<?php echo htmlspecialchars($exam['title']); ?>"</title>
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
                    <a href="manage_questions.php" class="flex items-center space-x-3 text-blue-600 bg-blue-50 p-3 rounded-lg">
                        <i class="fas fa-question-circle"></i>
                        <span>Manage Questions</span>
                    </a>
                    <a href="view_student_scores.php" class="flex items-center space-x-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 p-3 rounded-lg">
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
                    <h1 class="text-3xl font-bold text-gray-800">Manage Questions</h1>
                    <p class="text-gray-600 mt-2">Add and manage questions for "<?php echo htmlspecialchars($exam['title']); ?>"</p>
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

                <!-- Add New Question Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Question</h2>
                    <form method="POST" class="space-y-4" action="manage_questions.php?exam_id=<?php echo $exam_id; ?>">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Question Text</label>
                            <textarea name="question_text" placeholder="Enter your question here" required rows="3" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Option A</label>
                                <input type="text" name="option_a" placeholder="Option A" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Option B</label>
                                <input type="text" name="option_b" placeholder="Option B" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Option C</label>
                                <input type="text" name="option_c" placeholder="Option C" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Option D</label>
                                <input type="text" name="option_d" placeholder="Option D" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Correct Option</label>
                            <select name="correct_option" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">--Select--</option>
                                <option value="A">Option A</option>
                                <option value="B">Option B</option>
                                <option value="C">Option C</option>
                                <option value="D">Option D</option>
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="add_question" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Add Question
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Existing Questions -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Existing Questions</h2>
                    <?php if ($questions_result->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question Text</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Option A</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Option B</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Option C</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Option D</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Correct Option</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($q = $questions_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $q['id']; ?></td>
                                            <td class="px-6 py-4">
                                                <form method="POST" class="inline-form" action="manage_questions.php?exam_id=<?php echo $exam_id; ?>">
                                                    <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                                    <textarea name="question_text" rows="3" required 
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($q['question_text']); ?></textarea>
                                            </td>
                                            <td class="px-6 py-4">
                                                <input type="text" name="option_a" value="<?php echo htmlspecialchars($q['option_a']); ?>" required 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </td>
                                            <td class="px-6 py-4">
                                                <input type="text" name="option_b" value="<?php echo htmlspecialchars($q['option_b']); ?>" required 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </td>
                                            <td class="px-6 py-4">
                                                <input type="text" name="option_c" value="<?php echo htmlspecialchars($q['option_c']); ?>" required 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </td>
                                            <td class="px-6 py-4">
                                                <input type="text" name="option_d" value="<?php echo htmlspecialchars($q['option_d']); ?>" required 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </td>
                                            <td class="px-6 py-4">
                                                <select name="correct_option" required 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                    <option value="A" <?php if($q['correct_option'] == 'A') echo 'selected'; ?>>A</option>
                                                    <option value="B" <?php if($q['correct_option'] == 'B') echo 'selected'; ?>>B</option>
                                                    <option value="C" <?php if($q['correct_option'] == 'C') echo 'selected'; ?>>C</option>
                                                    <option value="D" <?php if($q['correct_option'] == 'D') echo 'selected'; ?>>D</option>
                                                </select>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button type="submit" name="edit_question" class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-save"></i> Save
                                                </button>
                                                </form>
                                                <form method="GET" class="inline" onsubmit="return confirm('Are you sure to delete this question?');">
                                                    <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                                                    <input type="hidden" name="delete_question" value="<?php echo $q['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        <i class="fas fa-trash-alt"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">No questions found for this exam.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
