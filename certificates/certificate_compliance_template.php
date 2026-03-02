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
        'male_age' => '___',
        'female_age' => '___',
        'issue_date' => date('Y-m-d'),
        'certificate_number' => 'YYYY-MM-ORI-NNN'
    ];
} else {
    try {
    // Get certificate data with couple information and addresses
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
            TIMESTAMPDIFF(YEAR, mp.date_of_birth, CURDATE()) as male_age,
            TIMESTAMPDIFF(YEAR, fp.date_of_birth, CURDATE()) as female_age,
            CONCAT('Brgy. ', COALESCE(ma.barangay, ''), ', ', 
                   CASE 
                       WHEN ma.city LIKE '%City' THEN COALESCE(ma.city, '')
                       ELSE CONCAT(COALESCE(ma.city, ''), ' City')
                   END) as male_address,
            CONCAT('Brgy. ', COALESCE(fa.barangay, ''), ', ', 
                   CASE 
                       WHEN fa.city LIKE '%City' THEN COALESCE(fa.city, '')
                       ELSE CONCAT(COALESCE(fa.city, ''), ' City')
                   END) as female_address
        FROM certificates c
        LEFT JOIN couple_access ca ON c.access_id = ca.access_id
        LEFT JOIN couple_profile mp ON ca.access_id = mp.access_id AND mp.sex = 'Male'
        LEFT JOIN couple_profile fp ON ca.access_id = fp.access_id AND fp.sex = 'Female'
        LEFT JOIN address ma ON mp.address_id = ma.address_id
        LEFT JOIN address fa ON fp.address_id = fa.address_id
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
    <title>Certificate of Compliance | BCPDO System</title>
    
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
            text-decoration: underline;
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
            text-align: right;
        }
        
        .conducted-by {
            margin: 40px 0;
            position: relative;
            z-index: 10; /* Ensure edit form is above QR section */
        }
        
        .signature-section {
            margin-top: 60px;
        }
        
        .issued-by {
            margin-top: 40px;
            text-align: center;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            width: 300px;
            margin: 20px auto;
        }
        
        .cert-number {
            position: absolute;
            bottom: 20mm;
            right: 20mm;
            font-size: 12px;
        }
        
        .conducted-person {
            min-height: 80px;
        }
        
        .conducted-name {
            margin-bottom: 5px;
        }
        
        .conducted-title {
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .conducted-office {
            font-size: 12px;
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
        <!-- Header with Logos -->
        <div class="header-logos">
            <img src="../images/City_of_Bago_Logo.png" alt="City of Bago Logo" class="logo">
            <div class="header-text">
                <p class="republic">Republic of the Philippines</p>
                <p class="province">Province of Negros Occidental</p>
                <p class="province">City of Bago</p>
                <p class="office-name">CITY POPULATION AND DEVELOPMENT OFFICE</p>
            </div>
            <img src="<?= getSecureImagePath('../images/bcpdo.png') ?>" alt="BCPDO Logo" class="logo">
        </div>
        
        <!-- Date -->
        <div class="date-section">
            Date: <?= date('F d, Y', strtotime($certificate['issue_date'])) ?>
        </div>
        
        <!-- Certificate Title -->
        <div class="certificate-title">
            CERTIFICATE OF COMPLIANCE
        </div>
        
        <!-- Certificate Body -->
        <div class="certificate-body">
            <p>This is to certify that Mr. <span class="couple-names"><?= strtoupper(htmlspecialchars($certificate['male_name'])) ?></span> age <u><?= $certificate['male_age'] ?? '___' ?></u> of <u><?= htmlspecialchars($certificate['male_address'] ?? '_____________________') ?></u> and Ms. <span class="couple-names"><?= strtoupper(htmlspecialchars($certificate['female_name'])) ?></span> age <u><?= $certificate['female_age'] ?? '___' ?></u> of <u><?= htmlspecialchars($certificate['female_address'] ?? '_____________________') ?></u> have completed the Pre-Marriage Orientation (PMO) session, in accordance with Section 15 of RA 10354. This certificate will be valid until the issuance of the marriage license.</p>
        </div>
        
        <!-- Conducted By Section -->
        <div class="conducted-by">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <strong>Conducted by:</strong>
                <button id="editConductedBtn" onclick="toggleEditConducted()" style="padding: 5px 15px; font-size: 12px; background-color: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; display: inline-block;">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
            <br>
            
            <table style="width: 100%; border-collapse: collapse;" id="conductedByTable">
                <tr>
                    <td style="width: 33%; text-align: center; vertical-align: top;">
                        <div class="conducted-person" data-person="1">
                            <div class="conducted-name"><strong>JONA V. EMILLA, RSW</strong></div>
                            <div class="conducted-title">Social Welfare Officer II</div>
                            <div class="conducted-office">City Social Welfare and<br>Development Office</div>
                        </div>
                    </td>
                    <td style="width: 33%; text-align: center; vertical-align: top;">
                        <div class="conducted-person" data-person="2">
                            <div class="conducted-name"><strong>JESSA MAE T. GORANTES</strong></div>
                            <div class="conducted-title">Population Program Worker II</div>
                            <div class="conducted-office">City Population and<br>Development Office</div>
                        </div>
                    </td>
                    <td style="width: 33%; text-align: center; vertical-align: top;">
                        <div class="conducted-person" data-person="3">
                            <div class="conducted-name"><strong>DOANNE M. HIPONIA, RN</strong></div>
                            <div class="conducted-title">Nurse I</div>
                            <div class="conducted-office">City Health Office</div>
                        </div>
                    </td>
                </tr>
            </table>
            
            <!-- Edit Form (hidden by default) -->
            <div id="editConductedForm" style="display: none; margin-top: 20px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; position: relative; z-index: 100;">
                <h4 style="margin-top: 0;">Edit Conducted By Information</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Person 1:</label>
                        <input type="text" id="person1_name" placeholder="Full Name" style="width: 100%; padding: 5px; margin-bottom: 5px;">
                        <input type="text" id="person1_title" placeholder="Title/Position" style="width: 100%; padding: 5px; margin-bottom: 5px;">
                        <textarea id="person1_office" placeholder="Office/Department" style="width: 100%; padding: 5px; min-height: 50px;"></textarea>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Person 2:</label>
                        <input type="text" id="person2_name" placeholder="Full Name" style="width: 100%; padding: 5px; margin-bottom: 5px;">
                        <input type="text" id="person2_title" placeholder="Title/Position" style="width: 100%; padding: 5px; margin-bottom: 5px;">
                        <textarea id="person2_office" placeholder="Office/Department" style="width: 100%; padding: 5px; min-height: 50px;"></textarea>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Person 3:</label>
                        <input type="text" id="person3_name" placeholder="Full Name" style="width: 100%; padding: 5px; margin-bottom: 5px;">
                        <input type="text" id="person3_title" placeholder="Title/Position" style="width: 100%; padding: 5px; margin-bottom: 5px;">
                        <textarea id="person3_office" placeholder="Office/Department" style="width: 100%; padding: 5px; min-height: 50px;"></textarea>
                    </div>
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
        
        <!-- Issued By Section -->
        <div class="issued-by">
            <strong>Issued by:</strong><br><br>
            
            <div class="signature-line"></div>
            <strong>ANN MARIE D. TORRES, LPT, MPsych, MSCJE</strong><br>
            Population Program Officer IV<br>
            City Population and Development Office<br>
            PMOC Team Leader
        </div>
        
        <!-- Certificate Number -->
        <div class="cert-number">
            Cert. No. <?= htmlspecialchars($certificate['certificate_number']) ?>
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
            const saved = localStorage.getItem('conductedBy_compliance');
            if (saved) {
                const data = JSON.parse(saved);
                updateConductedByDisplay(data);
            }
        }
        
        // Save conducted by data to localStorage
        function saveConductedByData(data) {
            localStorage.setItem('conductedBy_compliance', JSON.stringify(data));
        }
        
        // Update the display with conducted by data
        function updateConductedByDisplay(data) {
            for (let i = 1; i <= 3; i++) {
                const person = document.querySelector(`.conducted-person[data-person="${i}"]`);
                if (person && data[`person${i}`]) {
                    const p = data[`person${i}`];
                    person.querySelector('.conducted-name').innerHTML = `<strong>${p.name || ''}</strong>`;
                    person.querySelector('.conducted-title').textContent = p.title || '';
                    person.querySelector('.conducted-office').innerHTML = p.office || '';
                }
            }
        }
        
        // Toggle edit mode
        function toggleEditConducted() {
            const form = document.getElementById('editConductedForm');
            const btn = document.getElementById('editConductedBtn');
            
            if (form.style.display === 'none') {
                // Load current values into form
                const saved = localStorage.getItem('conductedBy_compliance');
                if (saved) {
                    const data = JSON.parse(saved);
                    for (let i = 1; i <= 3; i++) {
                        if (data[`person${i}`]) {
                            document.getElementById(`person${i}_name`).value = data[`person${i}`].name || '';
                            document.getElementById(`person${i}_title`).value = data[`person${i}`].title || '';
                            document.getElementById(`person${i}_office`).value = data[`person${i}`].office || '';
                        }
                    }
                } else {
                    // Load default values
                    const defaults = {
                        person1: { name: 'JONA V. EMILLA, RSW', title: 'Social Welfare Officer II', office: 'City Social Welfare and\nDevelopment Office' },
                        person2: { name: 'JESSA MAE T. GORANTES', title: 'Population Program Worker II', office: 'City Population and\nDevelopment Office' },
                        person3: { name: 'DOANNE M. HIPONIA, RN', title: 'Nurse I', office: 'City Health Office' }
                    };
                    for (let i = 1; i <= 3; i++) {
                        const p = defaults[`person${i}`];
                        document.getElementById(`person${i}_name`).value = p.name;
                        document.getElementById(`person${i}_title`).value = p.title;
                        document.getElementById(`person${i}_office`).value = p.office;
                    }
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
            const data = {};
            for (let i = 1; i <= 3; i++) {
                data[`person${i}`] = {
                    name: document.getElementById(`person${i}_name`).value.trim(),
                    title: document.getElementById(`person${i}_title`).value.trim(),
                    office: document.getElementById(`person${i}_office`).value.trim()
                };
            }
            
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
