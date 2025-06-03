<?php
session_start();
include '../includes/db_connection.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: ../login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Define departments array based on the database
$departments = [
    'Bachelor of Science in Information Technology',
    'College of Engineering'
];

// Define year levels based on the database enum
$year_levels = [
    '1st Year',
    '2nd Year',
    '3rd Year',
    '4th Year'
];

// Define sections based on the database enum
$sections = [
    'A',
    'B',
    'C',
    'D',
    'E'
];

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
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
        $year_level = filter_input(INPUT_POST, 'year_level', FILTER_SANITIZE_STRING);
        $section = filter_input(INPUT_POST, 'section', FILTER_SANITIZE_STRING);

        $update_stmt = $conn->prepare("
            UPDATE students 
            SET full_name = ?, email = ?, department = ?, year_level = ?, section = ?
            WHERE id = ?
        ");

        if ($update_stmt === false) {
            die("Error preparing statement: " . $conn->error);
        }

        $update_stmt->bind_param("sssssi", $full_name, $email, $department, $year_level, $section, $_SESSION['student_id']);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh student data
            $stmt->execute();
            $result = $stmt->get_result();
            $student = $result->fetch_assoc();
        } else {
            $error_message = "Failed to update profile.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .sidebar-item {
            transition: all 0.3s ease;
        }
        .sidebar-item:hover {
            transform: translateX(5px);
        }
        @media (max-width: 768px) {
            .profile-container {
                margin-top: 1rem;
            }
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
                    <a href="profile.php" class="sidebar-item flex items-center space-x-3 text-blue-600 bg-blue-100 p-3 rounded-lg font-semibold">
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
            <div class="profile-container max-w-3xl mx-auto bg-white rounded-lg shadow-xl p-4 lg:p-8">
                <div class="text-center mb-6 lg:mb-8">
                    <div class="flex justify-center mb-4">
                        <div class="w-24 h-24 lg:w-32 lg:h-32 rounded-full bg-blue-600 flex items-center justify-center text-white text-4xl lg:text-5xl font-bold">
                            <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                        </div>
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 mb-2">Student Profile</h1>
                    <p class="text-gray-600 text-sm lg:text-base">Manage your account information</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">
                    <!-- Personal Information -->
                    <div class="bg-gray-50 rounded-lg p-4 lg:p-6">
                        <h2 class="text-lg lg:text-xl font-semibold text-gray-800 mb-4">Personal Information</h2>
                        <form action="profile.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div>
                                <label class="block text-sm lg:text-base font-medium text-gray-700 mb-1">Full Name</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" 
                                       class="w-full px-3 lg:px-4 py-2 lg:py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm lg:text-base" required>
                            </div>

                            <div>
                                <label class="block text-sm lg:text-base font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" 
                                       class="w-full px-3 lg:px-4 py-2 lg:py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm lg:text-base" required>
                            </div>

                            <div>
                                <label class="block text-sm lg:text-base font-medium text-gray-700 mb-1">Department</label>
                                <?php if (!empty($student['department'])): ?>
                                    <!-- Read-only department if already set -->
                                    <input type="text" value="<?php echo htmlspecialchars($student['department']); ?>" 
                                           class="w-full px-3 lg:px-4 py-2 lg:py-3 border border-gray-300 rounded-md shadow-sm bg-gray-50 text-gray-600 text-sm lg:text-base" 
                                           readonly>
                                    <input type="hidden" name="department" value="<?php echo htmlspecialchars($student['department']); ?>">
                                <?php else: ?>
                                    <!-- Editable department if not set -->
                                    <select name="department" class="w-full px-3 lg:px-4 py-2 lg:py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm lg:text-base" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept; ?>" <?php echo $student['department'] === $dept ? 'selected' : ''; ?>>
                                            <?php echo $dept; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label class="block text-sm lg:text-base font-medium text-gray-700 mb-1">Year Level</label>
                                <select name="year_level" class="w-full px-3 lg:px-4 py-2 lg:py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm lg:text-base" required>
                                    <?php foreach ($year_levels as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $student['year_level'] === $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm lg:text-base font-medium text-gray-700 mb-1">Section</label>
                                <select name="section" class="w-full px-3 lg:px-4 py-2 lg:py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm lg:text-base" required>
                                    <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section; ?>" <?php echo $student['section'] === $section ? 'selected' : ''; ?>>
                                        <?php echo $section; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="pt-4">
                                <button type="submit" class="w-full inline-flex justify-center items-center px-4 lg:px-6 py-2 lg:py-3 border border-transparent text-sm lg:text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="bg-gray-50 rounded-lg p-4 lg:p-6">
                        <h2 class="text-lg lg:text-xl font-semibold text-gray-800 mb-4">Change Password</h2>
                        <form action="profile.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div>
                                <label class="block text-sm lg:text-base font-medium text-gray-700 mb-1">Current Password</label>
                                <input type="password" name="current_password" 
                                       class="w-full px-3 lg:px-4 py-2 lg:py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm lg:text-base" required>
                            </div>

                            <div>
                                <label class="block text-sm lg:text-base font-medium text-gray-700 mb-1">New Password</label>
                                <input type="password" name="new_password" 
                                       class="w-full px-3 lg:px-4 py-2 lg:py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm lg:text-base" required>
                            </div>

                            <div>
                                <label class="block text-sm lg:text-base font-medium text-gray-700 mb-1">Confirm New Password</label>
                                <input type="password" name="confirm_password" 
                                       class="w-full px-3 lg:px-4 py-2 lg:py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm lg:text-base" required>
                            </div>

                            <div class="pt-4">
                                <button type="submit" class="w-full inline-flex justify-center items-center px-4 lg:px-6 py-2 lg:py-3 border border-transparent text-sm lg:text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                                    Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="mt-6 lg:mt-8 text-center">
                    <a href="student_dashboard.php" class="inline-flex items-center px-6 lg:px-8 py-2 lg:py-3 border border-transparent text-sm lg:text-base font-medium rounded-md shadow-sm text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-200">
                        Return to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add mobile menu functionality
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('overlay');

        function toggleMobileMenu() {
            mobileMenu.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        mobileMenuButton.addEventListener('click', toggleMobileMenu);
        overlay.addEventListener('click', toggleMobileMenu);
    </script>
</body>
</html> 