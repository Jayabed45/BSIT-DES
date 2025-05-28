<?php
session_start();
include '../includes/db_connection.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: ../login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Fetch exam details and student's attempt
$stmt = $conn->prepare("
    SELECT e.*, ea.score, ea.started_at, ea.completed_at,
           (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as total_questions
    FROM exams e
    JOIN exam_attempts ea ON e.id = ea.exam_id
    WHERE e.id = ? AND ea.student_id = ?
");
$stmt->bind_param("ii", $exam_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Exam result not found.");
}

$exam = $result->fetch_assoc();
$score_percentage = ($exam['score'] / $exam['total_questions']) * 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Result - BSIT Exam System</title>
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

        .result-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .result-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .result-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .result-header p {
            color: #64748b;
        }

        .score-circle {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background: conic-gradient(
                #2563eb 0% <?php echo $score_percentage; ?>%,
                #e2e8f0 <?php echo $score_percentage; ?>% 100%
            );
        }

        .score-circle::before {
            content: '';
            position: absolute;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: white;
        }

        .score-text {
            position: relative;
            z-index: 1;
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .score-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .detail-item {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
        }

        .detail-item h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .detail-item p {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .exam-info {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .exam-info h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .exam-info p {
            color: #64748b;
            margin-bottom: 1rem;
        }

        .exam-info p:last-child {
            margin-bottom: 0;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
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
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #1e293b;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .result-card {
                padding: 1.5rem;
            }

            .score-circle {
                width: 150px;
                height: 150px;
            }

            .score-circle::before {
                width: 120px;
                height: 120px;
            }

            .score-text {
                font-size: 2rem;
            }

            .action-buttons {
                flex-direction: column;
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
        <div class="result-card">
            <div class="result-header">
                <h1>Exam Result</h1>
                <p><?php echo htmlspecialchars($exam['title']); ?></p>
            </div>

            <div class="score-circle">
                <div class="score-text"><?php echo round($score_percentage); ?>%</div>
            </div>

            <div class="score-details">
                <div class="detail-item">
                    <h3>Score</h3>
                    <p><?php echo $exam['score']; ?>/<?php echo $exam['total_questions']; ?></p>
                </div>
                <div class="detail-item">
                    <h3>Duration</h3>
                    <p><?php echo $exam['duration_minutes']; ?> minutes</p>
                </div>
                <div class="detail-item">
                    <h3>Time Taken</h3>
                    <p><?php 
                        $start = new DateTime($exam['started_at']);
                        $end = new DateTime($exam['completed_at']);
                        $diff = $start->diff($end);
                        echo $diff->format('%H:%I:%S');
                    ?></p>
                </div>
            </div>

            <div class="exam-info">
                <h2>Exam Details</h2>
                <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($exam['description'])); ?></p>
                <p><strong>Started:</strong> <?php echo date('F j, Y g:i A', strtotime($exam['started_at'])); ?></p>
                <p><strong>Completed:</strong> <?php echo date('F j, Y g:i A', strtotime($exam['completed_at'])); ?></p>
            </div>

            <div class="action-buttons">
                <a href="student_dashboard.php" class="btn btn-secondary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html> 