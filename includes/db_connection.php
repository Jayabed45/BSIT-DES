<?php
$host = 'localhost';
$db = 'bsit_exam_system_main';
$user = 'root';
$pass = ''; // Update if your MySQL password is not empty

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
