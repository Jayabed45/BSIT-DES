<?php
session_start();
include '../includes/db_connection.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: ../login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Fetch exam details and student's attempt
$stmt = $conn->prepare("
    SELECT e.*, ea.score, ea.started_at, ea.completed_at,
           (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as total_questions
    FROM exams e
    JOIN exam_attempts ea ON e.id = ea.exam_id
    WHERE e.id = ? AND ea.student_id = ?
");
$stmt->bind_param("ii", $exam_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Exam result not found.");
}

$exam = $result->fetch_assoc();
$score_percentage = ($exam['score'] / $exam['total_questions']) * 100;

// Calculate score and answers
$score = round($score_percentage);
$correct_answers = $exam['score'];
$total_questions = $exam['total_questions'];

// Fetch questions for review
$questions_stmt = $conn->prepare("
    SELECT q.* 
    FROM questions q
    WHERE q.exam_id = ?
    ORDER BY q.id
");

if (!$questions_stmt) {
    die("Error preparing questions query: " . $conn->error);
}

$questions_stmt->bind_param("i", $exam_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();

// Store answers in an array for easy access
$answers = [];
while ($row = $questions_result->fetch_assoc()) {
    $answers[$row['id']] = $row['correct_option']; // Using correct_option as the answer for now
}
$questions_result->data_seek(0); // Reset the result pointer for later use
$question_num = 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Result - BSIT Exam System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .score-circle {
            background: conic-gradient(
                #2563eb 0% var(--score-percentage, 0%),
                #e2e8f0 var(--score-percentage, 0%) 100%
            );
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
<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <!-- Mobile Menu Button -->
    <div class="lg:hidden fixed top-4 left-4 z-50">
        <button id="mobileMenuButton" class="p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
            <i class="fas fa-bars text-xl"></i>
        </button>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="overlay" class="overlay"></div>

    <div class="flex h-screen bg-gray-100">
        <!-- Sidebar -->
        <div id="mobileMenu" class="mobile-menu fixed lg:static w-64 bg-white shadow-md flex flex-col h-full z-50">
            <div class="p-6 flex-1">
                <div class="flex items-center space-x-3 mb-8">
                    <i class="fas fa-graduation-cap text-2xl text-blue-600"></i>
                    <h2 class="text-2xl font-bold text-blue-600">Student Panel</h2>
                </div>
                <nav class="space-y-2">
                    <a href="student_dashboard.php" class="sidebar-item flex items-center space-x-3 text-gray-700 hover:bg-blue-100 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="profile.php" class="sidebar-item flex items-center space-x-3 text-gray-700 hover:bg-blue-100 hover:text-blue-600 p-3 rounded-lg">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                </nav>
            </div>
            <div class="p-6 border-t border-gray-200">
                <a href="../logout.php" class="sidebar-item flex items-center space-x-3 text-red-600 hover:bg-red-100 hover:text-red-800 p-3 rounded-lg">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-4 lg:p-6 overflow-y-auto">
            <div class="container mx-auto">
                <!-- Header -->
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6">
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 mb-4 lg:mb-0">Exam Results</h1>
                    <a href="student_dashboard.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Dashboard
                    </a>
                </div>

                <!-- Score Card -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex flex-col md:flex-row items-center justify-between">
                        <div class="text-center md:text-left mb-6 md:mb-0">
                            <h2 class="text-xl font-semibold text-gray-800 mb-2">Your Score</h2>
                            <p class="text-4xl font-bold text-blue-600"><?php echo $score; ?>%</p>
                            <p class="text-gray-600 mt-1"><?php echo $correct_answers; ?> out of <?php echo $total_questions; ?> correct</p>
                        </div>
                        <div class="score-circle w-32 h-32 rounded-full flex items-center justify-center">
                            <div class="text-center">
                                <span class="text-2xl font-bold text-blue-600"><?php echo $score; ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Question Review -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Question Review</h3>
                    <div class="space-y-4">
                        <?php while ($question = $questions_result->fetch_assoc()): 
                            $user_answer = $answers[$question['id']] ?? null;
                            $is_correct = $user_answer === $question['correct_option'];
                        ?>
                        <div class="p-6 bg-gray-50 rounded-lg <?php echo $is_correct ? 'border-l-4 border-green-500' : 'border-l-4 border-red-500'; ?>">
                            <div class="flex items-start justify-between mb-4">
                                <h4 class="text-lg font-medium text-gray-800">Question <?php echo $question_num; ?></h4>
                                <span class="px-3 py-1 text-sm font-medium rounded-full <?php echo $is_correct ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $is_correct ? 'Correct' : 'Incorrect'; ?>
                                </span>
                            </div>
                            <p class="text-gray-700 mb-4"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="p-3 bg-white rounded-lg">
                                    <span class="font-medium text-gray-600">A:</span>
                                    <span class="text-gray-700"><?php echo htmlspecialchars($question['option_a']); ?></span>
                                </div>
                                <div class="p-3 bg-white rounded-lg">
                                    <span class="font-medium text-gray-600">B:</span>
                                    <span class="text-gray-700"><?php echo htmlspecialchars($question['option_b']); ?></span>
                                </div>
                                <div class="p-3 bg-white rounded-lg">
                                    <span class="font-medium text-gray-600">C:</span>
                                    <span class="text-gray-700"><?php echo htmlspecialchars($question['option_c']); ?></span>
                                </div>
                                <div class="p-3 bg-white rounded-lg">
                                    <span class="font-medium text-gray-600">D:</span>
                                    <span class="text-gray-700"><?php echo htmlspecialchars($question['option_d']); ?></span>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-2 md:space-y-0">
                                    <div>
                                        <span class="font-medium text-gray-600">Your answer:</span>
                                        <span class="text-gray-700 ml-2"><?php echo $user_answer ? $user_answer : 'Not answered'; ?></span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-600">Correct answer:</span>
                                        <span class="text-gray-700 ml-2"><?php echo $question['correct_option']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php 
                        $question_num++;
                        endwhile; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set the score percentage for the circular progress indicator
        document.documentElement.style.setProperty('--score-percentage', '<?php echo $score; ?>%');

        // Mobile menu functionality
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('overlay');

        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
        });
    </script>
</body>
</html> 