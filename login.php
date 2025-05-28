<?php
session_start();
include 'includes/db_connection.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_username = trim($_POST['email_or_username']);
    $password = $_POST['password'];

    // Check student login (by email)
    $stmt = $conn->prepare("SELECT id, password FROM students WHERE email = ?");
    $stmt->bind_param("s", $email_or_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $student = $result->fetch_assoc();
        if (password_verify($password, $student['password'])) {
            $_SESSION['student_id'] = $student['id'];
            header("Location: student/student_dashboard.php");
            exit();
        }
    }
    $stmt->close();

    // Check admin login (by username)
    $stmt = $conn->prepare("SELECT id, password, role FROM admins WHERE username = ?");
    $stmt->bind_param("s", $email_or_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_role'] = $admin['role'];

            if ($admin['role'] === 'admin1') {
                header("Location: admin-1/admin_dashboard.php");
                exit();
            } elseif ($admin['role'] === 'admin2') {
                header("Location: admin-2/admin2_dashboard.php");
                exit();
            } else {
                header("Location: admin_dashboard.php");
                exit();
            }
        }
    }
    $stmt->close();

    // If login fails
    $error = "Invalid email/username or password.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSIT DEPARTAMENTAL EXAMINATION SYSTEM - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-2xl font-bold text-blue-600">BSIT DEPARTAMENTAL EXAMINATION SYSTEM</a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="text-gray-700 hover:text-blue-600">Login</a>
                    <a href="register.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Register</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-white">
        <div class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                    Welcome to BSIT DEPARTAMENTAL EXAMINATION SYSTEM
                </h1>
                <p class="mt-3 max-w-md mx-auto text-base text-gray-500 sm:text-lg md:mt-5 md:text-xl md:max-w-3xl">
                    A comprehensive online examination platform for CTU students
                </p>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="py-12 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="text-blue-600 mb-4">
                        <i class="fas fa-laptop-code text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Online Exams</h3>
                    <p class="mt-2 text-gray-500">Take exams anytime, anywhere with our secure online platform.</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="text-blue-600 mb-4">
                        <i class="fas fa-chart-line text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Real-time Results</h3>
                    <p class="mt-2 text-gray-500">Get instant feedback and track your progress over time.</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="text-blue-600 mb-4">
                        <i class="fas fa-shield-alt text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Secure Platform</h3>
                    <p class="mt-2 text-gray-500">Your data is protected with advanced security measures.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Form -->
    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Sign in to your account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or
                <a href="register.php" class="font-medium text-blue-600 hover:text-blue-500">
                    create a new account
                </a>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" method="POST" action="login.php">
                    <div>
                        <label for="email_or_username" class="block text-sm font-medium text-gray-700">
                            Email (Student) or Username (Admin)
                        </label>
                        <div class="mt-1">
                            <input id="email_or_username" name="email_or_username" type="text" required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password
                        </label>
                        <div class="mt-1">
                            <input id="password" name="password" type="password" required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Sign in
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer
    <footer class="bg-white">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="text-center text-gray-500 text-sm">
                &copy; 2024 BSIT Exam System. All rights reserved.
            </div>
        </div> -->
    </footer>
</body>
</html>
