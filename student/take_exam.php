<?php
session_start();

// Database connection setup
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bsit_exam_system";

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
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .exam-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .exam-header {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2d89ef;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: bold;
        }
        .question {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .options {
            margin-top: 10px;
        }
        .option {
            margin: 10px 0;
        }
        .option label {
            display: block;
            padding: 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        .option label:hover {
            background: #f0f0f0;
        }
        .submit-btn {
            background: #2d89ef;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .submit-btn:hover {
            background: #2b7ad9;
        }
    </style>
</head>
<body>
    <div class="timer" id="timer">Time Remaining: <span id="time"><?php echo $exam['duration_minutes']; ?>:00</span></div>
    
    <div class="exam-container">
        <div class="exam-header">
            <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
            <p><?php echo nl2br(htmlspecialchars($exam['description'])); ?></p>
        </div>

        <form id="examForm" action="submit_exam.php" method="POST">
            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
            <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
            
            <?php 
            $question_num = 1;
            while ($question = $questions_result->fetch_assoc()): 
            ?>
            <div class="question">
                <h3>Question <?php echo $question_num; ?></h3>
                <p><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                
                <div class="options">
                    <div class="option">
                        <label>
                            <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="A" required>
                            <?php echo htmlspecialchars($question['option_a']); ?>
                        </label>
                    </div>
                    <div class="option">
                        <label>
                            <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="B" required>
                            <?php echo htmlspecialchars($question['option_b']); ?>
                        </label>
                    </div>
                    <div class="option">
                        <label>
                            <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="C" required>
                            <?php echo htmlspecialchars($question['option_c']); ?>
                        </label>
                    </div>
                    <div class="option">
                        <label>
                            <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="D" required>
                            <?php echo htmlspecialchars($question['option_d']); ?>
                        </label>
                    </div>
                </div>
            </div>
            <?php 
            $question_num++;
            endwhile; 
            ?>

            <button type="submit" class="submit-btn">Submit Exam</button>
        </form>
    </div>

    <script>
        // Timer functionality
        let duration = <?php echo $exam['duration_minutes']; ?> * 60; // Convert to seconds
        const timerDisplay = document.getElementById('time');
        
        const timer = setInterval(() => {
            duration--;
            const minutes = Math.floor(duration / 60);
            const seconds = duration % 60;
            timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (duration <= 0) {
                clearInterval(timer);
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