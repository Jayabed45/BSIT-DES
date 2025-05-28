<?php
include 'includes/db_connection.php'; // Your mysqli connection in $conn

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = $_POST['role'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password_raw = $_POST['password'] ?? '';

    if (!$role || !$username || !$password_raw) {
        $error = "Please fill all required fields.";
    } else {
        // Hash the password
        $password = password_hash($password_raw, PASSWORD_BCRYPT);

        if ($role === 'student') {
            // Get additional student fields
            $email = trim($_POST['email'] ?? '');
            $year_level = $_POST['year_level'] ?? '';
            $section = $_POST['section'] ?? '';
            $department = $_POST['department'] ?? '';

            if (empty($email) || empty($year_level) || empty($section) || empty($department)) {
                $error = "Please fill all required student fields (email, year level, section, department).";
            } else {
                // Insert student
                $stmt = $conn->prepare("INSERT INTO students (full_name, email, password, year_level, section, department) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    $error = "Prepare failed for students: " . $conn->error;
                } else {
                    $stmt->bind_param("ssssss", $username, $email, $password, $year_level, $section, $department);
                    if ($stmt->execute()) {
                        $success = "Student registration successful! <a href='login.php' class='text-blue-600 hover:text-blue-500'>Login here</a>";
                    } else {
                        $error = "Student registration failed: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        } elseif ($role === 'admin1' || $role === 'admin2') {
            // Insert admin
            $stmt = $conn->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)");
            if (!$stmt) {
                $error = "Prepare failed for admins: " . $conn->error;
            } else {
                $stmt->bind_param("sss", $username, $password, $role);
                if ($stmt->execute()) {
                    $success = ucfirst($role) . " registration successful! <a href='login.php' class='text-blue-600 hover:text-blue-500'>Login here</a>";
                } else {
                    $error = ucfirst($role) . " registration failed: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $error = "Invalid role selected.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSIT Exam System - Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-2xl font-bold text-blue-600">BSIT Exam System</a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="text-gray-700 hover:text-blue-600">Login</a>
                    <a href="register.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Register</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Registration Form -->
    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Create your account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or
                <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">
                    sign in to your account
                </a>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" method="POST" id="registerForm">
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" id="role" onchange="toggleFields()" required
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="student" <?php echo (($_POST['role'] ?? '') == 'student') ? 'selected' : ''; ?>>Student</option>
                            <option value="admin1" <?php echo (($_POST['role'] ?? '') == 'admin1') ? 'selected' : ''; ?>>Admin 1</option>
                            <option value="admin2" <?php echo (($_POST['role'] ?? '') == 'admin2') ? 'selected' : ''; ?>>Admin 2</option>
                        </select>
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username / Full Name</label>
                        <input type="text" name="username" id="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" name="password" id="password" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div id="studentFields" style="display: none;">
                        <div class="space-y-6">
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="year_level" class="block text-sm font-medium text-gray-700">Year Level</label>
                                <select name="year_level" id="year_level"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Select Year Level</option>
                                    <option value="1st Year" <?php echo (($_POST['year_level'] ?? '') == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo (($_POST['year_level'] ?? '') == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo (($_POST['year_level'] ?? '') == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo (($_POST['year_level'] ?? '') == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                </select>
                            </div>

                            <div>
                                <label for="section" class="block text-sm font-medium text-gray-700">Section</label>
                                <select name="section" id="section"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Select Section</option>
                                    <option value="A" <?php echo (($_POST['section'] ?? '') == 'A') ? 'selected' : ''; ?>>Section A</option>
                                    <option value="B" <?php echo (($_POST['section'] ?? '') == 'B') ? 'selected' : ''; ?>>Section B</option>
                                    <option value="C" <?php echo (($_POST['section'] ?? '') == 'C') ? 'selected' : ''; ?>>Section C</option>
                                    <option value="D" <?php echo (($_POST['section'] ?? '') == 'D') ? 'selected' : ''; ?>>Section D</option>
                                    <option value="E" <?php echo (($_POST['section'] ?? '') == 'E') ? 'selected' : ''; ?>>Section E</option>
                                </select>
                            </div>

                            <div>
                                <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                                <select name="department" id="department"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Select Department</option>
                                    <option value="College of Engineering" <?php echo (($_POST['department'] ?? '') == 'College of Engineering') ? 'selected' : ''; ?>>College of Engineering</option>
                                    <option value="College of Technology" <?php echo (($_POST['department'] ?? '') == 'College of Technology') ? 'selected' : ''; ?>>College of Technology</option>
                                    <option value="Bachelor of Science in Information Technology" <?php echo (($_POST['department'] ?? '') == 'Bachelor of Science in Information Technology') ? 'selected' : ''; ?>>Bachelor of Science in Information Technology</option>
                                    <option value="College Of Education" <?php echo (($_POST['department'] ?? '') == 'College Of Education') ? 'selected' : ''; ?>>College Of Education</option>
                                    <option value="College of Agriculture" <?php echo (($_POST['department'] ?? '') == 'College of Agriculture') ? 'selected' : ''; ?>>College of Agriculture</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Register
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    

    <script>
        function toggleFields() {
            const role = document.getElementById("role").value;
            const studentFields = document.getElementById("studentFields");
            const studentInputs = studentFields.querySelectorAll("input, select");
            
            if (role === "student") {
                studentFields.style.display = "block";
                studentInputs.forEach(input => input.required = true);
            } else {
                studentFields.style.display = "none";
                studentInputs.forEach(input => input.required = false);
            }
        }
        window.onload = toggleFields;
    </script>
</body>
</html>
