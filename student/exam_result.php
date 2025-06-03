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
        @media (max-width: 768px) {
            .result-container {
                margin-top: 1rem;
            }
            .score-circle {
                width: 150px;
                height: 150px;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <div class="flex flex-col items-center min-h-screen bg-gray-100 p-4 lg:p-6">
        <div class="result-container max-w-3xl w-full bg-white rounded-lg shadow-xl p-4 lg:p-8">
            <div class="text-center mb-6 lg:mb-8">
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 mb-2">Exam Results</h1>
                <p class="text-gray-600 text-sm lg:text-base"><?php echo htmlspecialchars($exam['title']); ?></p>
            </div>

            <div class="flex flex-col lg:flex-row items-center justify-between mb-6 lg:mb-8 p-4 lg:p-6 bg-gray-50 rounded-lg">
                <div class="text-center lg:text-left mb-4 lg:mb-0">
                    <h2 class="text-xl lg:text-2xl font-semibold text-gray-800 mb-2">Your Score</h2>
                    <p class="text-3xl lg:text-4xl font-bold text-blue-600"><?php echo $score; ?>%</p>
                    <p class="text-gray-600 text-sm lg:text-base mt-1"><?php echo $correct_answers; ?> out of <?php echo $total_questions; ?> correct</p>
                </div>
                
                <div class="score-circle w-32 h-32 lg:w-40 lg:h-40 rounded-full flex items-center justify-center">
                    <div class="text-center">
                        <span class="text-2xl lg:text-3xl font-bold text-blue-600"><?php echo $score; ?>%</span>
                    </div>
                </div>
            </div>

            <div class="mb-6 lg:mb-8">
                <h3 class="text-lg lg:text-xl font-semibold text-gray-800 mb-3 lg:mb-4">Question Review</h3>
                <div class="space-y-4">
                    <?php while ($question = $questions_result->fetch_assoc()): 
                        $user_answer = $answers[$question['id']] ?? null;
                        $is_correct = $user_answer === $question['correct_answer'];
                    ?>
                    <div class="p-4 lg:p-6 bg-gray-50 rounded-lg <?php echo $is_correct ? 'border-l-4 border-green-500' : 'border-l-4 border-red-500'; ?>">
                        <div class="flex items-start justify-between mb-3">
                            <h4 class="text-base lg:text-lg font-medium text-gray-800">Question <?php echo $question_num; ?></h4>
                            <span class="px-2 py-1 text-xs lg:text-sm font-medium rounded-full <?php echo $is_correct ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $is_correct ? 'Correct' : 'Incorrect'; ?>
                            </span>
                        </div>
                        <p class="text-gray-700 text-sm lg:text-base mb-3"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                        
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <span class="text-sm lg:text-base font-medium text-gray-600 w-8">A:</span>
                                <span class="text-sm lg:text-base text-gray-700"><?php echo htmlspecialchars($question['option_a']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="text-sm lg:text-base font-medium text-gray-600 w-8">B:</span>
                                <span class="text-sm lg:text-base text-gray-700"><?php echo htmlspecialchars($question['option_b']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="text-sm lg:text-base font-medium text-gray-600 w-8">C:</span>
                                <span class="text-sm lg:text-base text-gray-700"><?php echo htmlspecialchars($question['option_c']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="text-sm lg:text-base font-medium text-gray-600 w-8">D:</span>
                                <span class="text-sm lg:text-base text-gray-700"><?php echo htmlspecialchars($question['option_d']); ?></span>
                            </div>
                        </div>

                        <div class="mt-3 lg:mt-4 pt-3 lg:pt-4 border-t border-gray-200">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-2 lg:space-y-0">
                                <div>
                                    <span class="text-sm lg:text-base font-medium text-gray-600">Your answer:</span>
                                    <span class="text-sm lg:text-base text-gray-700 ml-2"><?php echo $user_answer ? $user_answer : 'Not answered'; ?></span>
                                </div>
                                <div>
                                    <span class="text-sm lg:text-base font-medium text-gray-600">Correct answer:</span>
                                    <span class="text-sm lg:text-base text-gray-700 ml-2"><?php echo $question['correct_answer']; ?></span>
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

            <div class="text-center">
                <a href="student_dashboard.php" class="inline-flex items-center px-6 lg:px-8 py-2 lg:py-3 border border-transparent text-sm lg:text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                    Return to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        // Set the score percentage for the circular progress indicator
        document.documentElement.style.setProperty('--score-percentage', '<?php echo $score; ?>%');
    </script>
</body>
</html> 