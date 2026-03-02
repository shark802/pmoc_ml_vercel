<?php
// Start output buffering to prevent any output before redirect
ob_start();
// session_start() is called in the included templates, so we don't need it here
require_once '../includes/conn.php';

$certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$certificate_id) {
    http_response_code(400);
    echo 'Invalid certificate ID';
    exit();
}

try {
    // Get certificate data with couple information
    $stmt = $conn->prepare("
        SELECT 
            c.certificate_id,
            c.access_id,
            -- Removed couple_id as it's no longer needed
            c.issue_date,
            c.status,
            c.admin_id,
            ca.access_code,
            CONCAT(mp.first_name, ' ', mp.last_name) as male_name,
            CONCAT(fp.first_name, ' ', fp.last_name) as female_name,
            mp.date_of_birth as male_dob,
            fp.date_of_birth as female_dob,
            a.admin_name as issued_by
        FROM certificates c
        LEFT JOIN couple_access ca ON c.access_id = ca.access_id
        LEFT JOIN couple_profile mp ON ca.access_id = mp.access_id AND mp.sex = 'Male'
        LEFT JOIN couple_profile fp ON ca.access_id = fp.access_id AND fp.sex = 'Female'
        LEFT JOIN admin a ON c.admin_id = a.admin_id
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

    // Get the actual certificate number from database
    $cert_number_stmt = $conn->prepare("SELECT certificate_number FROM certificates WHERE certificate_id = ?");
    $cert_number_stmt->bind_param("i", $certificate_id);
    $cert_number_stmt->execute();
    $cert_number_result = $cert_number_stmt->get_result()->fetch_assoc();
    $certificateNumber = $cert_number_result['certificate_number'] ?? 'N/A';
    
    // Determine certificate type based on number pattern
    $certificate_type = '';
    if (preg_match('/^[0-9]{4}-[0-9]+$/', $certificateNumber)) {
        $certificate_type = 'counseling';  // YYYY-N format (e.g., 2025-1)
    } else {
        $certificate_type = 'orientation';  // YYYY-MM-NNN format (e.g., 2025-10-001)
    }
    
    // Debug: Log the decision (remove this after testing)
    // error_log("Certificate ID: $certificate_id, Number: $certificateNumber, Type: $certificate_type");
    
    // Clear any output and include the appropriate certificate template
    ob_clean();
    
    // Ensure the certificate_id is available in the included template
    $_GET['id'] = $certificate_id;
    
    if ($certificate_type === 'counseling') {
        include __DIR__ . '/certificate_counseling_template.php';
    } else {
        include __DIR__ . '/certificate_compliance_template.php';
    }
    exit();

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error loading certificate';
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate #<?= $certificate['certificate_id'] ?> | BCPDO System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Times New Roman', serif;
        }
        .certificate-container {
            background: white;
            border: 3px solid #28a745;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 30px auto;
            max-width: 900px;
            overflow: hidden;
        }
        .certificate-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
        }
        .certificate-header::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 15px;
        }
        .header-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .header-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        .certificate-body {
            padding: 50px;
            text-align: center;
        }
        .certificate-number {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 30px;
            font-family: 'Courier New', monospace;
        }
        .main-text {
            font-size: 1.3rem;
            line-height: 1.8;
            margin-bottom: 40px;
            color: #333;
        }
        .couple-names {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
            margin: 30px 0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .certificate-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            color: #495057;
            min-width: 150px;
        }
        .detail-value {
            color: #212529;
            text-align: right;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .signature-box {
            text-align: center;
            flex: 1;
            margin: 0 20px;
        }
        .signature-line {
            border-bottom: 2px solid #333;
            width: 200px;
            margin: 10px auto;
            height: 40px;
        }
        .signature-name {
            font-weight: bold;
            color: #333;
        }
        .signature-title {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .qr-section {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .qr-code {
            width: 120px;
            height: 120px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 10px;
            background: white;
            margin: 0 auto 15px;
        }
        .verification-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .certificate-footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .status-badge {
            font-size: 1rem;
            padding: 8px 16px;
            border-radius: 20px;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        @media print {
            .print-button { display: none; }
            body { background: white; }
            .certificate-container { 
                border: none; 
                box-shadow: none; 
                margin: 0; 
            }
        }
    </style>
</head>

<body>
    <!-- Print Button -->
    <button class="btn btn-primary print-button" onclick="window.print()">
        <i class="fas fa-print mr-2"></i>Print Certificate
    </button>

    <div class="container-fluid">
        <div class="certificate-container">
            <!-- Certificate Header -->
            <div class="certificate-header">
                <div class="header-title">
                    <i class="fas fa-certificate mr-3"></i>Certificate of Completion
                </div>
                <div class="header-subtitle">
                    Pre-Marriage Counseling Program<br>
                    Bago City Population Development Office (BCPDO)
                </div>
            </div>

            <!-- Certificate Body -->
            <div class="certificate-body">
                <!-- Certificate Number -->
                <div class="certificate-number">
                    Certificate No: <?= htmlspecialchars($certificateNumber) ?>
                </div>

                <!-- Main Text -->
                <div class="main-text">
                    This is to certify that
                </div>

                <!-- Couple Names -->
                <div class="couple-names">
                    <?= htmlspecialchars($certificate['male_name']) ?><br>
                    <span style="font-size: 1.5rem; color: #6c757d;">and</span><br>
                    <?= htmlspecialchars($certificate['female_name']) ?>
                </div>

                <div class="main-text">
                    have successfully completed the Pre-Marriage Counseling Program<br>
                    conducted by the Bago City Population Development Office (BCPDO)<br>
                    in accordance with the requirements set forth by the City Ordinance.
                </div>

                <!-- Certificate Details -->
                <div class="certificate-details">
                    <h6 class="text-center mb-3"><i class="fas fa-info-circle mr-2"></i>Certificate Information</h6>
                    
                    <div class="detail-row">
                        <span class="detail-label">Certificate ID:</span>
                        <span class="detail-value">#<?= $certificate['certificate_id'] ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Access Code:</span>
                        <span class="detail-value"><?= htmlspecialchars($certificate['access_code']) ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Issue Date:</span>
                        <span class="detail-value"><?= date('F d, Y', strtotime($certificate['issue_date'])) ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <?php
                            $statusClass = 'badge-secondary';
                            if ($certificate['status'] === 'active') $statusClass = 'badge-success';
                            elseif ($certificate['status'] === 'revoked') $statusClass = 'badge-danger';
                            elseif ($certificate['status'] === 'pending') $statusClass = 'badge-warning';
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst($certificate['status']) ?></span>
                        </span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Issued By:</span>
                        <span class="detail-value"><?= htmlspecialchars($certificate['issued_by'] ?? 'BCPDO System') ?></span>
                    </div>
                </div>

                <!-- QR Code Section -->
                <div class="qr-section">
                    <h6 class="mb-3"><i class="fas fa-qrcode mr-2"></i>Verification QR Code</h6>
                    <img src="qr_generator.php?id=<?= $certificate['certificate_id'] ?>" 
                         alt="Certificate QR Code" class="qr-code">
                    <div class="verification-info">
                        Scan this QR code to verify certificate authenticity<br>
                        or visit: <a href="verify_certificate.php?id=<?= $certificate['certificate_id'] ?>" target="_blank">verification page</a>
                    </div>
                </div>

                <!-- Signature Section -->
                <div class="signature-section">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-name">BCPDO Director</div>
                        <div class="signature-title">Authorized Signatory</div>
                    </div>
                    
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-name"><?= htmlspecialchars($certificate['issued_by'] ?? 'BCPDO Staff') ?></div>
                        <div class="signature-title">Program Coordinator</div>
                    </div>
                </div>
            </div>

            <!-- Certificate Footer -->
            <div class="certificate-footer">
                <p class="mb-0">
                    <i class="fas fa-shield-alt mr-2"></i>
                    This certificate is issued as proof of completion and compliance with pre-marriage counseling requirements.<br>
                    For verification, please scan the QR code or contact BCPDO office.
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 