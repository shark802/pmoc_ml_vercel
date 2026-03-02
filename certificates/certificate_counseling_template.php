<?php
session_start();
require_once '../includes/conn.php';
require_once '../includes/image_helper.php';

$certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no ID provided, show blank template for preview
if (!$certificate_id) {
    $certificate = [
        'male_name' => '_____________________',
        'female_name' => '_____________________', 
        'issue_date' => date('Y-m-d'),
        'certificate_number' => 'YYYY-MM-COU-NNNN',
        'pmc_number' => 'N'
    ];
} else {
    try {
    // Get certificate data with couple information
    $stmt = $conn->prepare("
        SELECT 
            c.certificate_id,
            c.access_id,
            -- Removed couple_id as it's no longer needed
            c.issue_date,
            c.status,
            c.certificate_number,
            ca.access_code,
            CONCAT(mp.first_name, ' ', COALESCE(mp.middle_name, ''), ' ', mp.last_name) as male_name,
            CONCAT(fp.first_name, ' ', COALESCE(fp.middle_name, ''), ' ', fp.last_name) as female_name,
            mp.date_of_birth as male_dob,
            fp.date_of_birth as female_dob,
            c.certificate_number as couple_number
        FROM certificates c
        LEFT JOIN couple_access ca ON c.access_id = ca.access_id
        LEFT JOIN couple_profile mp ON ca.access_id = mp.access_id AND mp.sex = 'Male'
        LEFT JOIN couple_profile fp ON ca.access_id = fp.access_id AND fp.sex = 'Female'
        -- Removed couple_official join as certificate_number is now the couple number
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

    // Extract PMC number from certificate_number (e.g., "2025-1" -> "1")
    $cert_number = $certificate['certificate_number'] ?? '';
    $pmc_number = 1; // Default
    if (preg_match('/^[0-9]{4}-([0-9]+)$/', $cert_number, $matches)) {
        $pmc_number = $matches[1];
    }
    $certificate['pmc_number'] = $pmc_number;

    } catch (Exception $e) {
        http_response_code(500);
        echo 'Database error: ' . $e->getMessage();
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate of Marriage Counseling | BCPDO System</title>
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        
        body {
            font-family: 'Times New Roman', serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: #000;
        }
        
        .certificate-container {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            padding: 20mm;
            box-sizing: border-box;
            border: 3px solid #000;
            position: relative;
        }
        .qr-section { 
            position: absolute; 
            left: 0; 
            right: 0; 
            bottom: 15mm; 
            text-align: center; 
            z-index: 1;
            pointer-events: none; /* Allow clicks to pass through to elements below */
        }
        .qr-frame { width: 45mm; height: 45mm; border: 0; pointer-events: auto; }
        .qr-caption { font-size: 10px; color: #333; margin-top: 4px; }
        
        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
        }
        
        .header-text {
            text-align: center;
            flex: 1;
            margin: 0 20px;
        }
        
        .republic {
            font-size: 14px;
            font-weight: bold;
            margin: 0;
        }
        
        .province {
            font-size: 12px;
            margin: 2px 0;
        }
        
        .office-name {
            font-size: 12px;
            font-weight: bold;
            margin: 2px 0;
        }
        
        .certificate-title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin: 40px 0 30px 0;
            font-style: italic;
        }
        
        .certificate-body {
            text-align: justify;
            line-height: 1.8;
            font-size: 14px;
            margin: 30px 0;
        }
        
        .couple-names {
            font-weight: bold;
            text-decoration: underline;
        }
        
        .date-section {
            margin: 30px 0;
            font-size: 12px;
        }
        
        .pmc-info {
            text-align: left;
            font-size: 12px;
            margin-bottom: 20px;
        }
        
        .pmc-number {
            margin-bottom: 5px;
        }
        
        .year {
            margin-bottom: 10px;
        }
        
        .certification-text {
            margin: 40px 0;
            font-size: 12px;
            text-align: justify;
        }
        
        .signature-section {
            margin-top: 80px;
            text-align: center;
            position: relative;
            z-index: 10; /* Ensure edit form is above QR section */
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            width: 300px;
            margin: 30px auto 10px auto;
        }
        
        .signature-name {
            font-weight: bold;
            margin: 5px 0;
        }
        
        .signature-title {
            font-size: 12px;
            margin: 2px 0;
        }
        
        /* Global SweetAlert2 toast theme: light green background (matching system) */
        .swal2-popup.swal2-toast {
            background-color: #d4edda !important; /* light green */
            color: #155724 !important; /* dark green text */
            border: 1px solid #c3e6cb !important;
            box-shadow: 0 0 0 1px rgba(21, 87, 36, 0.05), 0 4px 12px rgba(21, 87, 36, 0.15) !important;
        }
        .swal2-popup.swal2-toast .swal2-title,
        .swal2-popup.swal2-toast .swal2-html-container {
            color: #155724 !important;
        }
        .swal2-popup.swal2-toast .swal2-timer-progress-bar {
            background: rgba(40, 167, 69, 0.6) !important; /* medium green */
        }
        
        @media print {
            body {
                padding: 0;
            }
            .certificate-container {
                border: none;
                padding: 15mm;
            }
            #editConductedBtn,
            #editConductedForm {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <?php if ($certificate_id): ?>
        <div class="qr-section">
            <img class="qr-frame" src="qr_generator.php?id=<?= $certificate_id ?>&size=300x300" alt="QR Code" />
            <div class="qr-caption">Scan to verify</div>
        </div>
        <?php endif; ?>
        <!-- PMC Certificate Number and Year (Left Side) -->
        <div class="pmc-info">
            <div class="pmc-number">
                PMC Certificate No. <?= $certificate['pmc_number'] ?>
            </div>
            <div class="year">
                Year: <?= date('Y', strtotime($certificate['issue_date'])) ?>
            </div>
        </div>
        
        <!-- Header with Logos -->
        <div class="header-logos">
            <img src="../images/City_of_Bago_Logo.png" alt="City of Bago Logo" class="logo">
            <div class="header-text">
                <p class="republic">Republic of the Philippines</p>
                <p class="province">Province of Negros Occidental</p>
                <p class="province">City of Bago</p>
                <p class="office-name">Bago City Population and Development Office</p>
            </div>
            <img src="<?= getSecureImagePath('../images/bcpdo.png') ?>" alt="BCPDO Logo" class="logo">
        </div>
        
        <!-- Certificate Title -->
        <div class="certificate-title">
            Certificate of Marriage Counseling
        </div>
        
        <!-- Certificate Body -->
        <div class="certificate-body">
            <p>This is to certify that <span class="couple-names"><?= strtoupper(htmlspecialchars($certificate['male_name'])) ?></span> and <span class="couple-names"><?= strtoupper(htmlspecialchars($certificate['female_name'])) ?></span> have undergone Pre - Marriage Counseling on <u><?= $certificate_id ? date('F d, Y', strtotime($certificate['issue_date'])) : '_____________________'; ?></u>.</p>
        </div>
        
        <!-- Certification Text -->
        <div class="certification-text">
            <p>This certification is issued as a pre - requisite for securing the marriage license of the above couple as provided for in Article 16 of the New Family Code.</p>
        </div>
        
        <!-- Signature Section -->
        <div class="signature-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <p style="margin: 0;"><strong>Conducted and Signed by:</strong></p>
                <button id="editConductedBtn" onclick="toggleEditConducted()" style="padding: 5px 15px; font-size: 12px; background-color: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; display: inline-block;">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
            
            <div id="conductedByDisplay">
                <div class="signature-line"></div>
                <div class="signature-name" id="conductedName">JESSA MAE T. GORANTES</div>
                <div class="signature-title" id="conductedTitle">Population Program Worker II</div>
                <div class="signature-title" id="conductedAccNo">Acc. no. DSWD – FOVI – AMC - <?= date('Y', strtotime($certificate['issue_date'])) ?> – <?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?></div>
            </div>
            
            <!-- Edit Form (hidden by default) -->
            <div id="editConductedForm" style="display: none; margin-top: 20px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; position: relative; z-index: 100;">
                <h4 style="margin-top: 0;">Edit Conducted By Information</h4>
                <div style="max-width: 400px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Full Name:</label>
                    <input type="text" id="person_name" placeholder="Full Name" style="width: 100%; padding: 5px; margin-bottom: 15px;">
                    
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Title/Position:</label>
                    <input type="text" id="person_title" placeholder="Title/Position" style="width: 100%; padding: 5px; margin-bottom: 15px;">
                    
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Accreditation Number:</label>
                    <input type="text" id="person_accno" placeholder="Acc. no. DSWD – FOVI – AMC - YYYY – NNN" style="width: 100%; padding: 5px; margin-bottom: 15px;">
                </div>
                <div style="margin-top: 15px; text-align: right; position: relative; z-index: 101;">
                    <button onclick="saveConductedBy()" style="padding: 8px 20px; background-color: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; margin-right: 10px; position: relative; z-index: 102;">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button onclick="cancelEditConducted()" style="padding: 8px 20px; background-color: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer; position: relative; z-index: 102;">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Floating Print Button (always visible, hidden only during print) -->
    <div class="print-button-container" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.print()" class="btn btn-primary" style="padding: 10px 20px; font-size: 16px; border-radius: 5px; border: none; background-color: #007bff; color: white; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
            <i class="fas fa-print"></i> Print Certificate
        </button>
    </div>
    <style>
        @media print { .print-button-container { display: none !important; } }
    </style>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Load saved conducted by data from localStorage
        function loadConductedByData() {
            const saved = localStorage.getItem('conductedBy_counseling');
            if (saved) {
                const data = JSON.parse(saved);
                updateConductedByDisplay(data);
            }
        }
        
        // Save conducted by data to localStorage
        function saveConductedByData(data) {
            localStorage.setItem('conductedBy_counseling', JSON.stringify(data));
        }
        
        // Update the display with conducted by data
        function updateConductedByDisplay(data) {
            if (data.name) {
                document.getElementById('conductedName').textContent = data.name;
            }
            if (data.title) {
                document.getElementById('conductedTitle').textContent = data.title;
            }
            if (data.accno) {
                document.getElementById('conductedAccNo').textContent = data.accno;
            }
        }
        
        // Toggle edit mode
        function toggleEditConducted() {
            const form = document.getElementById('editConductedForm');
            const btn = document.getElementById('editConductedBtn');
            
            if (form.style.display === 'none') {
                // Load current values into form
                const saved = localStorage.getItem('conductedBy_counseling');
                if (saved) {
                    const data = JSON.parse(saved);
                    document.getElementById('person_name').value = data.name || '';
                    document.getElementById('person_title').value = data.title || '';
                    document.getElementById('person_accno').value = data.accno || '';
                } else {
                    // Load default values
                    document.getElementById('person_name').value = 'JESSA MAE T. GORANTES';
                    document.getElementById('person_title').value = 'Population Program Worker II';
                    const currentYear = new Date().getFullYear();
                    const randomNum = String(Math.floor(Math.random() * 999) + 1).padStart(3, '0');
                    document.getElementById('person_accno').value = 'Acc. no. DSWD – FOVI – AMC - ' + currentYear + ' – ' + randomNum;
                }
                
                form.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-times"></i> Cancel';
                btn.style.backgroundColor = '#dc3545';
            } else {
                form.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-edit"></i> Edit';
                btn.style.backgroundColor = '#28a745';
            }
        }
        
        // Cancel edit
        function cancelEditConducted() {
            document.getElementById('editConductedForm').style.display = 'none';
            document.getElementById('editConductedBtn').innerHTML = '<i class="fas fa-edit"></i> Edit';
            document.getElementById('editConductedBtn').style.backgroundColor = '#28a745';
        }
        
        // Save conducted by data
        function saveConductedBy() {
            const data = {
                name: document.getElementById('person_name').value.trim(),
                title: document.getElementById('person_title').value.trim(),
                accno: document.getElementById('person_accno').value.trim()
            };
            
            saveConductedByData(data);
            updateConductedByDisplay(data);
            cancelEditConducted();
            
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Conducted by information saved!',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
        
        // Load conducted by data when page loads
        window.onload = function() {
            loadConductedByData();
            // Removed auto-print to allow editing before printing
        };
    </script>
</body>
</html>
