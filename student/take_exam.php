<?php
session_start();

// Database connection setup
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bsit_exam_system_main";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
    die("Student not logged in.");
}

// Get exam ID from URL
$exam_id = $_GET['exam_id'] ?? null;
if (!$exam_id) {
    die("No exam specified.");
}

// Fetch exam details
$exam_sql = "SELECT * FROM exams WHERE id = ?";
$exam_stmt = $conn->prepare($exam_sql);

// Check if prepare() failed
if ($exam_stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}

$exam_stmt->bind_param("i", $exam_id);
$exam_stmt->execute();
$exam_result = $exam_stmt->get_result();
if ($exam_result->num_rows === 0) {
    die("Exam not found.");
}
$exam = $exam_result->fetch_assoc();

// Fetch questions for this exam
$questions_sql = "SELECT * FROM questions WHERE exam_id = ? ORDER BY id";
$questions_stmt = $conn->prepare($questions_sql);
$questions_stmt->bind_param("i", $exam_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();

// Check if student has already attempted this exam
$attempt_sql = "SELECT * FROM exam_attempts WHERE student_id = ? AND exam_id = ?";
$attempt_stmt = $conn->prepare($attempt_sql);
$attempt_stmt->bind_param("ii", $student_id, $exam_id);
$attempt_stmt->execute();
$attempt_result = $attempt_stmt->get_result();
if ($attempt_result->num_rows > 0) {
    die("You have already taken this exam.");
}

// Start exam attempt
$start_time = date('Y-m-d H:i:s');
$attempt_insert_sql = "INSERT INTO exam_attempts (student_id, exam_id, started_at) VALUES (?, ?, ?)";
$attempt_insert_stmt = $conn->prepare($attempt_insert_sql);
$attempt_insert_stmt->bind_param("iis", $student_id, $exam_id, $start_time);
$attempt_insert_stmt->execute();
$attempt_id = $conn->insert_id;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taking Exam: <?php echo htmlspecialchars($exam['title']); ?></title>
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
            .exam-container {
                margin-top: 4rem;
            }
            .timer {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10;
                border-radius: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <div class="flex flex-col items-center min-h-screen bg-gray-100 p-4 lg:p-6">
        <!-- Timer -->
        <div class="timer bg-blue-600 text-white text-base lg:text-lg font-bold py-2 px-4 rounded-md shadow-lg z-10 w-full text-center">
            Time Remaining: <span id="time"><?php echo $exam['duration_minutes']; ?>:00</span>
        </div>
        
        <div class="exam-container max-w-3xl w-full bg-white rounded-lg shadow-xl p-4 lg:p-8 mt-4 lg:mt-12">
            <div class="exam-header mb-6 lg:mb-8 pb-4 lg:pb-6 border-b border-gray-200 text-center">
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 mb-2">Taking Exam: <?php echo htmlspecialchars($exam['title']); ?></h1>
                <p class="text-gray-600 text-sm lg:text-base"><?php echo nl2br(htmlspecialchars($exam['description'])); ?></p>
            </div>

            <form id="examForm" action="submit_exam.php" method="POST">
                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                
                <?php 
                $question_num = 1;
                while ($question = $questions_result->fetch_assoc()): 
                ?>
                <div class="question mb-6 lg:mb-8 p-4 lg:p-6 bg-gray-50 rounded-lg shadow-sm">
                    <h3 class="text-base lg:text-lg font-semibold text-gray-800 mb-3 lg:mb-4">Question <?php echo $question_num; ?>:</h3>
                    <p class="text-gray-700 text-sm lg:text-base mb-4 lg:mb-6"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                    
                    <div class="options space-y-3 lg:space-y-4">
                        <div class="option">
                            <label class="flex items-center p-3 lg:p-4 bg-white rounded-md border border-gray-300 cursor-pointer hover:bg-gray-100 transition duration-200">
                                <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="A" required class="form-radio h-4 w-4 lg:h-5 lg:w-5 text-blue-600">
                                <span class="ml-3 text-gray-700 text-sm lg:text-base"><?php echo htmlspecialchars($question['option_a']); ?></span>
                            </label>
                        </div>
                        <div class="option">
                            <label class="flex items-center p-3 lg:p-4 bg-white rounded-md border border-gray-300 cursor-pointer hover:bg-gray-100 transition duration-200">
                                <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="B" required class="form-radio h-4 w-4 lg:h-5 lg:w-5 text-blue-600">
                                <span class="ml-3 text-gray-700 text-sm lg:text-base"><?php echo htmlspecialchars($question['option_b']); ?></span>
                            </label>
                        </div>
                        <div class="option">
                            <label class="flex items-center p-3 lg:p-4 bg-white rounded-md border border-gray-300 cursor-pointer hover:bg-gray-100 transition duration-200">
                                <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="C" required class="form-radio h-4 w-4 lg:h-5 lg:w-5 text-blue-600">
                                <span class="ml-3 text-gray-700 text-sm lg:text-base"><?php echo htmlspecialchars($question['option_c']); ?></span>
                            </label>
                        </div>
                        <div class="option">
                            <label class="flex items-center p-3 lg:p-4 bg-white rounded-md border border-gray-300 cursor-pointer hover:bg-gray-100 transition duration-200">
                                <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="D" required class="form-radio h-4 w-4 lg:h-5 lg:w-5 text-blue-600">
                                <span class="ml-3 text-gray-700 text-sm lg:text-base"><?php echo htmlspecialchars($question['option_d']); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
                <?php 
                $question_num++;
                endwhile; 
                ?>

                <div class="text-center mt-6 lg:mt-8">
                    <button type="submit" class="inline-flex items-center px-6 lg:px-8 py-2 lg:py-3 border border-transparent text-sm lg:text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                        Submit Exam
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Timer functionality
        let duration = <?php echo $exam['duration_minutes']; ?> * 60; // Convert to seconds
        const timerDisplay = document.getElementById('time');
        
        const timerInterval = setInterval(() => {
            duration--;
            const minutes = Math.floor(duration / 60);
            const seconds = duration % 60;
            timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (duration <= 0) {
                clearInterval(timerInterval);
                // Auto-submit form when timer runs out
                document.getElementById('examForm').submit();
            }
        }, 1000);

        // Prevent accidental navigation away
        window.onbeforeunload = function() {
            return "Are you sure you want to leave? Your progress will be lost.";
        };

        // Remove warning when submitting form
        document.getElementById('examForm').onsubmit = function() {
            window.onbeforeunload = null;
        };
    </script>
</body>
</html> 