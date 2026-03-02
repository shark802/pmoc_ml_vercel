<?php
session_start();
require_once '../includes/conn.php';

// Get certificate ID
$certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$certificate_id) {
    http_response_code(400);
    echo 'Invalid certificate ID';
    exit();
}

try {
    // Get certificate data
    $stmt = $conn->prepare("
        SELECT 
            c.certificate_id,
            c.access_id,
            c.issue_date,
            c.status,
            c.qr_token,
            ca.access_code,
            CONCAT(mp.first_name, ' ', mp.last_name) as male_name,
            CONCAT(fp.first_name, ' ', fp.last_name) as female_name
        FROM certificates c
        LEFT JOIN couple_access ca ON c.access_id = ca.access_id
        LEFT JOIN couple_profile mp ON ca.access_id = mp.access_id AND mp.sex = 'Male'
        LEFT JOIN couple_profile fp ON ca.access_id = fp.access_id AND fp.sex = 'Female'
        WHERE c.certificate_id = ?
    ");
    $stmt->bind_param("i", $certificate_id);
    $stmt->execute();
    $certificate = $stmt->get_result()->fetch_assoc();

    if (!$certificate) {
        http_response_code(404);
        echo 'Certificate not found';
        exit();
    }

    // Generate verification URL (prefer token). Handle localhost for mobile scans.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
    $serverName = $_SERVER['SERVER_NAME'] ?? '';
    $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
    // Prefer a non-localhost host
    $host = $hostHeader ?: $serverName ?: $serverAddr;
    if (empty($host) || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        $lanIp = $serverAddr;
        if (empty($lanIp) || $lanIp === '::1') {
            $lanIp = gethostbyname(gethostname());
        }
        $host = !empty($lanIp) ? $lanIp : '127.0.0.1';
    }
    // Include port if it is non-standard
    $port = $_SERVER['SERVER_PORT'] ?? '';
    if ($port && !in_array((int)$port, [80, 443], true) && strpos($host, ':') === false) {
        $host .= ':' . $port;
    }
    $token = trim($certificate['qr_token'] ?? '');
    // Fallback to ID parameter if token missing (legacy rows)
    $queryParam = $token !== '' ? ('t=' . urlencode($token)) : ('id=' . $certificate_id);
    
    // Determine base path - if uploaded to root, no /caps2 needed
    // Check if we're in production (pmoc.bccbsis.com) or local
    $isProduction = (strpos($host, 'pmoc.bccbsis.com') !== false || 
                     strpos($host, 'bccbsis.com') !== false);
    $basePath = $isProduction ? '' : '/caps2';
    
    $verificationUrl = $scheme . '://' . $host . $basePath . '/certificates/verify_certificate.php?' . $queryParam;
    
    // Fallback to production domain if host detection fails
    if (empty($verificationUrl) || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        $verificationUrl = 'https://pmoc.bccbsis.com/certificates/verify_certificate.php?' . $queryParam;
    }

    // Create QR code data
    $qrData = json_encode([
        'cert_id' => $certificate_id,
        'access_id' => $certificate['access_id'],
        'access_code' => $certificate['access_code'],
        'couple' => $certificate['male_name'] . ' & ' . $certificate['female_name'],
        'issue_date' => $certificate['issue_date'],
        'verification_url' => $verificationUrl
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error generating QR code';
    exit();
}

// Always return a PNG QR using a reliable external generator to avoid missing libs
$encoded = rawurlencode($verificationUrl);
$qrSize = isset($_GET['size']) ? preg_replace('/[^0-9x]/', '', $_GET['size']) : '300x300';
$apiUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$qrSize}&data={$encoded}";

// Stream the PNG back to the browser
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
$context = stream_context_create(['http' => ['timeout' => 5]]);
$img = @file_get_contents($apiUrl, false, $context);
if ($img === false) {
    // Final fallback: generate a simple placeholder PNG with the URL text
    $im = imagecreatetruecolor(300, 300);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefilledrectangle($im, 0, 0, 300, 300, $white);
    imagestring($im, 3, 10, 140, 'Scan URL:', $black);
    imagestring($im, 2, 10, 160, $verificationUrl, $black);
    imagepng($im);
    // Note: imagedestroy() is deprecated in PHP 8.0+ - resource is automatically destroyed when out of scope
    // No need to call imagedestroy() - the resource will be cleaned up automatically when the function exits
    exit;
}
echo $img;
?> 