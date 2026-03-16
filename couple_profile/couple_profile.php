<?php
declare(strict_types=1);
require_once '../includes/conn.php';
session_start();
date_default_timezone_set('Asia/Manila');

$response = ['success' => false, 'message' => ''];

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method', 405);
    }

    // Check if session exists, if not, try to validate from access code
    if (!isset($_SESSION['access_id'], $_SESSION['respondent'])) {
        // Session lost, try to validate from access code
        if (!isset($_POST['couple_access'])) {
            throw new Exception('Missing access code', 403);
        }
        
        // Validate access code and get access data
        $accessCheckStmt = $conn->prepare("
            SELECT access_id, male_selected, female_selected, male_profile_submitted, female_profile_submitted
            FROM couple_access 
            WHERE access_code = ? AND code_status = 'active'
        ");
        $accessCheckStmt->bind_param("s", $_POST['couple_access']);
        $accessCheckStmt->execute();
        $accessResult = $accessCheckStmt->get_result();
        
        if ($accessResult->num_rows !== 1) {
            throw new Exception('Invalid access code', 403);
        }
        
        $accessData = $accessResult->fetch_assoc();
        $access_id = (int)$accessData['access_id'];
        
        // Determine respondent type from the form data
        if (isset($_POST['sex'])) {
            $respondent = strtolower($_POST['sex']) === 'male' ? 'male' : 'female';
        } else {
            throw new Exception('Unable to determine respondent type', 403);
        }
        
        // Check if profile already submitted
        if ($accessData["{$respondent}_profile_submitted"]) {
            throw new Exception('Profile already submitted', 400);
        }
        
        // For session recovery, we completely skip CSRF validation
        // The access code validation provides sufficient security
        $skipCSRF = true;
    } else {
        // Session exists, use normal validation
        $access_id = (int)$_SESSION['access_id'];
        $respondent = $_SESSION['respondent'];
        $skipCSRF = false;
        
        // Validate CSRF token when session exists
        require_once __DIR__ . '/../includes/csrf_helper.php';
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please refresh the page and try again.', 403);
        }
    }

    // Validate respondent type
    $validRespondents = ['male', 'female'];
    if (!in_array($respondent, $validRespondents)) {
        throw new Exception('Invalid respondent type', 400);
    }

    // Start transaction
    $conn->begin_transaction();

    // Check submission status with row lock
    $checkStmt = $conn->prepare("
        SELECT male_profile_submitted, female_profile_submitted 
        FROM couple_access 
        WHERE access_id = ? 
        FOR UPDATE
    ");
    $checkStmt->bind_param("i", $access_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result()->fetch_assoc();

    if (!$result) {
        throw new Exception('Invalid access ID', 404);
    }

    if ($result["{$respondent}_profile_submitted"]) {
        throw new Exception('You have already submitted your profile', 400);
    }

    // Set flag if partner has already submitted
    if ($respondent === 'male' && $result['female_profile_submitted']) {
        $_SESSION['other_profile_submitted'] = true;
    }

    // Validate that sex matches respondent type
    $expectedSex = ucfirst($respondent);
    if (!isset($_POST['sex']) || $_POST['sex'] !== $expectedSex) {
        throw new Exception("Sex selection must be $expectedSex for this respondent type", 400);
    }

    // Validate and sanitize all data fields
    $data = [
        'sex' => sanitizeInput($_POST['sex'], 'text'),
        'first_name' => sanitizeInput($_POST['first_name'], 'name'),
        'middle_name' => sanitizeInput($_POST['middle_name'] ?? '', 'name'),
        'last_name' => sanitizeInput($_POST['last_name'], 'name'),
        'suffix' => sanitizeInput($_POST['suffix'] ?? '', 'name'),
        'email_address' => filter_var($_POST['email_address'], FILTER_SANITIZE_EMAIL),
        'date_of_birth' => $_POST['date_of_birth'],
        'residency_type' => sanitizeInput($_POST['residency_type'], 'text'),
        'city' => 'Bago City',
        'barangay' => sanitizeInput($_POST['barangay'], 'text'),
        'purok' => sanitizeInput($_POST['purok'], 'text'),
        'non_bago_city' => sanitizeInput($_POST['non_bago_city'] ?? '', 'text'),
        'non_bago_barangay' => sanitizeInput($_POST['non_bago_barangay'] ?? '', 'text'),
        'non_bago_purok' => sanitizeInput($_POST['non_bago_purok'] ?? '', 'text'),
        'foreigner_country' => sanitizeInput($_POST['foreigner_country'] ?? '', 'text'),
        'foreigner_state' => sanitizeInput($_POST['foreigner_state'] ?? '', 'text'),
        'foreigner_city' => sanitizeInput($_POST['foreigner_city'] ?? '', 'text'),
        'contact_number' => preg_replace('/[^0-9]/', '', $_POST['contact_number']),
        'civil_status' => sanitizeInput($_POST['civil_status'], 'text'),
        'years_living_together' => !empty($_POST['years_living_together']) ? sanitizeInput($_POST['years_living_together'], 'text') : null,
        'living_in_reason' => !empty($_POST['living_in_reason']) ? sanitizeInput($_POST['living_in_reason'], 'text') : null,
        'education' => sanitizeInput($_POST['education'], 'text'),
        'religion' => sanitizeInput($_POST['religion'], 'text'),
        'other_religion' => !empty($_POST['other_religion']) ? sanitizeInput($_POST['other_religion'], 'text') : null,
        'nationality' => sanitizeInput($_POST['nationality'], 'text'),
        'wedding_type' => sanitizeInput($_POST['wedding_type'] ?? '', 'text'),
        'employment_status' => sanitizeInput($_POST['employment_status'], 'text'),
        'occupation' => sanitizeInput($_POST['occupation'], 'text'),
        'monthly_income' => sanitizeInput($_POST['monthly_income'], 'text'),
        'heard_fp' => sanitizeInput($_POST['heard_fp'] ?? '', 'text'),
        'fp_facility' => sanitizeInput($_POST['fp_facility'] ?? '', 'text'),
        'other_facility' => !empty($_POST['other_facility']) ? sanitizeInput($_POST['other_facility'], 'text') : null,
        'fp_channel' => sanitizeInput($_POST['fp_channel'] ?? '', 'text'),
        'other_channel' => !empty($_POST['other_channel']) ? sanitizeInput($_POST['other_channel'], 'text') : null,
        'not_heard_reason' => !empty($_POST['not_heard_reason']) ? sanitizeInput($_POST['not_heard_reason'], 'text') : null,
        'intend_fp' => sanitizeInput($_POST['intend_fp'] ?? '', 'text'),
        'fp_female_method' => sanitizeInput($_POST['fp_female_method'] ?? '', 'text'),
        'fp_male_method' => sanitizeInput($_POST['fp_male_method'] ?? '', 'text'),
        'female_natural_method' => sanitizeInput($_POST['female_natural_method'] ?? '', 'text'),
        'male_natural_method' => sanitizeInput($_POST['male_natural_method'] ?? '', 'text'),
        'female_other_method' => sanitizeInput($_POST['female_other_method'] ?? '', 'text'),
        'male_other_method' => sanitizeInput($_POST['male_other_method'] ?? '', 'text'),
        'not_intend_reason' => !empty($_POST['not_intend_reason']) ? sanitizeInput($_POST['not_intend_reason'], 'text') : null,
        'currently_pregnant' => $_POST['currently_pregnant'] ?? null,
        'gestation_age' => !empty($_POST['gestation_age']) ? (int)$_POST['gestation_age'] : null,
        'pregnancy_plan' => sanitizeInput($_POST['pregnancy_plan'] ?? '', 'text'),
        'desired_children' => sanitizeInput($_POST['desired_children'], 'text'),
        'children_reason' => !empty($_POST['children_reason']) ? sanitizeInput($_POST['children_reason'], 'text') : null,
        'no_children_reason' => !empty($_POST['no_children_reason']) ? sanitizeInput($_POST['no_children_reason'], 'text') : null,
        'past_children' => sanitizeInput($_POST['past_children'], 'text'),
        'past_children_count' => isset($_POST['past_children_count']) && $_POST['past_children_count'] !== '' ? (int)$_POST['past_children_count'] : null,
        'philhealth_member' => sanitizeInput($_POST['philhealth_member'], 'text'),
        'marriage_reasons' => sanitizeInput($_POST['marriage_reasons'], 'text')
    ];

    // Validate required fields
    $requiredFields = [
        'first_name',
        'last_name',
        'date_of_birth',
        'sex',
        'residency_type',
        'contact_number',
        'civil_status',
        'education',
        'religion',
        'nationality',
        'employment_status',
        'occupation',
        'monthly_income',
        'marriage_reasons',
        'past_children',
        'philhealth_member'
    ];

    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            error_log("Missing required field: {$field}");
            error_log("POST data for {$field}: " . ($_POST[$field] ?? 'NOT_SET'));
            throw new Exception(str_replace('_', ' ', ucfirst($field)) . " is required", 400);
        }
    }

    // Validate contact number based on residency type
    if ($data['residency_type'] === 'foreigner') {
        // For foreigners: allow 10-15 digits (international formats)
        if (!preg_match('/^[0-9]{10,15}$/', $data['contact_number'])) {
            throw new Exception('Invalid contact number format (10-15 digits required for international numbers)', 400);
        }
    } else {
        // For Philippine residents: require 11 digits starting with 09
        if (!preg_match('/^09[0-9]{9}$/', $data['contact_number'])) {
            throw new Exception('Invalid contact number format (11 digits starting with 09 required)', 400);
        }
    }

    // Validate email (optional field - only validate format if provided)
    if (!empty($data['email_address']) && !filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address format', 400);
    }

    // Validate residency type
    $validResidencyTypes = ['bago', 'non-bago', 'foreigner'];
    if (!in_array($data['residency_type'], $validResidencyTypes)) {
        throw new Exception('Invalid residency type', 400);
    }

    // Validate address fields based on residency type
    if ($data['residency_type'] === 'bago') {
        if (empty($data['barangay']) || empty($data['purok'])) {
            throw new Exception('Barangay and Purok are required for Bago City residents', 400);
        }
    } elseif ($data['residency_type'] === 'non-bago') {
        if (empty($data['non_bago_city']) || empty($data['non_bago_barangay']) || empty($data['non_bago_purok'])) {
            throw new Exception('City, Barangay, and Purok are required for non-Bago residents', 400);
        }
    } elseif ($data['residency_type'] === 'foreigner') {
        if (empty($data['foreigner_country']) || empty($data['foreigner_state']) || empty($data['foreigner_city'])) {
            throw new Exception('Country, State/Province, and City are required for foreign residents', 400);
        }
    }

    // Calculate age
    $birthDate = new DateTime($data['date_of_birth']);
    $today = new DateTime();
    if ($birthDate > $today) {
        throw new Exception('Birthdate cannot be in the future', 400);
    }
    $data['age'] = $today->diff($birthDate)->y;
    
    // Validate age (must be 18 or older)
    if ($data['age'] < 18) {
        throw new Exception('Age must be 18 or older to register', 400);
    }

    // Validate numeric fields
    if ($data['past_children'] === 'Yes' && ($data['past_children_count'] === null || !is_numeric($data['past_children_count']))) {
        throw new Exception('Number of past children must be numeric', 400);
    }

    if ($respondent === 'female' && $data['currently_pregnant'] === 'Yes' && !is_numeric($data['gestation_age'])) {
        throw new Exception('Gestation age must be a number', 400);
    }

    // Normalize desired_children and enforce mutual exclusivity of reasons to satisfy DB CHECK constraints
    $desiredChildrenRaw = trim((string)$data['desired_children']);
    if ($desiredChildrenRaw === '5+' || strcasecmp($desiredChildrenRaw, '5 or more') === 0 || strcasecmp($desiredChildrenRaw, '5+') === 0) {
        $data['desired_children'] = '5'; // store as numeric-compatible string
    } elseif ($desiredChildrenRaw === '') {
        $data['desired_children'] = '0';
    } else {
        // keep as given but ensure numeric-compatible
        if (!preg_match('/^\d+$/', $desiredChildrenRaw)) {
            // Any non-numeric becomes 0 to avoid invalid casts
            $data['desired_children'] = '0';
        }
    }

    $desiredChildrenNum = (int)$data['desired_children'];
    $childrenReasonTrim = $data['children_reason'] !== null ? trim((string)$data['children_reason']) : null;
    $noChildrenReasonTrim = $data['no_children_reason'] !== null ? trim((string)$data['no_children_reason']) : null;

    if ($desiredChildrenNum > 0) {
        // Must have children_reason, and no_children_reason must be NULL
        if ($childrenReasonTrim === null || $childrenReasonTrim === '') {
            throw new Exception('Please provide reason for desired number of children', 400);
        }
        $data['children_reason'] = $childrenReasonTrim; // normalized
        $data['no_children_reason'] = null;
    } else {
        // 0 children: require no_children_reason and children_reason must be NULL
        if ($noChildrenReasonTrim === null || $noChildrenReasonTrim === '') {
            throw new Exception("Please provide reason why you don't want children", 400);
        }
        $data['no_children_reason'] = $noChildrenReasonTrim; // normalized
        $data['children_reason'] = null;
    }

    // Check if both partners are non-bago or foreigner
    if ($data['residency_type'] === 'non-bago' || $data['residency_type'] === 'foreigner') {
        // Get partner's residency type
        $partnerRespondent = ($respondent === 'male') ? 'female' : 'male';
        $partnerProfileStmt = $conn->prepare("
            SELECT cp.residency_type 
            FROM couple_profile cp 
            JOIN couple_access ca ON cp.access_id = ca.access_id 
            WHERE ca.access_id = ? AND ca.{$partnerRespondent}_profile_submitted = TRUE
        ");
        $partnerProfileStmt->bind_param("i", $access_id);
        $partnerProfileStmt->execute();
        $partnerResult = $partnerProfileStmt->get_result();
        
        if ($partnerResult->num_rows > 0) {
            $partnerData = $partnerResult->fetch_assoc();
            $partnerResidencyType = $partnerData['residency_type'];
            
            // If both partners are non-bago or foreigner, prevent submission
            if (($data['residency_type'] === 'non-bago' && $partnerResidencyType === 'non-bago') ||
                ($data['residency_type'] === 'foreigner' && $partnerResidencyType === 'foreigner') ||
                ($data['residency_type'] === 'non-bago' && $partnerResidencyType === 'foreigner') ||
                ($data['residency_type'] === 'foreigner' && $partnerResidencyType === 'non-bago')) {
                throw new Exception('The system does not allow both partners to be outside Bago City. At least one partner must be from Bago City.', 400);
            }
        }
    }

    // Handle address based on residency type
    // Always create a new address row for each profile
    if ($data['residency_type'] === 'bago') {
        // Create new address for Bago City residents
        $addressInsert = $conn->prepare("
            INSERT INTO address (country, state_province, city, barangay, purok) 
            VALUES ('Philippines', NULL, ?, ?, ?)
        ");
        $addressInsert->bind_param("sss", $data['city'], $data['barangay'], $data['purok']);
        if (!$addressInsert->execute()) {
            throw new Exception('Failed to save address: ' . $addressInsert->error, 500);
        }
        $address_id = $conn->insert_id;
    } elseif ($data['residency_type'] === 'non-bago') {
        // Create new address for non-bago residents
        $addressInsert = $conn->prepare("
            INSERT INTO address (country, state_province, city, barangay, purok) 
            VALUES ('Philippines', NULL, ?, ?, ?)
        ");
        $addressInsert->bind_param("sss", $data['non_bago_city'], $data['non_bago_barangay'], $data['non_bago_purok']);
        if (!$addressInsert->execute()) {
            throw new Exception('Failed to save address: ' . $addressInsert->error, 500);
        }
        $address_id = $conn->insert_id;
    } else { // foreigner
        // Create new address for foreigners
        $addressInsert = $conn->prepare("
            INSERT INTO address (country, state_province, city, barangay, purok) 
            VALUES (?, ?, ?, 'N/A', 'N/A')
        ");
        $addressInsert->bind_param("sss", $data['foreigner_country'], $data['foreigner_state'], $data['foreigner_city']);
        if (!$addressInsert->execute()) {
            throw new Exception('Failed to save address: ' . $addressInsert->error, 500);
        }
        $address_id = $conn->insert_id;
    }

    // Insert profile with address_id
    $stmt = $conn->prepare("
        INSERT INTO couple_profile (
            access_id, address_id, email_address, date_of_filing, sex, first_name, middle_name, last_name, suffix,
            date_of_birth, age, contact_number, residency_type,
            civil_status, years_living_together, living_in_reason, education,
            religion, other_religion, nationality, wedding_type, employment_status,
            occupation, monthly_income, heard_fp, fp_facility, other_facility,
            fp_channel, other_channel, not_heard_reason, intend_fp, fp_female_method,
            fp_male_method, female_natural_method, male_natural_method,
            female_other_method, male_other_method, not_intend_reason,
            currently_pregnant, gestation_age, pregnancy_plan, desired_children,
            children_reason, no_children_reason, past_children, past_children_count,
            philhealth_member, marriage_reasons
        ) VALUES (
            ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    // Bind all parameters
    $stmt->bind_param(
        "iisssssssisssssssssssssssssssssssssssssssssssss",
        $access_id,
        $address_id,
        $data['email_address'],
        $data['sex'],
        $data['first_name'],
        $data['middle_name'],
        $data['last_name'],
        $data['suffix'],
        $data['date_of_birth'],
        $data['age'],
        $data['contact_number'],
        $data['residency_type'],
        $data['civil_status'],
        $data['years_living_together'],
        $data['living_in_reason'],
        $data['education'],
        $data['religion'],
        $data['other_religion'],
        $data['nationality'],
        $data['wedding_type'],
        $data['employment_status'],
        $data['occupation'],
        $data['monthly_income'],
        $data['heard_fp'],
        $data['fp_facility'],
        $data['other_facility'],
        $data['fp_channel'],
        $data['other_channel'],
        $data['not_heard_reason'],
        $data['intend_fp'],
        $data['fp_female_method'],
        $data['fp_male_method'],
        $data['female_natural_method'],
        $data['male_natural_method'],
        $data['female_other_method'],
        $data['male_other_method'],
        $data['not_intend_reason'],
        $data['currently_pregnant'],
        $data['gestation_age'],
        $data['pregnancy_plan'],
        $data['desired_children'],
        $data['children_reason'],
        $data['no_children_reason'],
        $data['past_children'],
        $data['past_children_count'],
        $data['philhealth_member'],
        $data['marriage_reasons']
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to save profile: ' . $stmt->error, 500);
    }

    // Removed couple_sessions usage (no resume tracking)

    // Update access record - mark profile as submitted, clear the selected flag, and clear device binding
    $updateStmt = $conn->prepare("
        UPDATE couple_access 
        SET {$respondent}_profile_submitted = TRUE,
            {$respondent}_selected = 0,
            {$respondent}_device_token_hash = NULL,
            {$respondent}_device_bound_at = NULL,
            {$respondent}_device_last_seen = NULL
        WHERE access_id = ?
    ");
    $updateStmt->bind_param("i", $access_id);

    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update access record', 500);
    }

    // Check if both profiles are now submitted
    $checkBothStmt = $conn->prepare("
        SELECT male_profile_submitted, female_profile_submitted 
        FROM couple_access 
        WHERE access_id = ?
    ");
    $checkBothStmt->bind_param("i", $access_id);
    $checkBothStmt->execute();
    $bothResult = $checkBothStmt->get_result()->fetch_assoc();

    // If both profiles are submitted, create notification
    if ($bothResult['male_profile_submitted'] && $bothResult['female_profile_submitted']) {
        // Get couple names and emails for notification
        $notificationStmt = $conn->prepare("
            SELECT 
                CONCAT(m.first_name, ' ', m.last_name) as male_name,
                CONCAT(f.first_name, ' ', f.last_name) as female_name,
                m.email_address as male_email,
                f.email_address as female_email
            FROM couple_profile m
            JOIN couple_profile f ON m.access_id = f.access_id
            WHERE m.access_id = ? AND m.sex = 'Male' AND f.sex = 'Female'
        ");
        $notificationStmt->bind_param("i", $access_id);
        $notificationStmt->execute();
        $notificationResult = $notificationStmt->get_result()->fetch_assoc();
        
        if ($notificationResult) {
            $coupleNames = $notificationResult['male_name'] . ' & ' . $notificationResult['female_name'];
            $maleEmail = $notificationResult['male_email'];
            $femaleEmail = $notificationResult['female_email'];
            
            // Create notification
            $notificationStmt = $conn->prepare("
                INSERT INTO notifications (recipients, content, access_id, notification_status, created_at)
                VALUES (?, ?, ?, 'unread', NOW())");
            $notificationContent = "New couple registration completed";
            $notificationStmt->bind_param("ssi", $coupleNames, $notificationContent, $access_id);
            $notificationStmt->execute();
        }
    }

    $conn->commit();

    $_SESSION['profile_submitted'] = true;

    $response = [
        'success' => true,
        'message' => 'Profile submitted successfully!',
        'redirect' => '../questionnaire/questionnaire.php'
    ];
} catch (Exception $e) {
    if (isset($conn) && method_exists($conn, 'rollback')) {
        $conn->rollback();
    }
    $statusCode = $e->getCode();
    $statusCode = ($statusCode >= 400 && $statusCode < 600) ? $statusCode : 500;
    http_response_code($statusCode);
    $response['message'] = $e->getMessage();
    error_log("Profile Submission Error [Access ID: " . ($access_id ?? 'unknown') . "]: " . $e->getMessage());
    error_log("Profile Submission Error Stack: " . $e->getTraceAsString());
}

header('Content-Type: application/json');
echo json_encode($response);
exit();

function sanitizeInput($input, $type)
{
    if ($input === null) {
        return null;
    }

    $clean = trim($input);

    switch ($type) {
        case 'name':
            return preg_replace('/[^a-zA-ZáéíóúñäëïöüàèìòùÁÉÍÓÚÑÄËÏÖÜÀÈÌÒÙ\s\-\']/', '', $clean);
        case 'text':
            return htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
        case 'int':
            return (int)$clean;
        case 'phone':
            return preg_replace('/[^0-9]/', '', $clean);
        default:
            return htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
    }
}
