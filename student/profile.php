<?php
session_start();
include '../includes/db_connection.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: ../login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch student information
$stmt = $conn->prepare("
    SELECT * FROM students WHERE id = ?
");

if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($current_password)) {
        // Verify current password
        $verify_stmt = $conn->prepare("SELECT password FROM students WHERE id = ?");
        if ($verify_stmt === false) {
            die("Error preparing statement: " . $conn->error);
        }
        
        $verify_stmt->bind_param("i", $student_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $stored_password = $verify_result->fetch_assoc()['password'];

        if (password_verify($current_password, $stored_password)) {
            if ($new_password === $confirm_password) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
                if ($update_stmt === false) {
                    die("Error preparing statement: " . $conn->error);
                }
                
                $update_stmt->bind_param("si", $hashed_password, $student_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Password updated successfully!";
                } else {
                    $error_message = "Failed to update password.";
                }
            } else {
                $error_message = "New passwords do not match.";
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    }

    // Update email if provided
    if (!empty($email) && $email !== $student['email']) {
        $update_stmt = $conn->prepare("UPDATE students SET email = ? WHERE id = ?");
        if ($update_stmt === false) {
            die("Error preparing statement: " . $conn->error);
        }
        
        $update_stmt->bind_param("si", $email, $student_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            $student['email'] = $email;
        } else {
            $error_message = "Failed to update email.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - BSIT Exam System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2563eb;
            text-decoration: none;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .profile-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .profile-header p {
            color: #64748b;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 3rem;
            font-weight: 600;
            color: #64748b;
        }

        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-item {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
        }

        .info-item h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .info-item p {
            font-size: 1.125rem;
            font-weight: 500;
            color: #1e293b;
        }

        .form-section {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .form-section h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            color: #1e293b;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .profile-card {
                padding: 1.5rem;
            }

            .profile-info {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="student_dashboard.php" class="logo">BSIT Exam System</a>
        <a href="student_dashboard.php" class="back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Dashboard
        </a>
    </nav>

    <div class="container">
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php 
                        $name_parts = explode(' ', $student['full_name']);
                        $initials = '';
                        if (count($name_parts) >= 2) {
                            $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts)-1], 0, 1));
                        } else {
                            $initials = strtoupper(substr($student['full_name'], 0, 2));
                        }
                        echo $initials;
                    ?>
                </div>
                <h1><?php echo htmlspecialchars($student['full_name']); ?></h1>
                <p>Student ID: <?php echo htmlspecialchars($student['id']); ?></p>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="profile-info">
                <div class="info-item">
                    <h3>Department</h3>
                    <p><?php echo htmlspecialchars($student['department']); ?></p>
                </div>
                <div class="info-item">
                    <h3>Year Level</h3>
                    <p><?php echo htmlspecialchars($student['year_level']); ?></p>
                </div>
                <div class="info-item">
                    <h3>Section</h3>
                    <p><?php echo htmlspecialchars($student['section']); ?></p>
                </div>
            </div>

            <form method="POST" action="">
                <div class="form-section">
                    <h2>Update Email</h2>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Change Password</h2>
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password">
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
    </div>
</body>
</html> 