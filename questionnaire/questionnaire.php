<?php
session_start();
require_once '../includes/conn.php';
date_default_timezone_set('Asia/Manila');

// Validate session
if (!isset($_SESSION['access_id'], $_SESSION['respondent'])) {
    header("Location: ../index.php");
    exit();
}

$access_id = $_SESSION['access_id'];
$respondent = $_SESSION['respondent'];

    // Removed couple_sessions usage (no resume tracking)

// Database check for profile submission
try {
    $profileCheck = $conn->prepare("
        SELECT {$respondent}_profile_submitted 
        FROM couple_access 
        WHERE access_id = ?
    ");
    $profileCheck->bind_param('i', $access_id);
    $profileCheck->execute();
    $result = $profileCheck->get_result();

    if ($result->num_rows === 0) {
        header("Location: ../index.php");
        exit();
    }

    $submitted = $result->fetch_assoc()["{$respondent}_profile_submitted"];
    if (!$submitted) {
        $_SESSION['error'] = 'Complete your profile first';
        header("Location: ../couple_profile_form.php");
        exit();
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Check if questionnaire already submitted
try {
    $checkStmt = $conn->prepare("
        SELECT {$respondent}_questionnaire_submitted 
        FROM couple_access 
        WHERE access_id = ?
    ");
    $checkStmt->bind_param('i', $access_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->fetch_assoc()["{$respondent}_questionnaire_submitted"]) {
        // Questionnaire already completed, redirect to completion/next steps (walk-in scheduling)
        header("Location: ../includes/complete.php");
        exit();
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$categories = [];
try {
    // Load question categories with caching (cache for 1 hour)
    require_once __DIR__ . '/../includes/cache_helper.php';
    
    $categories = getCachedData('question_categories', function() use ($conn) {
        $stmt = $conn->prepare("
            SELECT category_id, category_name 
            FROM question_category 
            ORDER BY category_id ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }, 3600); // Cache for 1 hour
    
    $totalCategories = count($categories);
} catch (Exception $e) {
    error_log("Error loading categories in questionnaire.php: " . $e->getMessage());
    die("Error loading categories. Please refresh the page.");
}

$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$currentStep = max(1, min($currentStep, $totalCategories));
$currentCategoryId = $categories[$currentStep - 1]['category_id'];

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    // Store responses in session
    $_SESSION['responses'][$respondent][$currentCategoryId] = [
        'response' => $_POST['response'] ?? [],
        'reason' => $_POST['reason'] ?? []
    ];

    // Handle navigation
    if (isset($_POST['next'])) {
        $currentStep++;
    } elseif (isset($_POST['prev'])) {
        $currentStep--;
    } elseif (isset($_POST['submit'])) {
        // Final submission
        $conn->begin_transaction();

        try {
            // Save all responses to database
            foreach ($_SESSION['responses'][$respondent] as $category_id => $data) {
                foreach ($data['response'] as $qid => $responses) {
                    if (is_array($responses)) {
                        // Handle sub-questions
                        foreach ($responses as $sub_id => $response) {
                            $reason = $data['reason'][$qid][$sub_id] ?? '';
                            $stmt = $conn->prepare("
                                INSERT INTO couple_responses (
                                    access_id, category_id, question_id, 
                                    sub_question_id, respondent, response, reason
                                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->bind_param(
                                'iiiisss',
                                $access_id,
                                $category_id,
                                $qid,
                                $sub_id,
                                $respondent,
                                $response,
                                $reason
                            );
                            $stmt->execute();
                            $stmt->close();
                        }
                    } else {
                        // Handle main questions
                        $reason = $data['reason'][$qid] ?? '';
                        $stmt = $conn->prepare("
                            INSERT INTO couple_responses (
                                access_id, category_id, question_id, 
                                sub_question_id, respondent, response, reason
                            ) VALUES (?, ?, ?, NULL, ?, ?, ?)
                        ");
                        $stmt->bind_param(
                            'iiisss',
                            $access_id,
                            $category_id,
                            $qid,
                            $respondent,
                            $responses,
                            $reason
                        );
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            // Mark questionnaire as submitted
            $updateStmt = $conn->prepare("
                UPDATE couple_access 
                SET {$respondent}_questionnaire_submitted = 1 
                WHERE access_id = ?
            ");
            $updateStmt->bind_param('i', $access_id);
            $updateStmt->execute();

            // Check if all submissions are complete
            $statusCheck = $conn->query("
                SELECT 
                    male_profile_submitted, female_profile_submitted,
                    male_questionnaire_submitted, female_questionnaire_submitted
                FROM couple_access 
                WHERE access_id = $access_id
            ")->fetch_assoc();

            // Finalize access code if all steps completed
            if (
                $statusCheck['male_profile_submitted'] &&
                $statusCheck['female_profile_submitted'] &&
                $statusCheck['male_questionnaire_submitted'] &&
                $statusCheck['female_questionnaire_submitted']
            ) {
                $conn->query("
                    UPDATE couple_access 
                    SET code_status = 'used' 
                    WHERE access_id = $access_id
                ");
                
                // 🤖 Trigger automatic ML analysis for this couple
                require_once __DIR__ . '/../ml_model/trigger_ml_analysis.php';
                $ml_result = trigger_ml_analysis($access_id);
                if (!$ml_result) {
                    // Log failure but don't block user flow
                    error_log("ML analysis failed for access_id: $access_id - Analysis will need to be triggered manually or retried");
                    // Note: User flow continues normally, but ML analysis should be retried later
                } else {
                    error_log("ML analysis successfully triggered for access_id: $access_id");
                }
            }

            $conn->commit();

            // Clear session data
            unset(
                $_SESSION['responses'],
                $_SESSION['profile_submitted'],
                $_SESSION['questionnaire_submitted']
            );

            // Set completion flag
            $_SESSION['assessment_complete'] = true;

            // Redirect to completion/next steps (walk-in scheduling)
            header("Location: ../includes/complete.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            die("Error saving responses: " . $e->getMessage());
        }
    }

    // Redirect for prev/next navigation
    header("Location: ?step=" . max(1, min($currentStep, $totalCategories)));
    exit();
}

// Load questions for current category
try {
    $stmt = $conn->prepare("
        SELECT q.question_id, q.question_text, sq.sub_question_id, sq.sub_question_text
        FROM question_assessment q
        LEFT JOIN sub_question_assessment sq ON q.question_id = sq.question_id
        WHERE q.category_id = ?
        ORDER BY q.question_id ASC, sq.sub_question_id ASC
    ");

    $stmt->bind_param('i', $currentCategoryId);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $questions = [];
    foreach ($results as $row) {
        $qid = $row['question_id'];
        if (!isset($questions[$qid])) {
            $questions[$qid] = [
                'text' => htmlspecialchars($row['question_text'], ENT_QUOTES),
                'subs' => []
            ];
        }
        if (!empty($row['sub_question_text'])) {
            $questions[$qid]['subs'][] = [
                'id' => $row['sub_question_id'],
                'text' => htmlspecialchars($row['sub_question_text'], ENT_QUOTES)
            ];
        }
    }
    $stmt->close();
} catch (Exception $e) {
    die("Error loading questions: " . $e->getMessage());
}

$allReviewData = [];
try {
    // Build full data for review: categories, questions, sub-questions
    $stmt = $conn->prepare("
        SELECT 
            qc.category_id, qc.category_name,
            qa.question_id, qa.question_text,
            sqa.sub_question_id, sqa.sub_question_text
        FROM question_category qc
        JOIN question_assessment qa ON qa.category_id = qc.category_id
        LEFT JOIN sub_question_assessment sqa ON sqa.question_id = qa.question_id
        ORDER BY qc.category_id ASC, qa.question_id ASC, sqa.sub_question_id ASC
    ");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $cid = (int)$row['category_id'];
        if (!isset($allReviewData[$cid])) {
            $allReviewData[$cid] = [
                'category_id' => $cid,
                'category_name' => $row['category_name'],
                'questions' => []
            ];
        }
        $qid = (int)$row['question_id'];
        if (!isset($allReviewData[$cid]['questions'][$qid])) {
            $allReviewData[$cid]['questions'][$qid] = [
                'question_id' => $qid,
                'text' => htmlspecialchars($row['question_text'], ENT_QUOTES),
                'subs' => []
            ];
        }
        if (!empty($row['sub_question_id'])) {
            $allReviewData[$cid]['questions'][$qid]['subs'][] = [
                'id' => (int)$row['sub_question_id'],
                'text' => htmlspecialchars($row['sub_question_text'], ENT_QUOTES)
            ];
        }
    }
    // Reindex questions numerically for compact JSON
    foreach ($allReviewData as $cid => $cat) {
        $allReviewData[$cid]['questions'] = array_values($cat['questions']);
    }
} catch (Exception $e) {
    // Silently ignore review precompute errors
}

$conn->close();
$storedResponses = $_SESSION['responses'][$respondent][$currentCategoryId] ?? [];
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Marriage Readiness Assessment</title>
    <?php include '../includes/header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Embed review data into the page for client-side rendering
        window.questionnaireData = {
            categories: <?= json_encode(array_values($allReviewData)) ?>,
            responses: <?= json_encode($_SESSION['responses'][$respondent] ?? []) ?>,
            currentCategoryId: <?= (int)$currentCategoryId ?>
        };
        
        // Debug logging for troubleshooting
        console.log('Questionnaire Data:', {
            currentStep: <?= (int)$currentStep ?>,
            currentCategoryId: <?= (int)$currentCategoryId ?>,
            totalCategories: <?= (int)$totalCategories ?>,
            categories: <?= json_encode(array_values($allReviewData)) ?>,
            responses: <?= json_encode($_SESSION['responses'][$respondent] ?? []) ?>,
            accessId: <?= json_encode($_SESSION['access_id'] ?? '') ?>,
            respondent: <?= json_encode($respondent) ?>
        });
    </script>
    <style>
        .progress-bar {
            transition: width 0.3s ease;
        }

        .main-card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            max-width: 1000px;
            margin: 0 auto;
        }

        .category-card {
            margin-bottom: 2rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .nav-buttons {
            margin-top: 1.5rem;
        }

        .category-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        /* Remove bullet points from sub-questions */
        .sub-question td {
            padding-left: 20px !important;
        }

        .sub-question .question-cell {
            font-size: 16px;
            line-height: 1.5;
        }

        /* Ensure consistent response cell styling for all questions and sub-questions */
        .sub-question .response-cell {
            text-align: center;
            vertical-align: middle;
            padding: 12px 1px !important;
            width: 8%;
        }

        .sub-question .response-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            padding: 8px 4px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .sub-question .response-label span {
            font-weight: normal;
            font-size: 16px;
        }

        .sub-question .response-label input[type="radio"] {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
        }

        .sub-question .response-label input[type="radio"]:checked+span {
            font-weight: normal;
            color: #0d6efd;
        }

        /* Theme-specific sub-question response colors */
        .male-theme .sub-question .response-label input[type="radio"]:checked+span {
            color: #3c8dbc;
        }

        .female-theme .sub-question .response-label input[type="radio"]:checked+span {
            color: #ff6b9d;
        }

        /* Ensure consistent reason cell styling for all questions and sub-questions */
        .sub-question .reason-cell {
            padding: 12px 8px !important;
            vertical-align: middle;
            width: 35%;
        }

        .sub-question .reason-cell textarea {
            width: 100%;
            min-height: 80px;
            resize: vertical;
        }

        /* Enhanced radio button styling */
        .response-options {
            display: flex;
            justify-content: center;
            gap: 4px;
        }

        .response-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            padding: 8px 4px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .response-label span {
            font-weight: normal;
            font-size: 16px;
        }

        .response-label:hover {
            background-color: #f8f9fa;
        }

        .response-label input[type="radio"] {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
        }

        .response-label input[type="radio"]:checked+span {
            font-weight: normal;
            color: #0d6efd;
        }

        /* Theme-specific response colors */
        .male-theme .response-label input[type="radio"]:checked+span {
            color: #3c8dbc;
        }

        .female-theme .response-label input[type="radio"]:checked+span {
            color: #ff6b9d;
        }

        /* Consistent cell styling */
        .question-cell {
            padding: 12px 8px !important;
            vertical-align: middle;
            width: 45%;
            font-size: 16px;
            line-height: 1.5;
        }

        .response-cell {
            text-align: center;
            vertical-align: middle;
            padding: 12px 1px !important;
            width: 8%;
        }

        .reason-cell {
            padding: 12px 8px !important;
            vertical-align: middle;
            width: 35%;
            /* Wider comments section */
        }

        /* Make all textareas consistent */
        .reason-cell textarea {
            width: 100%;
            min-height: 80px;
            resize: vertical;
        }

        /* Ensure table text is clearly visible in light mode */
        body:not(.dark-mode) .table td,
        body:not(.dark-mode) .table th {
            color: #212529 !important;
        }

        /* Removed progress bar styles */

        /* Progress steps */
        .progress-step {
            text-align: center;
            flex: 1;
            position: relative;
        }

        /* Stepper redesign - matching the image design */
        .stepper {
            position: relative;
            width: 100%;
            padding: 0 20px;
            margin-bottom: 2rem;
        }
        .stepper::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e5e7eb; /* light gray connector */
            z-index: 0;
        }
        .stepper-inner {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }
        .step-node {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            z-index: 1; /* above the connector line */
            flex: 1;
            max-width: 25%;
        }
        .step-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            border: none; /* no border like in the image */
            background: #e5e7eb; /* light gray for future steps */
            color: #6b7280;
            transition: all 0.3s ease;
        }
        .step-node.completed .step-dot {
            background: #22c55e; /* green for completed */
            color: #ffffff;
            border: none;
        }
        .step-node.active .step-dot {
            background: #1e293b; /* dark blue/black for current */
            color: #ffffff;
            border: none;
        }
        .step-label {
            margin-top: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            line-height: 1.3;
            color: #6b7280; /* gray for future steps */
            max-width: 200px;
            word-wrap: break-word;
            text-align: center;
        }
        .step-node.active .step-label { 
            color: #3b82f6; /* blue text for current step */
        }
        .step-node.completed .step-label { 
            color: #22c55e; /* green text for completed steps */
        }


        .progress-step span {
            font-size: 0.85rem;
            font-weight: 600;
            color: #495057;
            line-height: 1.2;
            display: block;
            margin-top: 4px;
        }

        .progress-step.active span {
            color: #0d6efd;
            font-weight: 700;
        }

        .progress-step.completed span {
            color: #198754;
        }

        /* Enhanced required field validation */
        .response-group {
            position: relative;
        }

        .invalid-response-group {
            background-color: rgba(220, 53, 69, 0.1) !important;
            border: 2px solid #dc3545 !important;
            border-radius: 4px !important;
        }

        .invalid-response-group .response-label {
            color: #dc3545 !important;
        }

        .invalid-response-group .invalid-feedback {
            display: block !important;
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 5px;
        }


        /* Table styling */
        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom-width: 1px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 16px 12px;
            color: white;
        }

        /* Override Bootstrap table-secondary class */
        .male-header .table.table-bordered thead.table-secondary th,
        .female-header .table.table-bordered thead.table-secondary th {
            background-color: inherit !important;
        }

        /* Male respondent - Primary color header */
        .male-header .table thead th,
        .male-header .table.table-bordered thead th {
            background-color: #3c8dbc !important;
            color: white !important;
        }

        /* Female respondent - Pink color header */
        .female-header .table thead th,
        .female-header .table.table-bordered thead th {
            background-color: #ff6b9d !important;
            color: white !important;
        }

        .table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #e5e7eb;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .table tbody tr:last-child {
            border-bottom: none;
        }

        .table td {
            border: none;
            vertical-align: middle;
        }

        @media (max-width: 1200px) {
            .main-card {
                max-width: 900px;
            }
        }

        @media (max-width: 992px) {
            .main-card {
                max-width: 800px;
            }
        }

        @media (max-width: 768px) {
            .main-card {
                max-width: 100%;
                margin: 0 10px;
            }
            
            /* Mobile stepper adjustments */
            .stepper {
                padding: 0 10px;
                margin-bottom: 1.5rem;
            }
            
            .step-dot {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .step-label {
                font-size: 0.75rem;
                max-width: 150px;
                margin-top: 8px;
            }
            
            .stepper-inner {
                gap: 8px;
            }
            
            .response-label {
                padding: 4px;
            }

            .response-label span {
                font-size: 14px;
            }

            .sub-question .response-label span {
                font-size: 14px;
            }

            .progress-step small {
                font-size: 0.7rem;
            }

            .question-cell {
                width: 40%;
                font-size: 15px;
            }

            .sub-question .question-cell {
                font-size: 15px;
            }

            .response-cell {
                width: 12%;
            }

            .sub-question .response-cell {
                width: 12%;
            }

            .reason-cell {
                width: 30%;
            }

            .sub-question .reason-cell {
                width: 30%;
            }

            .reason-cell textarea {
                min-height: 60px;
            }

            .sub-question .reason-cell textarea {
                min-height: 60px;
            }
        }

        .bg-pink {
            background-color: #ff6b9d !important;
            /* Pink color matching couple profile form */
            color: white !important;
        }

        /* Male Theme - Blue Colors */
        .male-theme .card-header {
            background-color: #3c8dbc !important;
        }

        .male-theme .btn-primary {
            background-color: #3c8dbc;
            border-color: #3c8dbc;
        }

        .male-theme .btn-primary:hover {
            background-color: #367fa9;
            border-color: #367fa9;
        }

        /* Male Theme - Blue Colors */
        .male-theme .step-node.active .step-dot {
            background: #1e293b !important; /* dark blue/black for current step */
        }

        .male-theme .step-node.active .step-label {
            color: #3b82f6 !important; /* blue text for current step */
        }

        /* Female Theme - Pink Colors */
        .female-theme .card-header {
            background-color: #ff6b9d !important;
        }

        .female-theme .btn-primary {
            background-color: #ff6b9d;
            border-color: #ff6b9d;
        }

        .female-theme .btn-primary:hover {
            background-color: #e55d8c;
            border-color: #e55d8c;
        }

        .female-theme .step-node.active .step-dot {
            background: #1e293b !important; /* dark blue/black for current step */
        }

        .female-theme .step-node.active .step-label {
            color: #ff6b9d !important; /* pink text for current step */
        }
    </style>
</head>

<body>
    <div class="card main-card <?= $respondent == 'male' ? 'male-theme' : 'female-theme' ?>">
        <div class="card-header text-white">
            <h4 class="text-center mb-0">Readiness Assessment - <?= ucfirst($respondent) ?> Partner</h4>
        </div>
        <div class="card-body">
            
            <div class="stepper">
                <div class="stepper-inner">
                    <?php foreach ($categories as $index => $category): 
                        $nodeClasses = [];
                        if ($currentStep == $index + 1) { $nodeClasses[] = 'active'; }
                        if ($index + 1 < $currentStep) { $nodeClasses[] = 'completed'; }
                        $nodeClass = implode(' ', $nodeClasses);
                    ?>
                        <div class="step-node <?= $nodeClass ?>">
                            <div class="step-dot">
                                <?= $index + 1 ?>
                            </div>
                            <div class="step-label">
                                <?= htmlspecialchars($category['category_name'], ENT_QUOTES) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="post" id="assessmentForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="card category-card">
                    <div class="card-header text-white py-2">
                        <h4 class="category-title mb-0 text-center" style="font-size: 1.25rem; padding: 0.5rem 0;">
                            <?= htmlspecialchars($categories[$currentStep - 1]['category_name'], ENT_QUOTES) ?>
                        </h4>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive <?= $respondent == 'male' ? 'male-header' : 'female-header' ?>">
                            <table class="table table-bordered table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="question-cell">Statement</th>
                                        <th class="response-cell">Agree</th>
                                        <th class="response-cell">Neutral</th>
                                        <th class="response-cell">Disagree</th>
                                        <th class="reason-cell">Reasons/Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $qNumber = 1; ?>
                                    <?php foreach ($questions as $qid => $question): ?>
                                        <?php if (!empty($question['subs'])): ?>
                                            <tr class="bg-light">
                                                <td colspan="5" class="question-cell">
                                                    <?= $qNumber ?>. <?= $question['text'] ?>
                                                </td>
                                            </tr>
                                            <?php foreach ($question['subs'] as $sub): ?>
                                                <?php
                                                $subResponse = $storedResponses['response'][$qid][$sub['id']] ?? '';
                                                $subReason = $storedResponses['reason'][$qid][$sub['id']] ?? '';
                                                ?>
                                                <tr class="sub-question">
                                                    <td class="question-cell"><?= $sub['text'] ?></td>
                                                    <td class="response-cell response-group">
                                                        <label class="response-label">
                                                            <input type="radio" name="response[<?= $qid ?>][<?= $sub['id'] ?>]"
                                                                value="agree" <?= $subResponse === 'agree' ? 'checked' : '' ?> required>
                                                            <span>Agree</span>
                                                        </label>
                                                        <div class="invalid-feedback">Required</div>
                                                    </td>
                                                    <td class="response-cell response-group">
                                                        <label class="response-label">
                                                            <input type="radio" name="response[<?= $qid ?>][<?= $sub['id'] ?>]"
                                                                value="neutral" <?= $subResponse === 'neutral' ? 'checked' : '' ?> required>
                                                            <span>Neutral</span>
                                                        </label>
                                                        <div class="invalid-feedback">Required</div>
                                                    </td>
                                                    <td class="response-cell response-group">
                                                        <label class="response-label">
                                                            <input type="radio" name="response[<?= $qid ?>][<?= $sub['id'] ?>]"
                                                                value="disagree" <?= $subResponse === 'disagree' ? 'checked' : '' ?> required>
                                                            <span>Disagree</span>
                                                        </label>
                                                        <div class="invalid-feedback">Required</div>
                                                    </td>
                                                    <td class="reason-cell">
                                                        <textarea class="form-control"
                                                            name="reason[<?= $qid ?>][<?= $sub['id'] ?>]"
                                                            placeholder="Optional explanation"><?= htmlspecialchars($subReason, ENT_QUOTES) ?></textarea>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <?php
                                            $mainResponse = $storedResponses['response'][$qid] ?? '';
                                            $mainReason = $storedResponses['reason'][$qid] ?? '';
                                            ?>
                                            <tr>
                                                <td class="question-cell"><?= $qNumber ?>. <?= $question['text'] ?></td>
                                                <td class="response-cell response-group">
                                                    <label class="response-label">
                                                        <input type="radio" name="response[<?= $qid ?>]"
                                                            value="agree" <?= $mainResponse === 'agree' ? 'checked' : '' ?> required>
                                                        <span>Agree</span>
                                                    </label>
                                                    <div class="invalid-feedback">Required</div>
                                                </td>
                                                <td class="response-cell response-group">
                                                    <label class="response-label">
                                                        <input type="radio" name="response[<?= $qid ?>]"
                                                            value="neutral" <?= $mainResponse === 'neutral' ? 'checked' : '' ?> required>
                                                        <span>Neutral</span>
                                                    </label>
                                                    <div class="invalid-feedback">Required</div>
                                                </td>
                                                <td class="response-cell response-group">
                                                    <label class="response-label">
                                                        <input type="radio" name="response[<?= $qid ?>]"
                                                            value="disagree" <?= $mainResponse === 'disagree' ? 'checked' : '' ?> required>
                                                        <span>Disagree</span>
                                                    </label>
                                                    <div class="invalid-feedback">Required</div>
                                                </td>
                                                <td class="reason-cell">
                                                    <textarea class="form-control"
                                                        name="reason[<?= $qid ?>]"
                                                        placeholder="Optional explanation"><?= htmlspecialchars($mainReason, ENT_QUOTES) ?></textarea>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php $qNumber++; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end nav-buttons" style="gap: 1rem;">
                    <?php if ($currentStep > 1): ?>
                        <button type="submit" name="prev" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                    <?php endif; ?>

                    <?php if ($currentStep < $totalCategories): ?>
                        <button type="submit" name="next" class="btn btn-primary" id="nextButton">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    <?php else: ?>
                        <button type="submit" name="submit" class="btn btn-success" id="submitButton">
                            <i class="fas fa-check"></i> Submit All
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php include '../includes/scripts.php'; ?>
    <script>
        // Questionnaire draft recovery: auto-save and restore questionnaire inputs per access code and respondent
        (function() {
            const accessCode = <?= json_encode($_SESSION['access_id'] ?? '') ?>;
            const respondent = <?= json_encode($respondent) ?>;
            const currentStep = <?= (int)$currentStep ?>;
            const currentCategoryId = <?= (int)$currentCategoryId ?>;
            const draftKey = `questionnaireDraft:${accessCode}:${respondent}:step${currentStep}`;

            function collectFormData() {
                const data = {};
                const form = document.getElementById('assessmentForm');
                if (!form) {
                    console.log('Form not found!');
                    return data;
                }
                
                // Collect all radio buttons and textareas for current step only
                const radioButtons = form.querySelectorAll('input[type="radio"]:checked');
                const textareas = form.querySelectorAll('textarea[name]');
                
                console.log('Found', radioButtons.length, 'checked radio buttons and', textareas.length, 'textareas');
                
                radioButtons.forEach(el => {
                    const name = el.name;
                    if (!name) return;
                    data[name] = el.value;
                    console.log('Collected radio:', name, '=', el.value);
                });
                
                textareas.forEach(el => {
                    const name = el.name;
                    if (!name) return;
                    data[name] = el.value;
                    console.log('Collected textarea:', name, '=', el.value);
                });
                
                console.log('Total collected form data:', data);
                return data;
            }

            function applyDraft(draft) {
                console.log('Applying draft for step', currentStep, 'category', currentCategoryId, ':', draft);
                if (!draft) {
                    console.log('No draft to apply');
                    return;
                }
                const form = document.getElementById('assessmentForm');
                if (!form) {
                    console.log('Form not found for applying draft');
                    return;
                }
                
                // Apply radio button selections
                Object.keys(draft).forEach(name => {
                    const value = draft[name];
                    if (name.includes('reason')) {
                        // Handle textarea (reason fields)
                        const textarea = form.querySelector(`textarea[name="${name}"]`);
                        if (textarea) {
                            textarea.value = value;
                            textarea.dispatchEvent(new Event('input', { bubbles: true }));
                            console.log('Applied textarea:', name, '=', value);
                        } else {
                            console.log('Textarea not found:', name);
                        }
                    } else {
                        // Handle radio buttons
                        const radio = form.querySelector(`input[name="${name}"][value="${value}"]`);
                        if (radio) {
                            radio.checked = true;
                            radio.dispatchEvent(new Event('change', { bubbles: true }));
                            console.log('Applied radio:', name, '=', value);
                        } else {
                            console.log('Radio not found:', name, 'with value:', value);
                        }
                    }
                });
                
                // Also update the session data in the browser's questionnaireData
                if (window.questionnaireData && window.questionnaireData.responses) {
                    if (!window.questionnaireData.responses[currentCategoryId]) {
                        window.questionnaireData.responses[currentCategoryId] = { response: {}, reason: {} };
                    }
                    
                    // Convert draft data to session format
                    Object.keys(draft).forEach(name => {
                        const value = draft[name];
                        if (name.includes('reason')) {
                            // Extract question ID and sub-question ID from name like "reason[1][2]"
                            const match = name.match(/reason\[(\d+)\]\[(\d+)\]/);
                            if (match) {
                                const qid = parseInt(match[1]);
                                const subid = parseInt(match[2]);
                                if (!window.questionnaireData.responses[currentCategoryId].reason[qid]) {
                                    window.questionnaireData.responses[currentCategoryId].reason[qid] = {};
                                }
                                window.questionnaireData.responses[currentCategoryId].reason[qid][subid] = value;
                            } else {
                                // Main question reason
                                const match2 = name.match(/reason\[(\d+)\]/);
                                if (match2) {
                                    const qid = parseInt(match2[1]);
                                    window.questionnaireData.responses[currentCategoryId].reason[qid] = value;
                                }
                            }
                        } else {
                            // Extract question ID and sub-question ID from name like "response[1][2]"
                            const match = name.match(/response\[(\d+)\]\[(\d+)\]/);
                            if (match) {
                                const qid = parseInt(match[1]);
                                const subid = parseInt(match[2]);
                                if (!window.questionnaireData.responses[currentCategoryId].response[qid]) {
                                    window.questionnaireData.responses[currentCategoryId].response[qid] = {};
                                }
                                window.questionnaireData.responses[currentCategoryId].response[qid][subid] = value;
                            } else {
                                // Main question response
                                const match2 = name.match(/response\[(\d+)\]/);
                                if (match2) {
                                    const qid = parseInt(match2[1]);
                                    window.questionnaireData.responses[currentCategoryId].response[qid] = value;
                                }
                            }
                        }
                    });
                }
            }

            function saveDraft() {
                const data = collectFormData();
                console.log('Saving draft for step', currentStep, 'category', currentCategoryId, ':', data);
                console.log('Draft key:', draftKey);
                try { 
                    localStorage.setItem(draftKey, JSON.stringify(data)); 
                    console.log('Draft saved successfully');
                } catch (e) {
                    console.error('Error saving draft:', e);
                }
            }

            function loadDraft() {
                console.log('Loading draft for step', currentStep, 'category', currentCategoryId);
                console.log('Draft key:', draftKey);
                try {
                    const raw = localStorage.getItem(draftKey);
                    console.log('Raw draft data:', raw);
                    if (!raw) {
                        console.log('No draft found, trying session data fallback');
                        // Try to restore from session data as fallback
                        restoreFromSessionData();
                        return;
                    }
                    const draft = JSON.parse(raw);
                    console.log('Parsed draft data:', draft);
                    applyDraft(draft);
                    console.log('Draft applied successfully');
                } catch (e) {
                    console.error('Error loading draft:', e);
                    // Try to restore from session data as fallback
                    restoreFromSessionData();
                }
            }
            
            function restoreFromSessionData() {
                console.log('Attempting to restore from session data...');
                console.log('window.questionnaireData:', window.questionnaireData);
                console.log('currentCategoryId:', currentCategoryId);
                
                if (window.questionnaireData && window.questionnaireData.responses && window.questionnaireData.responses[currentCategoryId]) {
                    const sessionData = window.questionnaireData.responses[currentCategoryId];
                    console.log('Found session data to restore:', sessionData);
                    
                    const form = document.getElementById('assessmentForm');
                    if (!form) {
                        console.log('Form not found for session data restoration');
                        return;
                    }
                    
                    // Convert session data to form format and apply
                    const responses = sessionData.response || {};
                    const reasons = sessionData.reason || {};
                    
                    console.log('Session responses:', responses);
                    console.log('Session reasons:', reasons);
                    
                    // Apply main question responses
                    Object.keys(responses).forEach(qid => {
                        const response = responses[qid];
                        console.log('Processing response for question', qid, ':', response);
                        if (typeof response === 'string') {
                            // Main question response
                            const radio = form.querySelector(`input[name="response[${qid}]"][value="${response}"]`);
                            if (radio) {
                                radio.checked = true;
                                console.log('Applied main question response:', qid, '=', response);
                            } else {
                                console.log('Radio not found for main question:', qid, 'value:', response);
                            }
                        } else if (typeof response === 'object' && response !== null) {
                            // Sub-question responses
                            Object.keys(response).forEach(subid => {
                                const subResponse = response[subid];
                                const radio = form.querySelector(`input[name="response[${qid}][${subid}]"][value="${subResponse}"]`);
                                if (radio) {
                                    radio.checked = true;
                                    console.log('Applied sub-question response:', qid, subid, '=', subResponse);
                                } else {
                                    console.log('Radio not found for sub-question:', qid, subid, 'value:', subResponse);
                                }
                            });
                        }
                    });
                    
                    // Apply reasons
                    Object.keys(reasons).forEach(qid => {
                        const reason = reasons[qid];
                        if (typeof reason === 'string') {
                            // Main question reason
                            const textarea = form.querySelector(`textarea[name="reason[${qid}]"]`);
                            if (textarea) {
                                textarea.value = reason;
                            }
                        } else if (typeof reason === 'object') {
                            // Sub-question reasons
                            Object.keys(reason).forEach(subid => {
                                const subReason = reason[subid];
                                const textarea = form.querySelector(`textarea[name="reason[${qid}][${subid}]"]`);
                                if (textarea) {
                                    textarea.value = subReason;
                                }
                            });
                        }
                    });
                } else {
                    console.log('No session data found to restore from');
                }
            }

            function clearDraft() {
                try { 
                    localStorage.removeItem(draftKey); 
                } catch (e) {}
            }

            // Auto-save on any form change
            let saveTimer;
            const form = document.getElementById('assessmentForm');
            if (form) {
                form.addEventListener('change', function(e) {
                    console.log('Form change detected:', e.target.name, e.target.value);
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(() => {
                        saveDraft();
                        saveCurrentFormToSession();
                    }, 500);
                });
                form.addEventListener('input', function(e) {
                    console.log('Form input detected:', e.target.name, e.target.value);
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(() => {
                        saveDraft();
                        saveCurrentFormToSession();
                    }, 500);
                });
            } else {
                console.error('Assessment form not found!');
            }

            // Clear draft on successful submission
            const submitButton = document.querySelector('button[name="submit"]');
            if (submitButton) {
                submitButton.addEventListener('click', function() {
                    clearDraft();
                });
            }

            // Load draft on page load with a small delay to ensure DOM is ready
            setTimeout(() => {
                loadDraft();
            }, 200);
            
            // Function to save current form data to session
            function saveCurrentFormToSession() {
                const formData = collectFormData();
                
                // Update the questionnaireData.responses with current form data
                if (window.questionnaireData && window.questionnaireData.responses) {
                    if (!window.questionnaireData.responses[currentCategoryId]) {
                        window.questionnaireData.responses[currentCategoryId] = { response: {}, reason: {} };
                    }
                    
                    // Convert form data to session format
                    Object.keys(formData).forEach(name => {
                        const value = formData[name];
                        if (name.includes('reason')) {
                            // Extract question ID and sub-question ID from name like "reason[1][2]"
                            const match = name.match(/reason\[(\d+)\]\[(\d+)\]/);
                            if (match) {
                                const qid = parseInt(match[1]);
                                const subid = parseInt(match[2]);
                                if (!window.questionnaireData.responses[currentCategoryId].reason[qid]) {
                                    window.questionnaireData.responses[currentCategoryId].reason[qid] = {};
                                }
                                window.questionnaireData.responses[currentCategoryId].reason[qid][subid] = value;
                            } else {
                                // Main question reason
                                const match2 = name.match(/reason\[(\d+)\]/);
                                if (match2) {
                                    const qid = parseInt(match2[1]);
                                    window.questionnaireData.responses[currentCategoryId].reason[qid] = value;
                                }
                            }
                        } else {
                            // Extract question ID and sub-question ID from name like "response[1][2]"
                            const match = name.match(/response\[(\d+)\]\[(\d+)\]/);
                            if (match) {
                                const qid = parseInt(match[1]);
                                const subid = parseInt(match[2]);
                                if (!window.questionnaireData.responses[currentCategoryId].response[qid]) {
                                    window.questionnaireData.responses[currentCategoryId].response[qid] = {};
                                }
                                window.questionnaireData.responses[currentCategoryId].response[qid][subid] = value;
                            } else {
                                // Main question response
                                const match2 = name.match(/response\[(\d+)\]/);
                                if (match2) {
                                    const qid = parseInt(match2[1]);
                                    window.questionnaireData.responses[currentCategoryId].response[qid] = value;
                                }
                            }
                        }
                    });
                }
            }
            
            // Make functions available globally for debugging
            window.debugQuestionnaire = {
                saveDraft: saveDraft,
                loadDraft: loadDraft,
                collectFormData: collectFormData,
                applyDraft: applyDraft,
                restoreFromSessionData: restoreFromSessionData,
                saveCurrentFormToSession: saveCurrentFormToSession,
                currentStep: currentStep,
                currentCategoryId: currentCategoryId,
                draftKey: draftKey,
                testSave: function() {
                    console.log('=== MANUAL SAVE TEST ===');
                    const data = collectFormData();
                    console.log('Collected data:', data);
                    saveDraft();
                    console.log('=== END SAVE TEST ===');
                },
                testLoad: function() {
                    console.log('=== MANUAL LOAD TEST ===');
                    loadDraft();
                    console.log('=== END LOAD TEST ===');
                },
                checkLocalStorage: function() {
                    console.log('=== LOCALSTORAGE CHECK ===');
                    console.log('Draft key:', draftKey);
                    const raw = localStorage.getItem(draftKey);
                    console.log('Raw data:', raw);
                    if (raw) {
                        try {
                            const parsed = JSON.parse(raw);
                            console.log('Parsed data:', parsed);
                        } catch (e) {
                            console.error('Parse error:', e);
                        }
                    }
                    console.log('=== END LOCALSTORAGE CHECK ===');
                }
            };


        })();

        document.addEventListener('DOMContentLoaded', function() {
            // Form elements
            const form = document.getElementById('assessmentForm');
            const prevButton = document.querySelector('button[name="prev"]');
            const nextButton = document.querySelector('button[name="next"]');
            const submitButton = document.querySelector('button[name="submit"]');
            const currentStep = <?= (int)$currentStep ?>;
            const totalCategories = <?= (int)$totalCategories ?>;

            // Function to validate form
            function validateForm() {
                let isValid = true;
                let firstErrorElement = null;

                // Reset all error states
                document.querySelectorAll('.response-group').forEach(group => {
                    group.classList.remove('invalid-response-group');
                });

                // Validate all required radio buttons
                document.querySelectorAll('input[type="radio"][required]').forEach(radio => {
                    const name = radio.name;
                    const checked = document.querySelector(`input[name="${name}"]:checked`);

                    if (!checked) {
                        isValid = false;
                        document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                            const group = r.closest('.response-group');
                            group.classList.add('invalid-response-group');

                            if (!firstErrorElement) {
                                firstErrorElement = group;
                            }
                        });
                    }
                });

                // Scroll to first error if exists
                if (!isValid && firstErrorElement) {
                    firstErrorElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }

                return isValid;
            }

            // Clear error state when a radio is selected
            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const name = this.name;
                    document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                        r.closest('.response-group').classList.remove('invalid-response-group');
                    });
                });
            });

            // Handle Previous button
            if (prevButton) {
                prevButton.addEventListener('click', function() {
                    // Save current form data to session before navigation
                    if (window.debugQuestionnaire && window.debugQuestionnaire.saveCurrentFormToSession) {
                        window.debugQuestionnaire.saveCurrentFormToSession();
                    }
                    
                    // Temporarily remove required attributes
                    document.querySelectorAll('input[type="radio"][required]').forEach(radio => {
                        radio.removeAttribute('required');
                    });
                });
            }

            // Handle Next button
            if (nextButton) {
                nextButton.addEventListener('click', function(e) {
                    if (!validateForm()) {
                        e.preventDefault();
                        return;
                    }
                    
                    // Save current form data to session before navigation
                    if (window.debugQuestionnaire && window.debugQuestionnaire.saveCurrentFormToSession) {
                        window.debugQuestionnaire.saveCurrentFormToSession();
                    }
                });
            }

            // Build a full review: all categories and questions (answered and unanswered)
            function buildReviewHtml() {
                const data = window.questionnaireData || { categories: [], responses: {} };
                const categories = data.categories || [];
                const responsesByCategory = data.responses || {};

                // Function to get badge class for response
                function getBadgeClass(response) {
                    switch(response.toLowerCase()) {
                        case 'agree': return 'badge bg-success';
                        case 'neutral': return 'badge bg-warning text-dark';
                        case 'disagree': return 'badge bg-danger';
                        default: return 'badge bg-secondary';
                    }
                }

                // Function to format response as badge
                function formatResponse(response) {
                    if (!response) return '<em>—</em>';
                    const badgeClass = getBadgeClass(response);
                    return `<span class="${badgeClass}">${response}</span>`;
                }

                const sectionHtml = categories.map(cat => {
                    let number = 1; // per-category numbering
                    let qHtml = '';
                    (cat.questions || []).forEach(q => {
                        if (q.subs && q.subs.length) {
                            // Add a numbered parent row (no answer) so counting matches parent questions
                            qHtml += `
                                <div style="padding:8px 0;border-bottom:1px dashed #e5e7eb">
                                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                                        <div style="max-width:60%;color:#374151">${number}. ${q.text}</div>
                                        <div style="font-weight:600;color:#6b7280"><em>—</em></div>
                                    </div>
                                </div>`;
                            number++;
                            // Then list unnumbered sub-questions
                            q.subs.forEach(sub => {
                                const r = (responsesByCategory[cat.category_id]?.response?.[q.question_id]?.[sub.id]) || '';
                                const reason = (responsesByCategory[cat.category_id]?.reason?.[q.question_id]?.[sub.id]) || '';
                                const safeText = (sub.text || '').toString();
                                const safeAns = (r || '').toString();
                                const safeReason = (reason || '').toString();
                                qHtml += `
                                    <div style="padding:8px 0;border-bottom:1px dashed #e5e7eb">
                                        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
                                            <div style="max-width:60%;color:#374151">${safeText}</div>
                                            <div style="font-weight:600">${formatResponse(safeAns)}</div>
                                        </div>
                                        ${safeReason ? `<div style=\"color:#6b7280;font-size:12px;margin-top:4px\"><strong>Reason:</strong> ${safeReason}</div>` : ''}
                                    </div>`;
                            });
                        } else {
                            const r = (responsesByCategory[cat.category_id]?.response?.[q.question_id]) || '';
                            const reason = (responsesByCategory[cat.category_id]?.reason?.[q.question_id]) || '';
                            const safeAns = (r || '').toString();
                            const safeReason = (reason || '').toString();
                            qHtml += `
                                <div style="padding:8px 0;border-bottom:1px dashed #e5e7eb">
                                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
                                        <div style="max-width:60%;color:#374151">${number}. ${q.text}</div>
                                        <div style="font-weight:600">${formatResponse(safeAns)}</div>
                                    </div>
                                    ${safeReason ? `<div style=\"color:#6b7280;font-size:12px;margin-top:4px\"><strong>Reason:</strong> ${safeReason}</div>` : ''}
                                </div>`;
                            number++;
                        }
                    });
                    return `
                        <div style="margin-bottom:18px">
                            <div style="font-size:20px;font-weight:900;color:#0f172a;margin-bottom:10px">${cat.category_name}</div>
                            <div>${qHtml || '<div style=\"color:#9ca3af\">No questions</div>'}</div>
                        </div>`;
                }).join('');

                return `<div style=\"text-align:left;max-height:70vh;overflow:auto;padding-right:6px\">${sectionHtml || '<em>No data</em>'}</div>`;
            }

            // Handle Submit button with review modal
            if (submitButton) {
                submitButton.addEventListener('click', function(e) {
                    if (!validateForm()) {
                        e.preventDefault();
                        return;
                    }
                    e.preventDefault();
                    
                    // Save current form data to session before showing review
                    if (window.debugQuestionnaire && window.debugQuestionnaire.saveCurrentFormToSession) {
                        window.debugQuestionnaire.saveCurrentFormToSession();
                    }
                    
                    const reviewHtml = buildReviewHtml();
                    Swal.fire({
                        title: 'Review your answers',
                        html: reviewHtml,
                        icon: undefined,
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Submit All',
                        cancelButtonText: 'Edit',
                        reverseButtons: true,
                        width: 1000,
                        didOpen: () => {
                            const icon = document.querySelector('.swal2-icon');
                            if (icon) icon.style.display = 'none';
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Interactive loading animation similar to profile
                            let progress = 0;
                            let dots = 0;
                            Swal.fire({
                                title: 'Finalizing your assessment',
                                html: `
                                    <div style="text-align:left">
                                        <div id="qStatus" style="font-size:14px;color:#6b7280;margin-bottom:8px;">Submitting<span id="qdotz"></span></div>
                                        <div style="height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden">
                                            <div id="qBar" style="height:100%;width:0%;background:linear-gradient(90deg,#60a5fa,#2563eb);transition:width .2s"></div>
                                        </div>
                                        <div style="display:flex;gap:8px;margin-top:10px;color:#6b7280;font-size:12px">
                                            <span id="qs1" style="opacity:.6">✓ Validating</span>
                                            <span id="qs2" style="opacity:.6">• Saving</span>
                                            <span id="qs3" style="opacity:.6">• Completing</span>
                                        </div>
                                    </div>
                                `,
                                allowOutsideClick: false,
                                showConfirmButton: false,
                                willOpen: () => {
                                    const bar = document.getElementById('qBar');
                                    const dotz = document.getElementById('qdotz');
                                    const status = document.getElementById('qStatus');
                                    const s1 = document.getElementById('qs1');
                                    const s2 = document.getElementById('qs2');
                                    const s3 = document.getElementById('qs3');
                                    const interval = setInterval(() => {
                                        progress = Math.min(100, progress + Math.floor(Math.random() * 12) + 5);
                                        if (bar) bar.style.width = progress + '%';
                                        dots = (dots + 1) % 4;
                                        if (dotz) dotz.textContent = '.'.repeat(dots);
                                        if (progress > 30) { s1.style.opacity = '1'; }
                                        if (progress > 60) { s2.style.opacity = '1'; status.textContent = 'Saving'; }
                                        if (progress >= 95) { s3.style.opacity = '1'; status.textContent = 'Completing'; }
                                        if (progress >= 100) {
                                            clearInterval(interval);
                                            // Proceed with real submit
                                            const hidden = document.createElement('input');
                                            hidden.type = 'hidden';
                                            hidden.name = 'submit';
                                            hidden.value = '1';
                                            form.appendChild(hidden);
                                            // Avoid name collision with input[name="submit"] shadowing the submit() method
                                            HTMLFormElement.prototype.submit.call(form);
                                        }
                                    }, 180);
                                }
                            });
                        }
                    });
                });
            }

            // Make response cells clickable
            document.querySelectorAll('.response-cell').forEach(cell => {
                cell.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                        const radio = this.querySelector('input[type="radio"]');
                        if (radio) {
                            radio.checked = true;
                            radio.dispatchEvent(new Event('change'));
                            
                            // Immediately save draft and session data
                            setTimeout(() => {
                                console.log('Immediate save triggered from cell click');
                                if (window.debugQuestionnaire) {
                                    window.debugQuestionnaire.saveDraft();
                                    window.debugQuestionnaire.saveCurrentFormToSession();
                                }
                            }, 50);
                        }
                    }
                });
            });
            
            // Also add direct event listeners to radio buttons for immediate saving
            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    console.log('Radio button changed:', this.name, this.value);
                    // Immediate save without timeout
                    setTimeout(() => {
                        console.log('Immediate save triggered from radio change');
                        if (window.debugQuestionnaire) {
                            window.debugQuestionnaire.saveDraft();
                            window.debugQuestionnaire.saveCurrentFormToSession();
                        }
                    }, 10);
                });
            });

            // Function to check if a step is completed
            function isStepCompleted(stepNumber) {
                if (stepNumber > currentStep) return false; // Future steps are not completed
                
                // For the current step, validate the form
                if (stepNumber === currentStep) {
                    return validateForm();
                }
                
                // For previous steps, check if they have responses in session
                const responses = window.questionnaireData?.responses || {};
                const categoryId = window.questionnaireData?.categories?.[stepNumber - 1]?.category_id;
                
                if (!categoryId || !responses[categoryId]) return false;
                
                const stepResponses = responses[categoryId].response || {};
                // Check if all questions in this step have responses
                const questions = window.questionnaireData?.categories?.[stepNumber - 1]?.questions || [];
                
                for (let question of questions) {
                    if (question.subs && question.subs.length > 0) {
                        // Check sub-questions
                        for (let sub of question.subs) {
                            if (!stepResponses[question.question_id] || !stepResponses[question.question_id][sub.id]) {
                                return false;
                            }
                        }
                    } else {
                        // Check main question
                        if (!stepResponses[question.question_id]) {
                            return false;
                        }
                    }
                }
                return true;
            }

            // Function to validate current form without showing errors
            function validateFormSilently() {
                let isValid = true;
                
                // Check all required radio buttons in current form
                document.querySelectorAll('input[type="radio"][required]').forEach(radio => {
                    const name = radio.name;
                    const checked = document.querySelector(`input[name="${name}"]:checked`);
                    
                    if (!checked) {
                        isValid = false;
                    }
                });
                
                return isValid;
            }

            // Steps are now display-only indicators, no click navigation
        });
    </script>
</body>

</html>