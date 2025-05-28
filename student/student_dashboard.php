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
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Student Dashboard - BSIT Exam System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  /* Modern Reset */
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

  /* Navbar */
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

  .nav-icons {
    display: flex;
    gap: 1.5rem;
    align-items: center;
  }

  .notifications, .profile {
    position: relative;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: background-color 0.2s;
  }

  .notifications:hover, .profile:hover {
    background-color: #f1f5f9;
  }

  .notifications svg, .profile svg {
    width: 24px;
    height: 24px;
    fill: #64748b;
  }

  .badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    padding: 0.2rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    border: 2px solid white;
  }

  .profile-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 0.5rem;
    background: white;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    border-radius: 8px;
    min-width: 200px;
    overflow: hidden;
  }

  .profile-active .profile-dropdown {
    display: block;
  }

  .profile-dropdown a {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: #475569;
    text-decoration: none;
    transition: background-color 0.2s;
  }

  .profile-dropdown a:hover {
    background: #f8fafc;
    color: #2563eb;
  }

  /* Main Content */
  main {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 2rem;
  }

  h1 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.5rem;
  }

  h2 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #334155;
    margin: 2rem 0 1rem;
  }

  /* Exam Cards */
  .exam {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
  }

  .exam:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
  }

  .exam h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.75rem;
  }

  .exam p {
    color: #64748b;
    margin-bottom: 1rem;
  }

  .exam-info {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
  }

  .exam-info small {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
    font-size: 0.875rem;
  }

  .exam-info small::before {
    content: '';
    display: inline-block;
    width: 4px;
    height: 4px;
    background: #94a3b8;
    border-radius: 50%;
  }

  .exam-info small:first-child::before {
    display: none;
  }

  /* Buttons */
  .take-exam-btn, .continue-btn, .view-result-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
  }

  .take-exam-btn {
    background: #2563eb;
    color: white;
  }

  .take-exam-btn:hover {
    background: #1d4ed8;
  }

  .continue-btn {
    background: #f59e0b;
    color: white;
  }

  .continue-btn:hover {
    background: #d97706;
  }

  .view-result-btn {
    background: #10b981;
    color: white;
  }

  .view-result-btn:hover {
    background: #059669;
  }

  /* Modal */
  .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 1000;
    backdrop-filter: blur(4px);
  }

  .modal-content {
    background: white;
    margin: 5% auto;
    padding: 2rem;
    border-radius: 16px;
    max-width: 600px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
    position: relative;
  }

  .modal h2 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1.5rem;
  }

  .close-btn {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1.5rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: none;
    color: #64748b;
    padding: 0.5rem;
    border-radius: 50%;
    transition: background-color 0.2s;
  }

  .close-btn:hover {
    background: #f1f5f9;
    color: #1e293b;
  }

  #markAllReadBtn {
    background: #2563eb;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
    margin-bottom: 1.5rem;
  }

  #markAllReadBtn:hover {
    background: #1d4ed8;
  }

  .notification-item {
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
  }

  .notification-item.unread {
    background-color: #eff6ff;
    border-left: 4px solid #2563eb;
  }

  .notification-item.read {
    background-color: #f8fafc;
  }

  .notification-item:hover {
    background-color: #f1f5f9;
  }

  .notification-item h3 {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.5rem;
  }

  .notification-item p {
    color: #64748b;
    margin-bottom: 0.5rem;
  }

  .notification-item small {
    color: #94a3b8;
    font-size: 0.875rem;
  }

  /* Responsive Design */
  @media (max-width: 768px) {
    .navbar {
      padding: 1rem;
    }

    main {
      padding: 0 1rem;
    }

    .exam-info {
      flex-direction: column;
      gap: 0.5rem;
    }

    .modal-content {
      margin: 10% 1rem;
      padding: 1.5rem;
    }
  }
</style>
</head>
<body>

<nav class="navbar">
  <div class="logo">BSIT Exam System</div>
  <div class="nav-icons">
    <div class="notifications" title="Notifications" id="notificationBell" tabindex="0" aria-label="Notifications">
      <svg viewBox="0 0 24 24" aria-hidden="true" role="img">
        <path d="M12 24c1.104 0 2-.897 2-2h-4c0 1.103.896 2 2 2zm6.707-5l1.293-1.293-1.414-1.414L17 17.586V10c0-3.07-1.64-5.64-4.5-6.32V3a1.5 1.5 0 0 0-3 0v.68C7.64 4.36 6 6.93 6 10v7.586l-1.586 1.586-1.414-1.414L5.293 19H18.707z"></path>
      </svg>
      <?php if ($unread_count > 0): ?>
        <span class="badge"><?php echo $unread_count; ?></span>
      <?php endif; ?>
    </div>
    <div class="profile" title="Profile" id="profileIcon" tabindex="0" aria-label="User Profile">
      <svg viewBox="0 0 24 24" aria-hidden="true" role="img">
        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path>
      </svg>
      <div class="profile-dropdown" id="profileDropdown">
        <a href="profile.php">Profile</a>
        <a href="../logout.php">Logout</a>
      </div>
    </div>
  </div>
</nav>

<main>
  <h1>Welcome, Student #<?php echo htmlspecialchars($student_id); ?></h1>
  <h2>Your Assigned Exams</h2>

  <?php if ($assigned_exams_result->num_rows > 0): ?>
      <?php while ($exam = $assigned_exams_result->fetch_assoc()): ?>
          <div class="exam">
              <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
              <p><?php echo nl2br(htmlspecialchars($exam['description'])); ?></p>
              <div class="exam-info">
                  <small>Due: <?php echo date('F j, Y', strtotime($exam['due_date'])); ?></small>
                  <small>Duration: <?php echo $exam['duration_minutes']; ?> minutes</small>
                  <small>Questions: <?php echo $exam['question_count']; ?></small>
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
                      echo '<a href="exam_result.php?exam_id=' . $exam['id'] . '" class="view-result-btn">View Result</a>';
                  } else {
                      // Exam started but not completed
                      echo '<a href="take_exam.php?exam_id=' . $exam['id'] . '" class="continue-btn">Continue Exam</a>';
                  }
              } else {
                  // Exam not attempted yet
                  echo '<a href="take_exam.php?exam_id=' . $exam['id'] . '" class="take-exam-btn">Take Exam</a>';
              }
              $attempt_check_stmt->close();
              ?>
          </div>
      <?php endwhile; ?>
  <?php else: ?>
      <p>No exams assigned to you yet.</p>
  <?php endif; ?>
</main>

<!-- Notification Modal -->
<div class="modal" id="notificationModal" tabindex="-1" role="dialog" aria-labelledby="notificationModalLabel" aria-hidden="true">
  <div class="modal-content">
    <button class="close-btn" id="closeModal">&times;</button>
    <h2 id="notificationModalLabel">Notifications</h2>
    <button id="markAllReadBtn">Mark all as read</button>
    <div id="notificationContent">
      <?php if (empty($all_notifications)): ?>
        <p>No notifications.</p>
      <?php else: ?>
        <?php foreach ($all_notifications as $notif): ?>
          <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" 
               data-id="<?php echo $notif['id']; ?>"
               onclick="handleNotificationClick(this)">
            <h3><?php echo htmlspecialchars($notif['exam_title']); ?></h3>
            <p><?php echo htmlspecialchars($notif['message']); ?></p>
            <small><?php echo date('F j, Y g:i A', strtotime($notif['created_at'])); ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Prepare notification exams data from PHP to JS
const notificationExams = <?php
    $notif_exams = [];
    while ($row = $notif_exams_result->fetch_assoc()) {
        $notif_exams[] = $row;
    }
    echo json_encode($notif_exams);
?>;

const notificationBell = document.getElementById('notificationBell');
const notificationModal = document.getElementById('notificationModal');
const notificationContent = document.getElementById('notificationContent');
const closeModalBtn = document.getElementById('closeModal');

// Show modal and populate notifications
notificationBell.addEventListener('click', () => {
  // The notifications are already populated in the PHP section
  notificationModal.style.display = 'block';
  notificationModal.focus();
});

// Close modal on close button
closeModalBtn.addEventListener('click', () => {
  notificationModal.style.display = 'none';
});

// Close modal on click outside modal box
window.addEventListener('click', (e) => {
  if (e.target === notificationModal) {
    notificationModal.style.display = 'none';
  }
});

// Profile dropdown toggle
const profileIcon = document.getElementById('profileIcon');
const profileDropdown = document.getElementById('profileDropdown');
profileIcon.addEventListener('click', () => {
  profileIcon.classList.toggle('profile-active');
});
window.addEventListener('click', e => {
  if (!profileIcon.contains(e.target)) {
    profileIcon.classList.remove('profile-active');
  }
});

// Handle notification clicks
function handleNotificationClick(element) {
    if (!element.classList.contains('read')) {
        const formData = new FormData();
        formData.append('notification_id', element.dataset.id);

        fetch('mark_notification_read.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the notification item's appearance
                element.classList.remove('unread');
                element.classList.add('read');
                
                // Update badge count
                const badge = document.querySelector('.badge');
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
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                item.classList.add('read');
            });
            
            // Remove the notification badge
            const badge = document.querySelector('.badge');
            if (badge) {
                badge.remove();
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
});
</script>

</body>
</html>
