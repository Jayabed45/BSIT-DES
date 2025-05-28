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

// Get exam ID from URL
$exam_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($exam_id <= 0) {
    header('Location: admin_dashboard.php');
    exit;
}

// Fetch exam details
$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ? AND created_by = ?");
$stmt->bind_param("ii", $exam_id, $admin_id);
$stmt->execute();
$exam_result = $stmt->get_result();
$exam = $exam_result->fetch_assoc();

if (!$exam) {
    header('Location: admin_dashboard.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_exam'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $duration = intval($_POST['duration_minutes']);
        $due_date = $_POST['due_date'];

        if (empty($title) || $duration <= 0 || empty($due_date)) {
            $error = "Please enter a valid title, duration, and due date.";
        } else {
            $stmt = $conn->prepare("UPDATE exams SET title = ?, description = ?, duration_minutes = ?, due_date = ? WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ssisii", $title, $description, $duration, $due_date, $exam_id, $admin_id);
            
            if ($stmt->execute()) {
                $success = "Exam updated successfully.";
                // Refresh exam data
                $stmt = $conn->prepare("SELECT * FROM exams WHERE id = ? AND created_by = ?");
                $stmt->bind_param("ii", $exam_id, $admin_id);
                $stmt->execute();
                $exam_result = $stmt->get_result();
                $exam = $exam_result->fetch_assoc();
            } else {
                $error = "Error updating exam: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['add_question'])) {
        $question_text = trim($_POST['question_text']);
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_option = $_POST['correct_option'];

        if (empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
            $error = "Please fill in all question fields.";
        } else {
            $stmt = $conn->prepare("INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $exam_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option);
            
            if ($stmt->execute()) {
                $success = "Question added successfully.";
            } else {
                $error = "Error adding question: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_question'])) {
        $question_id = intval($_POST['question_id']);
        $stmt = $conn->prepare("DELETE FROM questions WHERE id = ? AND exam_id = ?");
        $stmt->bind_param("ii", $question_id, $exam_id);
        
        if ($stmt->execute()) {
            $success = "Question deleted successfully.";
        } else {
            $error = "Error deleting question: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch questions for this exam
$stmt = $conn->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Exam - <?php echo htmlspecialchars($exam['title']); ?></title>
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
                    <!-- <a href="manage_questions.php" class="flex items-center space-x-3 text-blue-600 bg-blue-50 p-3 rounded-lg">
                        <i class="fas fa-question-circle"></i>
                        <span>Manage Questions</span>
                    </a> -->
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
                    <h1 class="text-3xl font-bold text-gray-800">Edit Exam</h1>
                    <p class="text-gray-600 mt-2">Modify exam details and questions</p>
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

                <!-- Exam Details Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Exam Details</h2>
                    <form method="POST" action="edit_exam.php?id=<?php echo $exam_id; ?>" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($exam['title']); ?>" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                                <input type="number" id="duration_minutes" name="duration_minutes" value="<?php echo intval($exam['duration_minutes']); ?>" min="1" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea id="description" name="description" rows="3" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($exam['description']); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                            <input type="datetime-local" id="due_date" name="due_date" value="<?php echo htmlspecialchars($exam['due_date']); ?>" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="update_exam" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Update Exam
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Add New Question Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Question</h2>
                    <form method="POST" action="edit_exam.php?id=<?php echo $exam_id; ?>" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Question Text</label>
                            <textarea id="question_text" name="question_text" required rows="3" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Option A</label>
                                <input type="text" id="option_a" name="option_a" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Option B</label>
                                <input type="text" id="option_b" name="option_b" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Option C</label>
                                <input type="text" id="option_c" name="option_c" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Option D</label>
                                <input type="text" id="option_d" name="option_d" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Correct Option</label>
                            <select id="correct_option" name="correct_option" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
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

                <!-- Questions List -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Questions</h2>
                    <div class="space-y-4">
                        <?php while ($question = $questions_result->fetch_assoc()): ?>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h3 class="text-lg font-medium text-gray-800 mb-2">Question <?php echo $question['id']; ?></h3>
                                <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-2 <?php echo ($question['correct_option'] == 'A') ? 'text-green-600 font-medium' : 'text-gray-600'; ?>">
                                        <span class="w-6">A.</span>
                                        <span><?php echo htmlspecialchars($question['option_a']); ?></span>
                                        <?php if ($question['correct_option'] == 'A'): ?>
                                            <i class="fas fa-check-circle text-green-500"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-2 <?php echo ($question['correct_option'] == 'B') ? 'text-green-600 font-medium' : 'text-gray-600'; ?>">
                                        <span class="w-6">B.</span>
                                        <span><?php echo htmlspecialchars($question['option_b']); ?></span>
                                        <?php if ($question['correct_option'] == 'B'): ?>
                                            <i class="fas fa-check-circle text-green-500"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-2 <?php echo ($question['correct_option'] == 'C') ? 'text-green-600 font-medium' : 'text-gray-600'; ?>">
                                        <span class="w-6">C.</span>
                                        <span><?php echo htmlspecialchars($question['option_c']); ?></span>
                                        <?php if ($question['correct_option'] == 'C'): ?>
                                            <i class="fas fa-check-circle text-green-500"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-2 <?php echo ($question['correct_option'] == 'D') ? 'text-green-600 font-medium' : 'text-gray-600'; ?>">
                                        <span class="w-6">D.</span>
                                        <span><?php echo htmlspecialchars($question['option_d']); ?></span>
                                        <?php if ($question['correct_option'] == 'D'): ?>
                                            <i class="fas fa-check-circle text-green-500"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <form method="POST" action="edit_exam.php?id=<?php echo $exam_id; ?>" class="mt-4">
                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                    <button type="submit" name="delete_question" onclick="return confirm('Are you sure you want to delete this question?')" 
                                        class="text-red-600 hover:text-red-900 text-sm font-medium">
                                        <i class="fas fa-trash-alt mr-1"></i> Delete Question
                                    </button>
                                </form>
                            </div>
                        <?php endwhile; ?>
                        <?php if ($questions_result->num_rows === 0): ?>
                            <p class="text-gray-500 text-center py-4">No questions added to this exam yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 