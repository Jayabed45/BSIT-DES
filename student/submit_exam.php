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

// Get exam and attempt IDs
$exam_id = $_POST['exam_id'] ?? null;
$attempt_id = $_POST['attempt_id'] ?? null;
if (!$exam_id || !$attempt_id) {
    die("Invalid submission.");
}

// Get student's answers
$answers = $_POST['answer'] ?? [];
if (empty($answers)) {
    die("No answers submitted.");
}

// Calculate score
$score = 0;
$total_questions = 0;

// Get correct answers and calculate score
$questions_sql = "SELECT id, correct_option FROM questions WHERE exam_id = ?";
$questions_stmt = $conn->prepare($questions_sql);
$questions_stmt->bind_param("i", $exam_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();

while ($question = $questions_result->fetch_assoc()) {
    $total_questions++;
    if (isset($answers[$question['id']]) && $answers[$question['id']] === $question['correct_option']) {
        $score++;
    }
}

// Calculate percentage
$percentage = ($score / $total_questions) * 100;

// Update exam attempt with score and completion time
$completion_time = date('Y-m-d H:i:s');
$update_sql = "UPDATE exam_attempts SET score = ?, completed_at = ? WHERE id = ? AND student_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("isii", $score, $completion_time, $attempt_id, $student_id);
$update_stmt->execute();

// Insert into student_scores
$score_sql = "INSERT INTO student_scores (student_id, exam_id, score) VALUES (?, ?, ?)";
$score_stmt = $conn->prepare($score_sql);
$score_stmt->bind_param("iid", $student_id, $exam_id, $percentage);
$score_stmt->execute();

// Update exam assignment status
$status_sql = "UPDATE exam_assignments SET status = 'completed' WHERE exam_id = ? AND student_id = ?";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->bind_param("ii", $exam_id, $student_id);
$status_stmt->execute();

// Redirect to results page
header("Location: exam_result.php?exam_id=" . $exam_id);
exit;
?> 