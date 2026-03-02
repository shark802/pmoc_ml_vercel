<?php
// QR Verification System - Public Verification Page
require_once '../includes/conn.php';
require_once '../includes/image_helper.php';

// Get token or ID from URL
$token = $_GET['t'] ?? '';
$certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$format = $_GET['format'] ?? 'html'; // 'html' or 'json'

if (empty($token) && !$certificate_id) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No token or certificate ID provided', 'valid' => false]);
    } else {
        echo "<h1>Certificate Verification</h1><p>No verification token or certificate ID provided.</p>";
    }
    exit;
}

try {
    // Get certificate information - support both token and ID lookup
    if (!empty($token)) {
        // Lookup by token (preferred method)
        $stmt = $conn->prepare("
            SELECT 
                c.certificate_id,
                c.certificate_number,
                c.verification_status,
                c.issue_date,
                c.revoked_at,
                c.revoked_reason,
                a.admin_name AS issued_by,
                CONCAT(mp.first_name, ' ', mp.last_name) AS male_name,
                CONCAT(fp.first_name, ' ', fp.last_name) AS female_name,
                s.session_date
            FROM certificates c
            JOIN couple_access ca ON c.access_id = ca.access_id
            LEFT JOIN admin a ON c.admin_id = a.admin_id
            LEFT JOIN couple_profile mp ON ca.access_id = mp.access_id AND mp.sex = 'Male'
            LEFT JOIN couple_profile fp ON ca.access_id = fp.access_id AND fp.sex = 'Female'
            LEFT JOIN scheduling s ON ca.access_id = s.access_id
            WHERE c.qr_token = ?
            ORDER BY s.session_date DESC
            LIMIT 1
        ");
        $stmt->bind_param("s", $token);
    } else {
        // Fallback: lookup by certificate ID
        $stmt = $conn->prepare("
            SELECT 
                c.certificate_id,
                c.certificate_number,
                c.verification_status,
                c.issue_date,
                c.revoked_at,
                c.revoked_reason,
                a.admin_name AS issued_by,
                CONCAT(mp.first_name, ' ', mp.last_name) AS male_name,
                CONCAT(fp.first_name, ' ', fp.last_name) AS female_name,
                s.session_date
            FROM certificates c
            JOIN couple_access ca ON c.access_id = ca.access_id
            LEFT JOIN admin a ON c.admin_id = a.admin_id
            LEFT JOIN couple_profile mp ON ca.access_id = mp.access_id AND mp.sex = 'Male'
            LEFT JOIN couple_profile fp ON ca.access_id = fp.access_id AND fp.sex = 'Female'
            LEFT JOIN scheduling s ON ca.access_id = s.access_id
            WHERE c.certificate_id = ?
            ORDER BY s.session_date DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $certificate_id);
    }
        $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Certificate not found");
    }
    
    $certificate = $result->fetch_assoc();
    
    // Determine verification status
    $is_valid = false;
    $status_message = '';
    
    if ($certificate['verification_status'] === 'valid' && $certificate['revoked_at'] === null) {
        $is_valid = true;
        $status_message = 'VALID';
    } elseif ($certificate['verification_status'] === 'revoked') {
        $status_message = 'REVOKED';
    } elseif ($certificate['verification_status'] === 'expired') {
        $status_message = 'EXPIRED';
        } else {
        $status_message = 'INVALID';
    }
    
    // Log verification attempt (only if qr_verification_logs table exists)
    try {
        $log_stmt = $conn->prepare("
            INSERT INTO qr_verification_logs 
            (certificate_id, qr_token, verification_ip, verification_user_agent, verification_result, verified_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log_token = !empty($token) ? $token : '';
        $log_stmt->bind_param("issss", 
            $certificate['certificate_id'], 
            $log_token, 
            $ip, 
            $user_agent, 
            $status_message
        );
        $log_stmt->execute();
    } catch (Exception $log_error) {
        // Silently fail if logging table doesn't exist
        error_log("QR verification log error: " . $log_error->getMessage());
    }
    
    // Format completion date
    $completion_date = date('F j, Y', strtotime($certificate['session_date']));
    
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'valid' => $is_valid,
            'status' => $status_message,
            'certificate_number' => $certificate['certificate_number'],
            'couple_names' => $certificate['male_name'] . ' & ' . $certificate['female_name'],
            'completion_date' => $completion_date,
            'program' => 'PMOC (Pre-Marriage Orientation and Counseling)',
            'issued_by' => $certificate['issued_by'] ?? 'System',
            'issued_date' => date('F j, Y', strtotime($certificate['issue_date'])),
            'revoked_reason' => $certificate['revoked_reason']
        ]);
    } else {
        // HTML format
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Certificate Verification - BCPDO</title>
    <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; margin-bottom: 30px; }
                .logos { display:flex; align-items:center; justify-content:center; gap:20px; margin-bottom:10px; }
                .logos img { height:140px; width:auto; }
                .status { text-align: center; padding: 20px; border-radius: 5px; margin: 20px 0; font-size: clamp(22px, 3.2vw, 36px); font-weight: bold; }
                .valid { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                .invalid { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .info { background: #e2e3e5; padding: clamp(14px, 1.5vw, 24px); border-radius: 6px; margin: 14px 0; font-size: clamp(18px, 2.2vw, 28px); line-height: 1.8; }
                .info strong { display: inline-block; width: clamp(180px, 22vw, 340px); font-weight: 700; color: #222; font-size: clamp(18px, 2.2vw, 28px); }
                .footer { text-align: center; margin-top: 30px; color: #666; }
                /* Responsive adjustments for small screens */
                @media (max-width: 576px) {
                    .logos img { height: 110px; }
                    .info { font-size: 16px; padding: 14px; }
                    .info strong { display:block; width:auto; margin-top:10px; }
                    .info br { display:none; }
                    .info .line { display:block; margin-bottom:8px; }
                }
                /* Desktop tweaks */
                @media (min-width: 992px) {
                    .container { max-width: 1200px; }
                    .info .line { display: flex; align-items: baseline; gap: 1rem; }
                    .info br { display: none; }
                }
    </style>
</head>
<body>
    <div class="container">
                <div class="header">
                    <div class="logos">
                        <img src="../images/City_of_Bago_Logo.png" alt="City of Bago Logo">
                        <img src="<?= getSecureImagePath('../images/bcpdo.png') ?>" alt="BCPDO Logo">
                    </div>
                    <h1>BCPDO Certificate Verification</h1>
                    <h2>City of Bago Population and Development Office</h2>
                </div>

                <div class="status <?php echo $is_valid ? 'valid' : 'invalid'; ?>">
                    <?php echo $is_valid ? '✅ VALID CERTIFICATE' : '❌ ' . $status_message; ?>
                </div>

                <div class="info">
                    <span class="line"><strong>Certificate Number:</strong> <?php echo htmlspecialchars($certificate['certificate_number']); ?></span><br>
                    <span class="line"><strong>Couple Names:</strong> <?php echo htmlspecialchars($certificate['male_name'] . ' & ' . $certificate['female_name']); ?></span><br>
                    <span class="line"><strong>Completion Date:</strong> <?php echo $completion_date; ?></span><br>
                    <span class="line"><strong>Program:</strong> PMOC (Pre-Marriage Orientation and Counseling)</span><br>
                    <span class="line"><strong>Issued By:</strong> <?php echo htmlspecialchars($certificate['issued_by'] ?? 'System'); ?></span><br>
                    <span class="line"><strong>Issued Date:</strong> <?php echo date('F j, Y', strtotime($certificate['issue_date'])); ?></span><br>
                    <?php if ($certificate['revoked_reason']): ?>
                    <strong>Revocation Reason:</strong> <?php echo htmlspecialchars($certificate['revoked_reason']); ?><br>
                    <?php endif; ?>
                </div>
                
                <div class="footer">
                    <p>This certificate verifies completion of the Pre-Marriage Orientation and Counseling program.</p>
                    <p>Verified on: <?php echo date('F j, Y \a\t g:i A'); ?></p>
                </div>
            </div>
</body>
</html> 
        <?php
    }
    
} catch (Exception $e) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage(), 'valid' => false]);
    } else {
        echo "<h1>Certificate Verification</h1><p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>