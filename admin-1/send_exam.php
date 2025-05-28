<?php
session_start();
include '../includes/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$admin_id = $_SESSION['admin_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Normalize and sanitize inputs
    $exam_id    = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
    $section    = isset($_GET['section']) ? strtolower(trim($_GET['section'])) : '';
    $year       = isset($_GET['year']) ? strtolower(trim($_GET['year'])) : '';
    $department = isset($_GET['department']) ? strtolower(trim($_GET['department'])) : '';

    if ($exam_id <= 0 || empty($section) || empty($year) || empty($department)) {
        $error = "Please provide exam, section, year, and department.";
    } else {
        // Verify the exam belongs to this admin and get exam title
        $stmt = $conn->prepare("SELECT id, title FROM exams WHERE id = ? AND created_by = ?");
        if (!$stmt) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        $stmt->bind_param("ii", $exam_id, $admin_id);
        $stmt->execute();
        $result_exam = $stmt->get_result();

        if ($result_exam->num_rows === 0) {
            $error = "Exam not found or you do not have permission.";
            $stmt->close();
        } else {
            $exam = $result_exam->fetch_assoc();
            $exam_title = $exam['title'];
            $stmt->close();

            // Get students in section, year, department (case-insensitive match with LIKE)
            $stmt = $conn->prepare("
                SELECT id FROM students 
                WHERE LOWER(section) LIKE ? 
                  AND LOWER(year_level) LIKE ? 
                  AND LOWER(department) LIKE ?
            ");
            if (!$stmt) {
                die("Prepare failed for student query: (" . $conn->errno . ") " . $conn->error);
            }

            // Add wildcards for flexible matching
            $like_section    = "%$section%";
            $like_year       = "%$year%";
            $like_department = "%$department%";

            $stmt->bind_param("sss", $like_section, $like_year, $like_department);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $error = "No students found in Section $section, Year $year, Department $department.";
                $stmt->close();
            } else {
                $assignedCount = 0;

                $insert_stmt = $conn->prepare("INSERT IGNORE INTO exam_assignments (exam_id, student_id) VALUES (?, ?)");
                if (!$insert_stmt) {
                    die("Prepare failed for insert query: (" . $conn->errno . ") " . $conn->error);
                }

                $notif_stmt = $conn->prepare("INSERT INTO notifications (student_id, exam_id, message) VALUES (?, ?, ?)");
                if (!$notif_stmt) {
                    die("Prepare failed for notifications: (" . $conn->errno . ") " . $conn->error);
                }

                // Fetch students and assign exam
                while ($student = $result->fetch_assoc()) {
                    $student_id = $student['id'];

                    $insert_stmt->bind_param("ii", $exam_id, $student_id);
                    $executed = $insert_stmt->execute();

                    if ($executed && $insert_stmt->affected_rows > 0) {
                        $assignedCount++;

                        $message = "New exam assigned: " . $exam_title;
                        $notif_stmt->bind_param("iis", $student_id, $exam_id, $message);
                        $notif_stmt->execute();
                    }
                }

                $stmt->close();         // Close the SELECT students statement here
                $insert_stmt->close();
                $notif_stmt->close();

                // Optional: log the dispatch
                $log_stmt = $conn->prepare("INSERT INTO exam_sends (exam_id, year, section, department) VALUES (?, ?, ?, ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("isss", $exam_id, $year, $section, $department);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                if ($assignedCount > 0) {
                    $success = "Exam successfully sent to $assignedCount student(s) in Section $section, Year $year, Department $department.";
                } else {
                    $error = "Exam was already assigned to all students in that group.";
                }
            }
        }
    }
} else {
    $error = "Invalid request method.";
}

// Output messages (you can replace this with your UI display)
if (!empty($error)) {
    echo "<p style='color:red;'>Error: $error</p>";
}
if (!empty($success)) {
    echo "<p style='color:green;'>Success: $success</p>";
}
?>



<!DOCTYPE html>
<html>
<head>
    <title>Send Exam Result</title>
    <style>
        .msg { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        .container { width: 60%; margin: auto; padding-top: 30px; }
        .button { padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Send Exam Result</h1>

    <?php if ($error): ?>
        <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <a class="button" href="admin_dashboard.php">Back to Dashboard</a>
</div>
</body>
</html>
