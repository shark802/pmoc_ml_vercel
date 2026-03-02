<?php
session_start();
require_once 'includes/conn.php';

header('Content-Type: application/json');

// Device-bound helpers
function getDeviceTokenFromCookie(): ?string {
	return isset($_COOKIE['partner_device_token']) && is_string($_COOKIE['partner_device_token'])
		? $_COOKIE['partner_device_token']
		: null;
}

function issueDeviceTokenCookie(string $token): void {
	$expires = time() + (60 * 60 * 24 * 180); // 6 months
	setcookie('partner_device_token', $token, [
		'expires' => $expires,
		'path' => '/',
		'secure' => true,
		'httponly' => true,
		'samesite' => 'Strict'
	]);
}

function generateDeviceToken(): string {
	return bin2hex(random_bytes(32));
}

// Function to convert alphanumeric sequence to numeric for check digit calculation
function sequenceToNumeric($sequence) {
    // If sequence is numeric (001-999), return as-is
    if (preg_match('/^\d{3}$/', $sequence)) {
        return $sequence;
    }
    // If sequence is alphanumeric (A00-Z99), convert to numeric
    // Format: Letter (A-Z = 10-35) + 2 digits (00-99)
    if (preg_match('/^([A-Z])(\d{2})$/', $sequence, $matches)) {
        $letter = $matches[1];
        $digits = $matches[2];
        $letterValue = ord($letter) - 55; // A=10, B=11, ..., Z=35
        return str_pad($letterValue, 2, '0', STR_PAD_LEFT) . $digits;
    }
    return $sequence; // Fallback
}

// Function to validate check digit for access codes
function validateCheckDigit($accessCode) {
    // Extract the parts: BCPDO-YYYYMMDD-SSS-X
    // Supports both numeric (001-999) and alphanumeric (A00-Z99) sequences
    if (!preg_match('/^BCPDO-(\d{8})-(\d{3}|[A-Z]\d{2})-([A-Z0-9])$/', $accessCode, $matches)) {
        return false;
    }
    
    $date = $matches[1];
    $sequence = $matches[2];
    $providedCheckDigit = $matches[3];
    
    // Convert sequence to numeric format for calculation
    $numericSequence = sequenceToNumeric($sequence);
    
    // Generate expected check digit
    $input = $date . $numericSequence;
    $sum = 0;
    $weights = [3, 1, 3, 1, 3, 1, 3, 1, 3, 1, 3]; // Weights for 11 digits
    
    // Convert input to array of digits
    $digits = str_split($input);
    
    // Apply weights and sum
    for ($i = 0; $i < count($digits) && $i < count($weights); $i++) {
        $sum += intval($digits[$i]) * $weights[$i];
    }
    
    // Generate expected check digit
    $expectedCheckDigit = $sum % 36;
    if ($expectedCheckDigit < 10) {
        $expectedCheckDigit = strval($expectedCheckDigit);
    } else {
        $expectedCheckDigit = chr(65 + ($expectedCheckDigit - 10)); // A-Z
    }
    
    return $providedCheckDigit === $expectedCheckDigit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Sanitize and validate inputs
$accessCode = trim($_POST['access_code'] ?? '');
$respondentType = trim($_POST['respondent_type'] ?? '');

// Debug logging
error_log("Access code received: '" . $accessCode . "' (length: " . strlen($accessCode) . ")");
error_log("Respondent type: '" . $respondentType . "'");

if (empty($accessCode)) {
    echo json_encode(['success' => false, 'message' => 'Please enter your access code']);
    exit();
}

// Validate access code format and check digit
if (!validateCheckDigit($accessCode)) {
    echo json_encode(['success' => false, 'message' => 'Invalid access code format or check digit. Please check your code and try again.']);
    exit();
}

if (empty($respondentType) || !in_array($respondentType, ['male', 'female'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid respondent selection']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Lock the access code row for atomic operations
    $stmt = $conn->prepare("
        SELECT 
            access_id, 
            code_status, 
            male_profile_submitted, 
            female_profile_submitted,
            IFNULL(male_selected, 0) AS male_selected,
            IFNULL(female_selected, 0) AS female_selected,
            male_device_token_hash,
            female_device_token_hash,
            male_selected_time,
            female_selected_time
        FROM couple_access 
        WHERE access_code = ?
        FOR UPDATE
    ");
    $stmt->bind_param("s", $accessCode);
    $stmt->execute();
    $result = $stmt->get_result();

    // Validate access code exists
    if ($result->num_rows !== 1) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Invalid access code. Please check your code and try again.']);
        exit();
    }

    $accessData = $result->fetch_assoc();

    // Check code status
    if ($accessData['code_status'] !== 'active') {
        $conn->rollback();
        if ($accessData['code_status'] === 'expired') {
            echo json_encode(['success' => false, 'message' => 'This access code has expired. Please contact the administrator for a new code.']);
        } elseif ($accessData['code_status'] === 'used') {
            echo json_encode(['success' => false, 'message' => 'This access code has already been fully used.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'This access code is no longer valid']);
        }
        exit();
    }

    // Check if profile already submitted
    if ($accessData["{$respondentType}_profile_submitted"]) {
        // Profile already completed, set session and redirect to questionnaire
        $_SESSION['access_id'] = $accessData['access_id'];
        $_SESSION['access_code'] = $accessCode;
        $_SESSION['respondent'] = $respondentType;
        
        $conn->rollback();
        echo json_encode([
            'success' => true,
            'redirect' => 'questionnaire/questionnaire.php',
            'message' => ucfirst($respondentType) . ' partner profile already completed. Redirecting to questionnaire.'
        ]);
        exit();
    }

    // Device-bound enforcement
    $deviceToken = getDeviceTokenFromCookie();
    $partnerDeviceHashCol = $respondentType === 'male' ? 'male_device_token_hash' : 'female_device_token_hash';
    $partnerSelectedCol = $respondentType === 'male' ? 'male_selected' : 'female_selected';

    if (!empty($accessData[$partnerDeviceHashCol])) {
    	$storedHash = $accessData[$partnerDeviceHashCol];
    	$deviceOk = ($deviceToken && password_verify($deviceToken, $storedHash));
    	if (!$deviceOk) {
    		$conn->rollback();
    		echo json_encode([
    			'success' => false,
    			'message' => ucfirst($respondentType) . ' partner lane is bound to another device. Please resume on the same device or contact the administrator to reset.'
    		]);
    		exit();
    	}
    } else {
    	// No device bound yet; if legacy selected lock exists and is fresh, still block until it expires
    	if ((int)$accessData[$partnerSelectedCol] === 1) {
    		$flagCheckStmt = $conn->prepare("
                SELECT 
                    CASE 
                        WHEN {$respondentType}_selected = 1 AND 
                             TIMESTAMPDIFF(MINUTE, 
                                 COALESCE({$respondentType}_selected_time, NOW()), 
                                 NOW()) > 30 
                        THEN 1 
                        ELSE 0 
                    END as should_clear_flag
                FROM couple_access 
                WHERE access_id = ?
            ");
    		$flagCheckStmt->bind_param("i", $accessData['access_id']);
    		$flagCheckStmt->execute();
    		$flagResult = $flagCheckStmt->get_result()->fetch_assoc();
    		if (!$flagResult['should_clear_flag']) {
    			$conn->rollback();
    			echo json_encode([
    				'success' => false,
    				'message' => ucfirst($respondentType) . ' partner is already using this access code. Please resume on the same device or contact the administrator.'
    			]);
    			exit();
    		}
    		// Clear stale legacy flag to allow first-time binding
    		$clearStmt = $conn->prepare("
                UPDATE couple_access 
                SET {$respondentType}_selected = 0, 
                    {$respondentType}_selected_time = NULL 
                WHERE access_id = ?
            ");
    		$clearStmt->bind_param("i", $accessData['access_id']);
    		$clearStmt->execute();
    	}
    }

    // Bind or refresh device and selected flag
    if (empty($accessData[$partnerDeviceHashCol])) {
    	$newToken = $deviceToken ?: generateDeviceToken();
    	if (!$deviceToken) {
    		issueDeviceTokenCookie($newToken);
    	}
    	$hash = password_hash($newToken, PASSWORD_DEFAULT);
    	if ($respondentType === 'male') {
    		$upd = $conn->prepare("UPDATE couple_access SET male_selected = 1, male_selected_time = NOW(), male_device_token_hash = ?, male_device_bound_at = NOW(), male_device_last_seen = NOW() WHERE access_id = ?");
    		$upd->bind_param('si', $hash, $accessData['access_id']);
    		$upd->execute();
    		$upd->close();
    	} else {
    		$updF = $conn->prepare("UPDATE couple_access SET female_selected = 1, female_selected_time = NOW(), female_device_token_hash = ?, female_device_bound_at = NOW(), female_device_last_seen = NOW() WHERE access_id = ?");
    		$updF->bind_param('si', $hash, $accessData['access_id']);
    		$updF->execute();
    		$updF->close();
    	}
    } else {
    	if ($respondentType === 'male') {
    		$upd = $conn->prepare("UPDATE couple_access SET male_selected = 1, male_selected_time = NOW(), male_device_last_seen = NOW() WHERE access_id = ?");
    		$upd->bind_param('i', $accessData['access_id']);
    		$upd->execute();
    		$upd->close();
    	} else {
    		$updF = $conn->prepare("UPDATE couple_access SET female_selected = 1, female_selected_time = NOW(), female_device_last_seen = NOW() WHERE access_id = ?");
    		$updF->bind_param('i', $accessData['access_id']);
    		$updF->execute();
    		$updF->close();
    	}
    }

    // Proceed without couple_sessions resume/creation

    // Set session data
    $_SESSION['access_id'] = $accessData['access_id'];
    $_SESSION['access_code'] = $accessCode;
    $_SESSION['respondent'] = $respondentType;
    // No session hash tracking
    $_SESSION['partner_submitted'] = ($respondentType === 'male') 
        ? $accessData['female_profile_submitted'] 
        : $accessData['male_profile_submitted'];

    $conn->commit();

    // Determine redirect based on progress
    $redirectUrl = 'couple_profile/couple_profile_form.php';
    // No resume step switching

    // Successful response
    echo json_encode([
        'success' => true,
        'redirect' => $redirectUrl,
        'message' => 'Verification successful'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Access Verification Error: " . $e->getMessage());
    error_log("Access Verification Error Details - Access Code: '$accessCode', Respondent: '$respondentType'");
    error_log("Access Verification Error Stack Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'A system error occurred. Please try again later.',
        'debug' => 'Error logged: ' . $e->getMessage()
    ]);
    exit();
}
?>