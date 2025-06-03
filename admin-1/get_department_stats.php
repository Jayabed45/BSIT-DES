<?php
session_start();
include '../includes/db_connection.php';

if (!isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}

// Get department statistics
$query = "
    SELECT department, COUNT(*) as student_count 
    FROM students 
    GROUP BY department 
    ORDER BY student_count DESC
";

$result = $conn->query($query);

$departments = [];
$counts = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row['department'];
        $counts[] = (int)$row['student_count'];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'departments' => $departments,
    'counts' => $counts
]); 