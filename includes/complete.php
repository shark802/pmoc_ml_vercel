<?php
session_start();
require_once '../includes/conn.php';

// Get completion status for both partners
$male_completed = 0;
$female_completed = 0;
$code_status = '';

if (isset($_SESSION['access_id']) && isset($_SESSION['respondent'])) {
    $checkStmt = $conn->prepare("
        SELECT male_questionnaire_submitted, female_questionnaire_submitted, code_status
        FROM couple_access 
        WHERE access_id = ?
    ");

    if ($checkStmt) {
        $checkStmt->bind_param("i", $_SESSION['access_id']);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $male_completed = $row['male_questionnaire_submitted'];
            $female_completed = $row['female_questionnaire_submitted'];
            $code_status = $row['code_status'];
        }
        $checkStmt->close();
    }

    // Check if current respondent has just completed
    $just_completed = false;
    if (($_SESSION['respondent'] == 'male' && $male_completed) ||
        ($_SESSION['respondent'] == 'female' && $female_completed)
    ) {
        $just_completed = true;
    }
}

// Clear residual session data
session_unset();
session_destroy();
?>

<!DOCTYPE html>
<html>

<head>
    <title>Submission Complete</title>
    <?php include '../includes/header.php'; ?>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .completion-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .success-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: bounce 1s ease-in-out;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }

        .success-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .success-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .content-section {
            padding: 2rem;
        }

        .next-steps {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 20px rgba(23, 162, 184, 0.3);
        }

        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #17a2b8;
        }

        .info-card i {
            font-size: 2.5rem;
            color: #17a2b8;
            margin-bottom: 1rem;
        }

        .info-card h6 {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .info-card p {
            color: #6c757d;
            margin: 0;
        }

        .warning-section {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 20px rgba(255, 193, 7, 0.3);
        }

        .warning-section i {
            color: #856404;
        }

        .action-section {
            text-align: center;
            padding: 2rem 0;
        }

        .btn-home {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
        }

        .btn-home:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0, 123, 255, 0.4);
            color: white;
            text-decoration: none;
        }

        .reminder-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 1rem;
            font-style: italic;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .status-complete {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        @media (max-width: 768px) {
            .completion-container {
                padding: 1rem;
            }
            
            .success-title {
                font-size: 1.5rem;
            }
            
            .info-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- scripts.php intentionally not included on this page to avoid notification polling -->
    <div class="completion-container">
        <?php if ($code_status === 'used'): ?>
            <!-- Both partners completed -->
            <div class="success-card">
                <div class="success-header">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="success-title">Congratulations!</h1>
                    <p class="success-subtitle">Both partners have successfully completed their assessments</p>
                </div>
                
                <div class="content-section">
                    <div class="status-badge status-complete">
                        <i class="fas fa-check mr-2"></i>Assessment Complete
                    </div>
                    
                    <div class="next-steps">
                        <h5><i class="fas fa-walking mr-2"></i>Next Steps:</h5>
                        <p class="mb-0">Please visit the BCPDO office to schedule your counseling session and receive your marriage readiness certificate.</p>
                    </div>
                    
                    <div class="info-cards">
                        <div class="info-card">
                            <i class="fas fa-clock"></i>
                            <h6>Office Hours</h6>
                            <p>Monday - Friday<br>8:00 AM - 5:00 PM</p>
                        </div>
                        <div class="info-card">
                            <i class="fas fa-map-marker-alt"></i>
                            <h6>Office Location</h6>
                            <p>Corner Mabini Trinidad Street<br>Barangay Poblacion, Bago City</p>
                        </div>
                        <div class="info-card">
                            <i class="fas fa-id-card"></i>
                            <h6>What to Bring</h6>
                            <p>Valid Government ID<br>and supporting documents</p>
                        </div>
                    </div>
                    
                    <div class="action-section">
                        <a href="../index.php" class="btn-home">
                            <i class="fas fa-home"></i>
                            Return to Home
                        </a>
                        <p class="reminder-text">
                            <i class="fas fa-envelope mr-1"></i>
                            You will receive email and SMS reminders after your session is scheduled.
                        </p>
                    </div>
                </div>
            </div>
                    
        <?php elseif ($just_completed): ?>
            <!-- Current partner just completed (but other hasn't) -->
            <div class="success-card">
                <div class="success-header">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="success-title">Great Job!</h1>
                    <p class="success-subtitle">Your assessment has been successfully submitted</p>
                </div>
                
                <div class="content-section">
                    <div class="status-badge status-pending">
                        <i class="fas fa-clock mr-2"></i>Waiting for Partner
                    </div>
                    
                    <div class="next-steps">
                        <h5><i class="fas fa-walking mr-2"></i>Next Steps:</h5>
                        <p class="mb-0">Schedule your counseling session once both partners complete their assessments.</p>
                    </div>
                    
                    <div class="info-cards">
                        <div class="info-card">
                            <i class="fas fa-clock"></i>
                            <h6>Office Hours</h6>
                            <p>Monday - Friday<br>8:00 AM - 5:00 PM</p>
                        </div>
                        <div class="info-card">
                            <i class="fas fa-map-marker-alt"></i>
                            <h6>Office Location</h6>
                            <p>Corner Mabini Trinidad Street<br>Barangay Poblacion, Bago City</p>
                        </div>
                    </div>
                    
                    <div class="warning-section">
                        <h6><i class="fas fa-info-circle mr-2"></i>Important Note:</h6>
                        <p class="mb-0">Your partner still needs to complete their assessment. You'll receive email and SMS reminders after both assessments are complete and a session date is set by our staff.</p>
                    </div>
                    
                    <div class="action-section">
                        <a href="../index.php" class="btn-home">
                            <i class="fas fa-home"></i>
                            Return to Home
                        </a>
                        <p class="reminder-text">
                            <i class="fas fa-heart mr-1"></i>
                            Thank you for taking the time to complete your marriage readiness assessment.
                        </p>
                    </div>
                </div>
            </div>
                    
        <?php else: ?>
            <!-- Partner hasn't completed yet -->
            <div class="success-card">
                <div class="success-header" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <div class="success-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h1 class="success-title">Submission Recorded</h1>
                    <p class="success-subtitle">Your assessment has been saved successfully</p>
                </div>
                
                <div class="content-section">
                    <div class="status-badge status-pending">
                        <i class="fas fa-hourglass-half mr-2"></i>Waiting for Partner
                    </div>
                    
                    <div class="next-steps" style="background: linear-gradient(135deg, #6c757d, #495057);">
                        <h5><i class="fas fa-users mr-2"></i>Partner Status:</h5>
                        <p class="mb-0">Your partner still needs to complete their assessment. Please wait for them to finish their questionnaire.</p>
                    </div>
                    
                    <div class="action-section">
                        <a href="../index.php" class="btn-home">
                            <i class="fas fa-home"></i>
                            Return to Home
                        </a>
                        <p class="reminder-text">
                            <i class="fas fa-bell mr-1"></i>
                            You'll be notified once both assessments are complete.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>