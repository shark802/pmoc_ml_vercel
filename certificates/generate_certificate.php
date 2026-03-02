<?php
// Certificate Generation with Monthly Numbering and QR Code
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
require_once '../includes/conn.php';
require_once '../includes/session.php';
require_once '../includes/audit_log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $access_id = intval($_POST['access_id']);
    
    // Clear any previous output and set JSON headers
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Set JSON content type
    header('Content-Type: application/json');
    
    try {
        if (method_exists($conn, 'begin_transaction')) {
            $conn->begin_transaction();
        }

        // Generate official couple number (YYYY-MM-NNNN format)
        // Check if there's already a certificate for this access_id
        $existing_cert_stmt = $conn->prepare("SELECT certificate_number FROM certificates WHERE access_id = ?");
        $existing_cert_stmt->bind_param("i", $access_id);
        $existing_cert_stmt->execute();
        $existing_cert_result = $existing_cert_stmt->get_result()->fetch_assoc();
        
        if ($existing_cert_result) {
            // Use existing certificate number
            $cert_number = $existing_cert_result['certificate_number'];
            // Extract certificate type from existing number
            if (strpos($cert_number, '-ORI-') !== false) {
                $certificate_type = 'orientation';
            } elseif (strpos($cert_number, '-COU-') !== false) {
                $certificate_type = 'counseling';
            }
        } else {
            // Certificate type will be determined in eligibility check
            $certificate_type = '';
        }
        
        // Generate QR token
        $qr_token = bin2hex(random_bytes(32));
        $qr_url = "https://yourdomain.com/certificates/verify_certificate.php?t=" . $qr_token;
        
        // Get couple information
        $couple_stmt = $conn->prepare("
            SELECT 
                ca.access_code,
                CONCAT(mp.first_name, ' ', mp.last_name) AS male_name,
                CONCAT(fp.first_name, ' ', fp.last_name) AS female_name,
                s.session_date,
                s.session_type
            FROM couple_access ca
            LEFT JOIN couple_profile mp ON ca.access_id = mp.access_id AND mp.sex = 'Male'
            LEFT JOIN couple_profile fp ON ca.access_id = fp.access_id AND fp.sex = 'Female'
            LEFT JOIN scheduling s ON ca.access_id = s.access_id
            WHERE ca.access_id = ?
            ORDER BY s.session_date DESC
            LIMIT 1
        ");
        $couple_stmt->bind_param("i", $access_id);
        $couple_stmt->execute();
        $couple_result = $couple_stmt->get_result();
        $couple_data = $couple_result->fetch_assoc();
        
        // Check if certificate record exists, if not create it after eligibility check
        $cert_check_stmt = $conn->prepare("SELECT certificate_id FROM certificates WHERE access_id = ?");
        $cert_check_stmt->bind_param("i", $access_id);
        $cert_check_stmt->execute();
        $cert_exists = $cert_check_stmt->get_result()->fetch_assoc();
        
        if (!$cert_exists) {
            // Run eligibility check logic directly
            
            // 1) Male profile submitted
            $male_profile_stmt = $conn->prepare("SELECT male_profile_submitted FROM couple_access WHERE access_id = ?");
            $male_profile_stmt->bind_param("i", $access_id);
            $male_profile_stmt->execute();
            $male_profile = intval(($male_profile_stmt->get_result()->fetch_assoc()['male_profile_submitted'] ?? 0));

            // 2) Female profile submitted
            $female_profile_stmt = $conn->prepare("SELECT female_profile_submitted FROM couple_access WHERE access_id = ?");
            $female_profile_stmt->bind_param("i", $access_id);
            $female_profile_stmt->execute();
            $female_profile = intval(($female_profile_stmt->get_result()->fetch_assoc()['female_profile_submitted'] ?? 0));

            // 3) Male questionnaire submitted
            $male_q_stmt = $conn->prepare("SELECT male_questionnaire_submitted FROM couple_access WHERE access_id = ?");
            $male_q_stmt->bind_param("i", $access_id);
            $male_q_stmt->execute();
            $male_q = intval(($male_q_stmt->get_result()->fetch_assoc()['male_questionnaire_submitted'] ?? 0));

            // 4) Female questionnaire submitted
            $female_q_stmt = $conn->prepare("SELECT female_questionnaire_submitted FROM couple_access WHERE access_id = ?");
            $female_q_stmt->bind_param("i", $access_id);
            $female_q_stmt->execute();
            $female_q = intval(($female_q_stmt->get_result()->fetch_assoc()['female_questionnaire_submitted'] ?? 0));

            // 5) Check if counseling is required (age <= 25)
            $age_stmt = $conn->prepare("SELECT MIN(TIMESTAMPDIFF(YEAR, STR_TO_DATE(date_of_birth, '%Y-%m-%d'), CURDATE())) AS min_age FROM couple_profile WHERE access_id = ?");
            $age_stmt->bind_param("i", $access_id);
            $age_stmt->execute();
            $min_age = intval(($age_stmt->get_result()->fetch_assoc()['min_age'] ?? 99));
            $requires_counseling = ($min_age <= 25) ? 1 : 0;

            // 6) Orientation attendance
            $orient_stmt = $conn->prepare("SELECT COUNT(DISTINCT al.partner_type) AS cnt FROM attendance_logs al JOIN scheduling s ON al.schedule_id = s.schedule_id WHERE s.access_id = ? AND al.segment = 'orientation' AND al.status = 'present'");
            $orient_stmt->bind_param("i", $access_id);
            $orient_stmt->execute();
            $orientation_cnt = intval(($orient_stmt->get_result()->fetch_assoc()['cnt'] ?? 0));

            // 7) Counseling attendance (only if required)
            if ($requires_counseling === 1) {
                $coun_stmt = $conn->prepare("SELECT COUNT(DISTINCT al.partner_type) AS cnt FROM attendance_logs al JOIN scheduling s ON al.schedule_id = s.schedule_id WHERE s.access_id = ? AND al.segment = 'counseling' AND al.status = 'present'");
                $coun_stmt->bind_param("i", $access_id);
                $coun_stmt->execute();
                $counseling_cnt = intval(($coun_stmt->get_result()->fetch_assoc()['cnt'] ?? 0));
            } else {
                $counseling_cnt = 2; // treat as passed when not required
            }

            // Determine certificate type based on actual session type from scheduling
            $certificate_type = '';
            $orientation_eligible = ($male_profile === 1 && $female_profile === 1 && $male_q === 1 && $female_q === 1 && $orientation_cnt === 2);
            $counseling_eligible = ($orientation_eligible && $counseling_cnt === 2);
            
            // Get the actual session type from scheduling table
            $session_type_stmt = $conn->prepare("SELECT session_type FROM scheduling WHERE access_id = ? ORDER BY session_date DESC LIMIT 1");
            $session_type_stmt->bind_param("i", $access_id);
            $session_type_stmt->execute();
            $session_type = $session_type_stmt->get_result()->fetch_assoc()['session_type'] ?? '';
            
            // Determine certificate type based on session type and eligibility
            // For "Orientation + Counseling" sessions, generate BOTH certificates
            if ($session_type === 'Orientation' && $orientation_eligible) {
                $certificate_type = 'orientation'; // Orientation only session
            } elseif ($session_type === 'Orientation + Counseling' && $orientation_eligible) {
                $certificate_type = 'orientation'; // First generate orientation certificate
            } else {
                throw new Exception("Couple is not eligible for any certificate. Requirements not met.");
            }

            // Generate certificate number based on type
            if (!$existing_couple_result) {
                $year = date('Y');
                $month = date('m'); // 2-digit month with leading zero
                
                if ($certificate_type === 'counseling') {
                    // Counseling certificates: YYYY-N format (e.g., 2025-1, 2025-2, 2025-3)
                    $yearly_count_stmt = $conn->prepare("
                        SELECT COUNT(*) as count 
                        FROM certificates 
                        WHERE certificate_number COLLATE utf8mb4_general_ci REGEXP CONCAT('^', ?, '-[0-9]+$')
                    ");
                    $yearly_count_stmt->bind_param("s", $year);
                    $yearly_count_stmt->execute();
                    $count_result = $yearly_count_stmt->get_result()->fetch_assoc();
                    $next_number = ($count_result['count'] ?? 0) + 1;
                    
                    // Format: YYYY-N
                    $cert_number = sprintf('%s-%d', $year, $next_number);
                } else {
                    // Compliance certificates: YYYY-MM-NNN format (no prefix)
                    $monthly_count_stmt = $conn->prepare("
                        SELECT COUNT(*) as count 
                        FROM certificates 
                        WHERE certificate_number COLLATE utf8mb4_general_ci REGEXP CONCAT('^', ?, '-', ?, '-[0-9]+$')
                    ");
                    $monthly_count_stmt->bind_param("ss", $year, $month);
                    $monthly_count_stmt->execute();
                    $count_result = $monthly_count_stmt->get_result()->fetch_assoc();
                    $next_number = ($count_result['count'] ?? 0) + 1;
                    
                    // Format: YYYY-MM-NNN
                    $cert_number = sprintf('%s-%s-%03d', $year, $month, $next_number);
                }
                
            // No need for separate PMC number - extracted from certificate_number
            }

            // Create certificate record in 'eligible' status
            $admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
            
            $cert_stmt = $conn->prepare("INSERT INTO certificates (access_id, status, issue_date, admin_id) VALUES (?, 'eligible', NOW(), ?)");
            $cert_stmt->bind_param("ii", $access_id, $admin_id);
            $cert_stmt->execute();
        }
        
        // Update certificate with generated data
        $update_cert_stmt = $conn->prepare("
            UPDATE certificates 
            SET certificate_number = ?, qr_token = ?, qr_url = ?, 
                verification_status = 'valid', status = 'issued'
            WHERE access_id = ? AND status = 'eligible'
        ");
        $update_cert_stmt->bind_param("sssi", $cert_number, $qr_token, $qr_url, $access_id);
        $update_cert_stmt->execute();
        
        if ($update_cert_stmt->affected_rows === 0) {
            throw new Exception("Certificate not found or not eligible for access_id: " . $access_id);
        }
        
        // Log certificate generation
        logAudit($conn, $_SESSION['admin_id'] ?? 0, AUDIT_CREATE, 
            'Certificate generated: ' . $cert_number . ' (' . $certificate_type . ')', 
            'certificates', 
            ['certificate_number' => $cert_number, 'certificate_type' => $certificate_type, 'access_id' => $access_id]);
        
        // Certificate number is now the same as couple number - no need for separate couple_official table

        // For "Orientation + Counseling" sessions, also generate counseling certificate if counseling is completed
        if ($session_type === 'Orientation + Counseling' && $counseling_eligible && $orientation_eligible) {
            // Generate counseling certificate number YYYY-N format
            $year = date('Y');
            $yearly_count_stmt = $conn->prepare("SELECT COUNT(*) AS count FROM certificates WHERE certificate_number COLLATE utf8mb4_general_ci REGEXP CONCAT('^', ?, '-[0-9]+$')");
            $yearly_count_stmt->bind_param('s', $year);
            $yearly_count_stmt->execute();
            $count_result = $yearly_count_stmt->get_result()->fetch_assoc();
            $next_number = (int)($count_result['count'] ?? 0) + 1;
            $counsel_cert_number = sprintf('%s-%d', $year, $next_number);

            // No need for separate PMC counter - number is in certificate_number

            // Generate QR token for counseling certificate
            $qr_token2 = bin2hex(random_bytes(32));
            $qr_url2 = "https://yourdomain.com/certificates/verify_certificate.php?t=" . $qr_token2;

            // Insert counseling certificate
            $admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
            
            $ins = $conn->prepare("INSERT INTO certificates (access_id, status, issue_date, admin_id) VALUES (?, 'eligible', NOW(), ?)");
            $ins->bind_param('ii', $access_id, $admin_id);
            $ins->execute();

            // Update counseling certificate with details
            $upd2 = $conn->prepare("UPDATE certificates SET certificate_number = ?, qr_token = ?, qr_url = ?, verification_status = 'valid', status = 'issued' WHERE access_id = ? AND status = 'eligible'");
            $upd2->bind_param('sssi', $counsel_cert_number, $qr_token2, $qr_url2, $access_id);
            $upd2->execute();
            
            // Log counseling certificate generation
            logAudit($conn, $_SESSION['admin_id'] ?? 0, AUDIT_CREATE, 
                'Counseling certificate generated: ' . $counsel_cert_number, 
                'certificates', 
                ['certificate_number' => $counsel_cert_number, 'certificate_type' => 'counseling', 'access_id' => $access_id]);
        }

        
        // Log certificate generation
        $log_stmt = $conn->prepare("
            INSERT INTO qr_verification_logs (certificate_id, qr_token, verification_ip, verification_user_agent, verification_result)
            VALUES ((SELECT certificate_id FROM certificates WHERE access_id = ? ORDER BY certificate_id DESC LIMIT 1), ?, ?, ?, 'valid')
        ");
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $log_stmt->bind_param("isss", $access_id, $qr_token, $ip, $user_agent);
        $log_stmt->execute();
        
        if (method_exists($conn, 'commit')) {
            $conn->commit();
        }
        
        // Check if both certificates were generated (for Orientation + Counseling sessions)
        $both_certificates = ($session_type === 'Orientation + Counseling' && $counseling_eligible && $orientation_eligible);
        
        if ($both_certificates) {
            // For Orientation + Counseling sessions, show message for both certificates
            $certificate_name = 'Certificate of Compliance and Certificate of Marriage Counseling';
            $message = 'Certificate of Compliance and Certificate of Marriage Counseling generated successfully';
        } else {
            // For single certificate generation
            $certificate_name = ($certificate_type === 'counseling') ? 'Certificate of Marriage Counseling' : 'Certificate of Compliance';
            $message = $certificate_name . ' generated successfully';
        }
        
        // Get the generated certificate ID for viewing (prefer counseling certificate if both exist)
        if ($both_certificates) {
            // Get the counseling certificate ID (the second one generated)
            $cert_id_stmt = $conn->prepare("SELECT certificate_id FROM certificates WHERE access_id = ? AND certificate_number COLLATE utf8mb4_general_ci REGEXP '^[0-9]{4}-[0-9]+$' ORDER BY certificate_id DESC LIMIT 1");
        } else {
            // Get the most recent certificate ID
            $cert_id_stmt = $conn->prepare("SELECT certificate_id FROM certificates WHERE access_id = ? ORDER BY certificate_id DESC LIMIT 1");
        }
        $cert_id_stmt->bind_param("i", $access_id);
        $cert_id_stmt->execute();
        $cert_id_result = $cert_id_stmt->get_result()->fetch_assoc();
        $certificate_id = $cert_id_result['certificate_id'];
        
        // Determine template URL based on certificate type
        $template_url = ($certificate_type === 'counseling') 
            ? 'certificate_counseling_template.php?id=' . $certificate_id
            : 'certificate_compliance_template.php?id=' . $certificate_id;
        
        // Ensure clean output
        if (ob_get_level()) {
            ob_clean();
        }
        
        echo json_encode([
            'success' => true,
            'certificate_id' => $certificate_id,
            'certificate_number' => $cert_number,
            'certificate_type' => $certificate_type,
            'certificate_name' => $certificate_name,
            'template_url' => $template_url,
            'qr_token' => $qr_token,
            'qr_url' => $qr_url,
            'couple_data' => $couple_data,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        if (method_exists($conn, 'rollback')) {
            $conn->rollback();
        }
        
        // Ensure clean output for error response
        if (ob_get_level()) {
            ob_clean();
        }
        
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>