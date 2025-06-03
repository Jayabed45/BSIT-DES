<?php
session_start();
include 'includes/db_connection.php';

$login_error = "";
$register_error = "";
$register_success = "";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
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
                header("Location: admin-1/admin_dashboard.php");
                exit();
            } else {
                 $login_error = "Invalid email/username or password.";
            }
        } else {
             $login_error = "Invalid email/username or password.";
        }
        if (isset($stmt)) $stmt->close();

    } elseif (isset($_POST['action']) && $_POST['action'] === 'register') {
        $role = $_POST['role'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $password_raw = $_POST['password'] ?? '';

        if (!$role || !$username || !$password_raw) {
            $register_error = "Please fill all required fields.";
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
                    $register_error = "Please fill all required student fields (email, year level, section, department).";
                } else {
                     // Check if email already exists
                    $check_email_stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
                    $check_email_stmt->bind_param("s", $email);
                    $check_email_stmt->execute();
                    $check_email_result = $check_email_stmt->get_result();
                    if ($check_email_result->num_rows > 0) {
                         $register_error = "Email already exists.";
                    } else {
                        // Insert student
                        $stmt = $conn->prepare("INSERT INTO students (full_name, email, password, year_level, section, department) VALUES (?, ?, ?, ?, ?, ?)");
                        if (!$stmt) {
                            $register_error = "Prepare failed for students: " . $conn->error;
                        } else {
                            $stmt->bind_param("ssssss", $username, $email, $password, $year_level, $section, $department);
                            if ($stmt->execute()) {
                                $register_success = "Student registration successful! You can now login.";
                            } else {
                                $register_error = "Student registration failed: " . $stmt->error;
                            }
                        }
                    }
                     if (isset($check_email_stmt)) $check_email_stmt->close();
                }
            } elseif ($role === 'admin1') {
                 // Check if username already exists
                $check_username_stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
                $check_username_stmt->bind_param("s", $username);
                $check_username_stmt->execute();
                $check_username_result = $check_username_stmt->get_result();

                if ($check_username_result->num_rows > 0) {
                    $register_error = "Username already exists.";
                } else {
                    // Insert admin
                    $stmt = $conn->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)");
                    if (!$stmt) {
                        $register_error = "Prepare failed for admins: " . $conn->error;
                    } else {
                        $stmt->bind_param("sss", $username, $password, $role);
                        if ($stmt->execute()) {
                            $register_success = "Admin registration successful! You can now login.";
                        } else {
                            $register_error = "Admin registration failed: " . $stmt->error;
                        }
                    }
                }
                if (isset($check_username_stmt)) $check_username_stmt->close();
            } else {
                $register_error = "Invalid role selected.";
            }
             if (isset($stmt)) $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEPARTAMENTAL EXAMINATION SYSTEM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
        }
        .modal-content {
            max-height: 90vh;
        }
         .form-container {
            max-height: calc(90vh - 6rem); /* Adjust based on header/padding */
            overflow-y: auto;
        }

    </style>
</head>
<body class="bg-gray-100 font-sans antialiased leading-normal tracking-wide">

    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-xl sm:text-2xl font-bold text-blue-600">BSIT EXAM SYSTEM</a>
                </div>
                <nav class="flex items-center space-x-3 sm:space-x-4">
                    <button id="loginBtn" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">Login</button>
                    <button id="registerBtn" class="bg-blue-600 text-white px-3 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition duration-150 ease-in-out">Register</button>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-blue-600 to-indigo-700 text-white py-28 px-4 sm:px-6 lg:px-8 overflow-hidden">
        <!-- Optional: Add a subtle pattern or graphic in the background -->
        <!-- <div class="absolute inset-0 z-0 opacity-10" style="background-image: url('path/to/your/subtle-pattern.png'); background-repeat: repeat;"></div> -->
        
        <div class="relative max-w-6xl mx-auto text-center z-10">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight mb-6 drop-shadow-md">
                BSIT Departmental Examination System
            </h1>
            <p class="text-lg sm:text-xl opacity-95 mb-10 max-w-3xl mx-auto leading-relaxed">
                An efficient and secure platform designed to streamline departmental exams for BSIT students and administrators.
            </p>
            <div class="space-x-4">
                 <button id="getStartedBtn" class="bg-white text-blue-600 hover:bg-gray-200 px-10 py-4 rounded-full text-lg font-semibold shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 hover:shadow-xl">Get Started</button>
            </div>
        </div>
    </section>

    <!-- Features Section -->
     <section class="py-24 px-4 sm:px-6 lg:px-8 bg-white">
        <div class="max-w-7xl mx-auto">
            <h2 class="text-3xl sm:text-4xl font-bold text-center text-gray-800 mb-12">Why Choose Us?</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-12">
                <div class="bg-gray-50 p-8 rounded-xl shadow-lg text-center transform transition duration-300 ease-in-out hover:scale-105 hover:shadow-xl">
                    <div class="text-blue-600 mb-6">
                        <i class="fas fa-laptop-code text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Online Exams</h3>
                    <p class="text-gray-600 leading-relaxed">Easily create, administer, and take exams online, anytime, anywhere.</p>
                </div>
                <div class="bg-gray-50 p-8 rounded-xl shadow-lg text-center transform transition duration-300 ease-in-out hover:scale-105 hover:shadow-xl">
                    <div class="text-blue-600 mb-6">
                        <i class="fas fa-chart-line text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Real-time Results</h3>
                    <p class="text-gray-600 leading-relaxed">Get instant scores and detailed feedback immediately after completing an exam.</p>
                </div>
                <div class="bg-gray-50 p-8 rounded-xl shadow-lg text-center transform transition duration-300 ease-in-out hover:scale-105 hover:shadow-xl">
                    <div class="text-blue-600 mb-6">
                        <i class="fas fa-shield-alt text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Enhanced Security</h3>
                    <p class="text-gray-600 leading-relaxed">Robust security measures to protect exam integrity and student data.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="bg-blue-700 text-white py-20 px-4 sm:px-6 lg:px-8 text-center">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-3xl sm:text-4xl font-bold mb-6">Ready to Get Started?</h2>
            <p class="text-lg opacity-95 mb-8 leading-relaxed">Join the BSIT Departmental Examination System today and simplify your exam process.</p>
            <button id="ctaRegisterBtn" class="bg-white text-blue-700 hover:bg-gray-200 px-10 py-4 rounded-full text-lg font-semibold shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 hover:shadow-xl">Sign Up Now</button>
        </div>
    </section>

    <!-- Login Modal -->
    <div id="loginModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
        <div class="modal-overlay absolute inset-0 bg-gray-900 opacity-75"></div>
        <div class="bg-white rounded-lg shadow-2xl p-6 sm:p-8 w-full max-w-md mx-auto relative z-10 modal-content transform transition-all duration-300 ease-out scale-95 opacity-0">
            <button id="closeLoginModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-3xl leading-none">&times;</button>
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Sign in to your account</h2>
            <?php if (!empty($login_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($login_error); ?></span>
                </div>
            <?php endif; ?>
            <div class="form-container">
                 <form class="space-y-6" method="POST" action="index.php">
                     <input type="hidden" name="action" value="login">
                    <div>
                        <label for="login_email_or_username" class="block text-sm font-medium text-gray-700">Email (Student) or Username (Admin)</label>
                        <div class="mt-1">
                            <input id="login_email_or_username" name="email_or_username" type="text" required
                                class="appearance-none block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="login_password" class="block text-sm font-medium text-gray-700">Password</label>
                        <div class="mt-1">
                            <input id="login_password" name="password" type="password" required
                                class="appearance-none block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300">
                            Sign in
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
        <div class="modal-overlay absolute inset-0 bg-gray-900 opacity-75"></div>
        <div class="bg-white rounded-lg shadow-2xl p-6 sm:p-8 w-full max-w-md mx-auto relative z-10 modal-content transform transition-all duration-300 ease-out scale-95 opacity-0">
            <button id="closeRegisterModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-3xl leading-none">&times;</button>
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Create your account</h2>
             <?php if (!empty($register_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($register_error); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($register_success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 text-sm" role="alert">
                    <span class="block sm:inline"><?php echo $register_success; ?></span>
                </div>
            <?php endif; ?>
            <div class="form-container">
                 <form class="space-y-6" method="POST" id="registerFormActual" action="index.php">
                     <input type="hidden" name="action" value="register">
                    <div>
                        <label for="register_role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" id="register_role" onchange="toggleRegisterFields()" required
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="student" <?php echo (($_POST['role'] ?? '') == 'student') ? 'selected' : ''; ?>>Student</option>
                            <option value="admin1" <?php echo (($_POST['role'] ?? '') == 'admin1') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>

                    <div>
                        <label for="register_username" class="block text-sm font-medium text-gray-700">Username / Full Name</label>
                        <input type="text" name="username" id="register_username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="register_password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" name="password" id="register_password" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div id="registerStudentFields" class="space-y-6">
                        <div>
                            <label for="register_email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="register_email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="register_year_level" class="block text-sm font-medium text-gray-700">Year Level</label>
                            <select name="year_level" id="register_year_level"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                <option value="">Select Year Level</option>
                                <option value="1st Year" <?php echo (($_POST['year_level'] ?? '') == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2nd Year" <?php echo (($_POST['year_level'] ?? '') == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3rd Year" <?php echo (($_POST['year_level'] ?? '') == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4th Year" <?php echo (($_POST['year_level'] ?? '') == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>

                        <div>
                            <label for="register_section" class="block text-sm font-medium text-gray-700">Section</label>
                             <select name="section" id="register_section"
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
                            <label for="register_department" class="block text-sm font-medium text-gray-700">Department</label>
                             <select name="department" id="register_department"
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

                    <div>
                        <button type="submit"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300">
                            Register
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const loginModal = document.getElementById('loginModal');
        const registerModal = document.getElementById('registerModal');
        const loginBtn = document.getElementById('loginBtn');
        const registerBtn = document.getElementById('registerBtn');
        const getStartedBtn = document.getElementById('getStartedBtn');
         const ctaRegisterBtn = document.getElementById('ctaRegisterBtn');
        const closeLoginModal = document.getElementById('closeLoginModal');
        const closeRegisterModal = document.getElementById('closeRegisterModal');

        function openModal(modal) {
            modal.classList.remove('hidden');
             // Add animation classes
            const modalContent = modal.querySelector('.modal-content');
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }

        function closeModal(modal) {
             // Add animation classes
             const modalContent = modal.querySelector('.modal-content');
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');

            // Hide modal after animation
            modal.addEventListener('transitionend', function() {
                 if (modalContent.classList.contains('opacity-0')) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = ''; // Restore scrolling
                     // Clear form errors/success messages if any
                    const errorDiv = modal.querySelector('.bg-red-100');
                    const successDiv = modal.querySelector('.bg-green-100');
                    if(errorDiv) errorDiv.remove();
                    if(successDiv) successDiv.remove();
                 }
            }, { once: true });
        }

        loginBtn.addEventListener('click', () => openModal(loginModal));
        registerBtn.addEventListener('click', () => openModal(registerModal));
        getStartedBtn.addEventListener('click', () => openModal(registerModal));
         ctaRegisterBtn.addEventListener('click', () => openModal(registerModal));

        closeLoginModal.addEventListener('click', () => closeModal(loginModal));
        closeRegisterModal.addEventListener('click', () => closeModal(registerModal));

        // Close modals when clicking outside
        window.addEventListener('click', (event) => {
            if (event.target === loginModal) {
                closeModal(loginModal);
            }
            if (event.target === registerModal) {
                closeModal(registerModal);
            }
        });

        // Toggle student/admin fields in register modal
        function toggleRegisterFields() {
            const role = document.getElementById("register_role").value;
            const studentFields = document.getElementById("registerStudentFields");
            const studentInputs = studentFields.querySelectorAll("input, select");

            if (role === "student") {
                studentFields.style.display = "block";
                studentInputs.forEach(input => input.required = true);
            } else {
                studentFields.style.display = "none";
                studentInputs.forEach(input => input.required = false);
            }
        }

        // Initial check in case of PHP redirect with errors
        <?php if (!empty($login_error)): ?>
            openModal(loginModal);
        <?php endif; ?>
        <?php if (!empty($register_error) || !empty($register_success)): ?>
            openModal(registerModal);
        <?php endif; ?>
    </script>

</body>
</html> 