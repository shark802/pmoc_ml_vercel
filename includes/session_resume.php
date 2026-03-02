<?php
/**
 * Session Resume Functionality
 * Handles session recovery and progress tracking
 */

// Function to check if user can resume their session
function canResumeSession($access_id, $respondent) {
	global $conn;
	
	$stmt = $conn->prepare("
        SELECT 
            {$respondent}_selected,
            {$respondent}_selected_time,
            {$respondent}_profile_submitted,
            {$respondent}_questionnaire_submitted,
            TIMESTAMPDIFF(MINUTE, {$respondent}_selected_time, NOW()) as minutes_elapsed
        FROM couple_access 
        WHERE access_id = ?
    ");
	$stmt->bind_param("i", $access_id);
	$stmt->execute();
	$result = $stmt->get_result()->fetch_assoc();
	
	if (!$result) {
		return ['can_resume' => false, 'reason' => 'Invalid access ID'];
	}
	
	// Check if session is still valid (within 30 minutes)
	if ($result['minutes_elapsed'] > 30) {
		return ['can_resume' => false, 'reason' => 'Session expired'];
	}
	
	// Check if profile is already submitted
	if ($result["{$respondent}_profile_submitted"]) {
		return ['can_resume' => true, 'step' => 'questionnaire', 'reason' => 'Profile completed, resume questionnaire'];
	}
	
	// Check if questionnaire is already submitted
	if ($result["{$respondent}_questionnaire_submitted"]) {
		return ['can_resume' => true, 'step' => 'complete', 'reason' => 'Questionnaire completed. Please walk in to schedule.'];
	}
	
	// Can resume profile
	return ['can_resume' => true, 'step' => 'profile', 'reason' => 'Resume profile completion'];
}

// Function to get progress data for resume
function getProgressData($access_id, $respondent, $step) {
	global $conn;
	
	switch ($step) {
		case 'profile':
			// Get profile progress from session or form data
			$stmt = $conn->prepare("
                SELECT 
                    sex, first_name, last_name, date_of_birth, age, 
                    education, occupation, income, residency_type,
                    address, contact_number, email
                FROM couple_profile 
                WHERE access_id = ? AND respondent = ?
            ");
			$stmt->bind_param("is", $access_id, $respondent);
			$stmt->execute();
			$result = $stmt->get_result();
			
			if ($result->num_rows > 0) {
				return $result->fetch_assoc();
			}
			return null;
			
		case 'questionnaire':
			// Get questionnaire progress
			$stmt = $conn->prepare("
                SELECT 
                    category_id,
                    question_id,
                    response,
                    reason
                FROM couple_responses 
                WHERE access_id = ? AND respondent = ?
                ORDER BY category_id, question_id
            ");
			$stmt->bind_param("is", $access_id, $respondent);
			$stmt->execute();
			$result = $stmt->get_result();
			
			$responses = [];
			while ($row = $result->fetch_assoc()) {
				$responses[$row['category_id']][$row['question_id']] = [
					'response' => $row['response'],
					'reason' => $row['reason']
				];
			}
			return $responses;
			
		default:
			return null;
	}
}

// Function to update session activity
function updateSessionActivity($access_id, $respondent) {
	global $conn;
	
	$stmt = $conn->prepare("
        UPDATE couple_access 
        SET {$respondent}_selected_time = NOW() 
        WHERE access_id = ? AND {$respondent}_selected = 1
    ");
	$stmt->bind_param("i", $access_id);
	return $stmt->execute();
}

// Function to clear session flags
function clearSessionFlags($access_id, $respondent) {
	global $conn;
	
	$stmt = $conn->prepare("
        UPDATE couple_access 
        SET {$respondent}_selected = 0, 
            {$respondent}_selected_time = NULL 
        WHERE access_id = ?
    ");
	$stmt->bind_param("i", $access_id);
	return $stmt->execute();
}
?>