<?php
session_start();
include '../includes/db_connection.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$student_id = $_SESSION['student_id'];
$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        if (isset($_POST['mark_all']) && $_POST['mark_all'] === '1') {
            // Mark all notifications as read
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE student_id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $response['success'] = true;
            $response['message'] = 'All notifications marked as read';
        } elseif (isset($_POST['notification_id'])) {
            // Mark single notification as read
            $notification_id = intval($_POST['notification_id']);
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND student_id = ?");
            $stmt->bind_param("ii", $notification_id, $student_id);
            $stmt->execute();
            $response['success'] = true;
            $response['message'] = 'Notification marked as read';
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?> 