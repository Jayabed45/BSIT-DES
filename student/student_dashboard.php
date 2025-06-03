<?php
session_start();
include '../includes/db_connection.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php"); // redirect to login if not logged in
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch student info (department, section, year)
$student_sql = "SELECT department, section, year_level FROM students WHERE id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
if ($student_result->num_rows === 0) {
    die("Student info not found.");
}
$student_info = $student_result->fetch_assoc();
$department = $student_info['department'];
$section = $student_info['section'];
$year = $student_info['year_level'];
$student_stmt->close();

// Fetch exams assigned to this student explicitly
$assigned_sql = "
    SELECT e.id, e.title, e.description, e.due_date, e.duration_minutes,
           (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as question_count
    FROM exams e
    JOIN exam_assignments ea ON e.id = ea.exam_id
    WHERE ea.student_id = ?
    ORDER BY e.due_date ASC
";
$assigned_stmt = $conn->prepare($assigned_sql);
$assigned_stmt->bind_param("i", $student_id);
$assigned_stmt->execute();
$assigned_exams_result = $assigned_stmt->get_result();

// Fetch exams assigned by department, section, year for notifications
$notif_sql = "
    SELECT e.id, e.title, e.description, e.due_date, e.duration_minutes,
           (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as question_count
    FROM exams e
    JOIN exam_sends es ON e.id = es.exam_id
    WHERE (es.department = ? OR es.department = '')
      AND (es.section = ? OR es.section = '')
      AND (es.year = ? OR es.year = '')
    ORDER BY e.due_date ASC
";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("sss", $department, $section, $year);
$notif_stmt->execute();
$notif_exams_result = $notif_stmt->get_result();

// Fetch notifications for this student
$notif_sql = "
    SELECT n.*, e.title as exam_title 
    FROM notifications n 
    JOIN exams e ON n.exam_id = e.id 
    WHERE n.student_id = ? AND n.is_read = 0
    ORDER BY n.created_at DESC
";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $student_id);
$notif_stmt->execute();
$notifications_result = $notif_stmt->get_result();

// Count unread notifications
$unread_count = $notifications_result->num_rows;
$notifications = [];
while ($notif = $notifications_result->fetch_assoc()) {
    $notifications[] = $notif;
}

// Fetch all notifications for display
$all_notif_sql = "
    SELECT n.*, e.title as exam_title 
    FROM notifications n 
    JOIN exams e ON n.exam_id = e.id 
    WHERE n.student_id = ? 
    ORDER BY n.created_at DESC
";
$all_notif_stmt = $conn->prepare($all_notif_sql);
$all_notif_stmt->bind_param("i", $student_id);
$all_notif_stmt->execute();
$all_notifications = $all_notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Dashboard - BSIT Exam System</title>
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
                    <a href="student_dashboard.php" class="sidebar-item flex items-center space-x-3 text-blue-600 bg-blue-100 p-3 rounded-lg font-semibold">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="profile.php" class="sidebar-item flex items-center space-x-3 text-gray-700 hover:bg-blue-100 hover:text-blue-600 p-3 rounded-lg">
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
            <div class="container mx-auto">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6">
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 mb-4 lg:mb-0">Welcome, Student #<?php echo htmlspecialchars($student_id); ?></h1>
                    
                    <!-- Notifications and Profile Icons -->
                    <div class="flex items-center space-x-4">
                        <div class="relative" id="notificationBellContainer">
                            <i class="fas fa-bell text-gray-600 text-xl cursor-pointer" id="notificationBell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/2 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <h2 class="text-xl lg:text-2xl font-semibold text-gray-700 mb-4">Your Assigned Exams</h2>

                <?php if ($assigned_exams_result->num_rows > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
                        <?php while ($exam = $assigned_exams_result->fetch_assoc()): ?>
                            <div class="bg-white rounded-lg shadow-md p-4 lg:p-6 flex flex-col">
                                <h3 class="text-lg lg:text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($exam['title']); ?></h3>
                                <p class="text-gray-600 mb-4 flex-1"><?php echo nl2br(htmlspecialchars($exam['description'])); ?></p>
                                <div class="flex flex-wrap items-center text-gray-500 text-sm mb-4 gap-4">
                                    <span><i class="far fa-calendar-alt mr-1"></i> Due: <?php echo date('F j, Y', strtotime($exam['due_date'])); ?></span>
                                    <span><i class="far fa-clock mr-1"></i> Duration: <?php echo $exam['duration_minutes']; ?> minutes</span>
                                    <span><i class="fas fa-question-circle mr-1"></i> Questions: <?php echo $exam['question_count']; ?></span>
                                </div>

                                <?php
                                // Check if student has already attempted this exam
                                $attempt_check_sql = "SELECT * FROM exam_attempts WHERE student_id = ? AND exam_id = ?";
                                $attempt_check_stmt = $conn->prepare($attempt_check_sql);
                                $attempt_check_stmt->bind_param("ii", $student_id, $exam['id']);
                                $attempt_check_stmt->execute();
                                $attempt_check_result = $attempt_check_stmt->get_result();

                                if ($attempt_check_result->num_rows > 0) {
                                    $attempt = $attempt_check_result->fetch_assoc();
                                    if ($attempt['completed_at']) {
                                        // Exam completed, show result
                                        echo '<a href="exam_result.php?exam_id=' . $exam['id'] . '" class="inline-block bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200"><i class="fas fa-poll mr-2"></i>View Result</a>';
                                    } else {
                                        // Exam started but not completed
                                        echo '<a href="take_exam.php?exam_id=' . $exam['id'] . '" class="inline-block bg-yellow-500 text-white px-4 py-2 rounded-md hover:bg-yellow-600 transition duration-200"><i class="fas fa-play-circle mr-2"></i>Continue Exam</a>';
                                    }
                                } else {
                                    // Exam not attempted yet
                                    echo '<a href="take_exam.php?exam_id=' . $exam['id'] . '" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200"><i class="fas fa-pencil-alt mr-2"></i>Take Exam</a>';
                                }
                                $attempt_check_stmt->close();
                                ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600">No exams assigned to you yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notification Modal -->
    <div class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center z-50 hidden" id="notificationModal">
        <div class="bg-white rounded-lg shadow-xl p-4 lg:p-6 w-full max-w-md mx-4 relative">
            <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-xl" id="closeModal">&times;</button>
            <h2 class="text-xl lg:text-2xl font-bold text-gray-800 mb-4">Notifications</h2>
            <button id="markAllReadBtn" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200 mb-4"><i class="fas fa-check-double mr-2"></i>Mark all as read</button>
            <div id="notificationContent" class="space-y-3 max-h-64 overflow-y-auto">
                <?php if (empty($all_notifications)): ?>
                    <p class="text-gray-600">No notifications.</p>
                <?php else: ?>
                    <?php foreach ($all_notifications as $notif): ?>
                        <div class="notification-item p-3 rounded-md cursor-pointer transition duration-200 <?php echo $notif['is_read'] ? 'bg-gray-100' : 'bg-blue-50 border-l-4 border-blue-500'; ?>" 
                             data-id="<?php echo $notif['id']; ?>"
                             onclick="handleNotificationClick(this)">
                            <h3 class="text-base lg:text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($notif['exam_title']); ?></h3>
                            <p class="text-gray-600 text-sm mb-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                            <small class="text-gray-500 text-xs"><?php echo date('F j, Y g:i A', strtotime($notif['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Prepare notification exams data from PHP to JS (kept for potential future use if needed elsewhere)
        const notificationExams = <?php
            $notif_exams = [];
            // Reset result pointer to re-fetch for JS if needed
            if ($notif_exams_result->num_rows > 0) {
                 $notif_exams_result->data_seek(0);
                 while ($row = $notif_exams_result->fetch_assoc()) {
                     $notif_exams[] = $row;
                 }
            }
            echo json_encode($notif_exams);
        ?>;

        const notificationBell = document.getElementById('notificationBell');
        const notificationModal = document.getElementById('notificationModal');
        const closeModalBtn = document.getElementById('closeModal');

        // Show modal
        notificationBell.addEventListener('click', () => {
          notificationModal.classList.remove('hidden');
        });

        // Close modal on close button
        closeModalBtn.addEventListener('click', () => {
          notificationModal.classList.add('hidden');
        });

        // Close modal on click outside modal box
        window.addEventListener('click', (e) => {
          if (e.target === notificationModal) {
            notificationModal.classList.add('hidden');
          }
        });

        // Handle notification clicks
        function handleNotificationClick(element) {
            if (!element.classList.contains('bg-gray-100')) { // Check if not already read
                const notificationId = element.dataset.id;
                const formData = new FormData();
                formData.append('notification_id', notificationId);

                fetch('mark_notification_read.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the notification item's appearance
                        element.classList.remove('bg-blue-50', 'border-l-4', 'border-blue-500');
                        element.classList.add('bg-gray-100');
                        
                        // Update badge count
                        const badge = document.querySelector('#notificationBellContainer .bg-red-500');
                        if (badge) {
                            const currentCount = parseInt(badge.textContent);
                            if (currentCount > 1) {
                                badge.textContent = currentCount - 1;
                            } else {
                                badge.remove();
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        }

        document.getElementById('markAllReadBtn').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('mark_all', '1');

            fetch('mark_notification_read.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mark all notification items as read
                    document.querySelectorAll('#notificationContent .notification-item').forEach(item => {
                         item.classList.remove('bg-blue-50', 'border-l-4', 'border-blue-500');
                         item.classList.add('bg-gray-100');
                    });
                    
                    // Remove the notification badge
                    const badge = document.querySelector('#notificationBellContainer .bg-red-500');
                    if (badge) {
                        badge.remove();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });

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
