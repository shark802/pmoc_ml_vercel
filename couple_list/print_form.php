<?php
include('../includes/conn.php');
include('../includes/session.php');

$access_id = isset($_GET['access_id']) ? (int)$_GET['access_id'] : 0;
$sex_filter = isset($_GET['sex']) ? $_GET['sex'] : ''; // 'Male', 'Female'

$profiles = ['male' => null, 'female' => null];
// Filing metadata
$access_code_value = null;
$date_created_value = null;

if ($access_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM couple_profile LEFT JOIN address ON address.address_id = couple_profile.address_id WHERE access_id = ?");
        $stmt->bind_param("i", $access_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $profiles[$row['sex'] === 'Male' ? 'male' : 'female'] = $row;
        }
    } catch (Exception $e) {
        // Handle error silently or log
    }
    
    // Fetch access code and date of filing
    try {
        $stmt = $conn->prepare("SELECT access_code, date_created FROM couple_access WHERE access_id = ?");
        $stmt->bind_param("i", $access_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $access_code_value = $row['access_code'];
            $date_created_value = $row['date_created'];
        }
    } catch (Exception $e) {
        // Non-fatal
    }
}

function isChecked($actual, $expected) {
    $actual = trim($actual);
    $expected = trim($expected);
    
    // Direct match
    if ($actual === $expected) {
        return '✓';
    }
    
    // Handle monthly income format variations (with/without commas)
    // Remove commas for comparison
    $actualNoCommas = str_replace(',', '', $actual);
    $expectedNoCommas = str_replace(',', '', $expected);
    
    if ($actualNoCommas === $expectedNoCommas) {
        return '✓';
    }
    
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMOC Couples Profile Form</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; }
            @page { margin: 0.5in; size: legal portrait; }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.2;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .paper {
            background-color: white;
            width: 8.5in;
            margin: 0 auto;
            padding: 0.5in;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            box-sizing: border-box;
            margin-bottom: 20px;
        }
        .paper.paper-male {
            background-color: #e6f0ff; /* very light blue */
        }
        .paper.paper-female {
            background-color: #ffe6f0; /* very light pink */
        }
        
        /* HEADER STYLES */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid black;
            margin-bottom: 10px;
        }
        .header-table td {
            border: 1px solid black;
            padding: 0;
            vertical-align: middle;
        }
        .logo-cell {
            width: 25%;
            text-align: center;
            border-right: 1px solid black;
            padding: 5px !important;
        }
        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
        }
        .logo-img {
            width: 55px;
            height: auto;
        }
        .title-cell {
            text-align: center;
            padding: 10px !important;
            border-bottom: 1px solid black !important;
        }
        .main-title {
            font-family: "Times New Roman", Times, serif;
            font-weight: bold;
            font-size: 24px;
            margin: 0;
            text-transform: uppercase;
        }
        .meta-container {
            padding: 0 !important;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin: 0;
        }
        .meta-table td {
            border: none;
            border-right: 1px solid black;
            padding: 4px 5px;
            font-family: "Times New Roman", Times, serif;
            font-size: 12px;
            text-align: center;
            width: 33.33%;
        }
        .meta-table td:last-child {
            border-right: none;
        }

        /* PROFILE SECTION HEADERS */
        .profile-header-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-family: Arial, sans-serif;
            font-weight: bold;
            font-size: 12px;
        }
        .header-left-female {
            background-color: #ffe5cc; /* Peach */
            padding: 6px 10px;
            width: 38%;
            display: flex;
            align-items: center;
            border: 1px solid transparent; /* To maintain box model consistency if needed */
        }
        .header-left-male {
            background-color: #99c2ff; /* Light Blue */
            padding: 6px 10px;
            width: 38%;
            display: flex;
            align-items: center;
            border: 1px solid transparent; /* To maintain box model consistency if needed */
        }
        .header-right {
            background-color: #e6e6e6; /* Grey */
            padding: 6px 10px;
            width: 60%;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        /* FORM ELEMENTS */
        .section-title {
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .instruction {
            font-style: italic;
            font-size: 10px;
            margin-bottom: 10px;
        }
        .input-line {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 50px;
            padding-left: 5px;
            padding-right: 5px;
        }
        .checkbox-item {
            display: inline-block;
            margin-right: 15px;
        }
        .checkbox-box {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1px solid black;
            margin-right: 3px;
            vertical-align: middle;
            text-align: center;
            line-height: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        .row {
            display: flex;
            margin-bottom: 5px;
        }
        .col {
            flex: 1;
        }
        table.content-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.content-table td {
            vertical-align: top;
            padding: 2px 0;
        }
        .indent {
            margin-left: 20px;
        }
        .footer-note {
            font-size: 9px;
            font-style: italic;
            margin-top: 5px;
            border-top: 1px solid black;
            padding-top: 2px;
        }
        .btn-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .btn {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
        }
        .btn-back {
            background-color: #6c757d;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="btn-container no-print">
        <button onclick="window.print()" class="btn">Print Profile</button>
        <button onclick="history.back()" class="btn btn-back">Back</button>
    </div>

    <?php if (($sex_filter === 'Female' || empty($sex_filter)) && $profiles['female']): 
        $female = $profiles['female']; 
    ?>
    <div class="paper paper-female">
        <!-- HEADER -->
        <table class="header-table">
            <tr>
                <td class="logo-cell" rowspan="2">
                    <div class="logo-container">
                        <img src="../images/City_of_Bago_Logo1.png" class="logo-img" alt="City of Bago Logo">
                        <img src="../images/bcpdo.png" class="logo-img" alt="BCPDO Logo">
                        <img src="../images/popcom1.png" class="logo-img" alt="POPCOM Logo">
                    </div>
                </td>
                <td class="title-cell">
                    <h1 class="main-title">PMOC COUPLE’S PROFILE FORM</h1>
                </td>
            </tr>
            <tr>
                <td class="meta-container">
                    <table class="meta-table">
                        <tr>
                            <td>Form No. PMOC-CPF-001</td>
                            <td>Version No. 01</td>
                            <td>Effective: August 13, 2024</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- FEMALE APPLICANT HEADER -->
        <div class="profile-header-container">
            <div class="header-left-female">
                PMOC Female Applicant’s Profile
            </div>
            <div class="header-right">
                <span style="margin-right: 5px;">COUPLE NO.</span>
                <span class="input-line" style="width: 120px; margin-right: 10px;"></span>
                <span style="margin-right: 5px;">DATE OF FILING:</span>
                <span class="input-line" style="width: 120px;"><?= !empty($female['date_of_filing']) ? date('F j, Y', strtotime($female['date_of_filing'])) : ($date_created_value ? date('F j, Y', strtotime($date_created_value)) : date('F j, Y')) ?></span>
            </div>
        </div>

        <div class="instruction">
            INSTRUCTIONS: Please fill out the form. Kindly check the option that corresponds to your answer.
        </div>

        <table class="content-table">
            <tr>
                <td colspan="2">
                    <div style="display: flex; align-items: flex-start;">
                        <span style="margin-right: 5px; padding-top: 3px;">Name:</span>
                        <div style="text-align: center; margin-right: 5px;">
                            <span class="input-line" style="width: 140px; text-align: center;"><?= htmlspecialchars($female['first_name']) ?></span>
                            <div style="font-size: 10px;">First</div>
                        </div>
                        <div style="text-align: center; margin-right: 5px;">
                            <span class="input-line" style="width: 100px; text-align: center;"><?= htmlspecialchars($female['middle_name']) ?></span>
                            <div style="font-size: 10px;">Middle</div>
                        </div>
                        <div style="text-align: center; margin-right: 15px;">
                            <span class="input-line" style="width: 100px; text-align: center;"><?= htmlspecialchars($female['last_name']) ?></span>
                            <div style="font-size: 10px;">Last</div>
                        </div>
                        <span style="margin-right: 5px; padding-top: 3px;">Date of Birth:</span>
                        <div style="text-align: center;">
                            <span class="input-line" style="width: 150px; text-align: center;"><?= date('m/d/Y', strtotime($female['date_of_birth'])) ?></span>
                            <div style="font-size: 10px;">mm/dd/yyyy</div>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    Age: <span class="input-line" style="width: 50px;"><?= $female['age'] ?></span>
                    <span style="margin-left: 20px;">Email:</span> <span class="input-line" style="width: 250px;"><?= htmlspecialchars($female['email_address'] ?? '') ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    Address: <span class="input-line" style="width: 350px;"><?= htmlspecialchars(($female['purok'] ?? '') . ', ' . ($female['barangay'] ?? '') . ', ' . ($female['city'] ?? 'Bago City')) ?></span>
                    Contact No: <span class="input-line" style="width: 150px;"><?= htmlspecialchars($female['contact_number']) ?></span>
                </td>
            </tr>
            <tr>
                <td width="15%">Civil Status:</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['civil_status'], 'Single') ?></span> Single</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['civil_status'], 'Widow/er') ?></span> Widow/er</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['civil_status'], 'Separated') ?></span> Separated</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['civil_status'], 'Annulled/Divorced') ?></span> Annulled/Divorced</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['civil_status'], 'Living In') ?></span> Living-in</div>
                    If living in, indicate the number of years: <span class="input-line" style="width: 50px;"><?= ($female['civil_status'] === 'Living In') ? $female['years_living_together'] : '' ?></span>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>Indicate reason/s for living in: <span class="input-line" style="width: 300px;"><?= ($female['civil_status'] === 'Living In') ? htmlspecialchars($female['living_in_reason']) : '' ?></span></td>
            </tr>
        </table>

        <div style="margin-top: 5px;">Highest Educational Attainment:</div>
        <table class="content-table" style="margin-left: 20px;">
            <tr>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'No education') ?></span> No education</div></td>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'High School Graduate') ?></span> High School Graduate</div></td>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'College Level') ?></span> College Level</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'Pre-School') ?></span> Pre-School</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'Junior HS Level') ?></span> Junior HS Level</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'College Graduate') ?></span> College Graduate</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'Elementary Level') ?></span> Elementary Level</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'Junior HS Graduate') ?></span> Junior HS Graduate</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'Vocational/Technical') ?></span> Vocational/Technical</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'Elementary Graduate') ?></span> Elementary Graduate</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'Senior HS Level') ?></span> Senior HS Level</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'Alternative Learning System (ALS)') ?></span> Alternative Learning System (ALS)</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'High School Level') ?></span> High School Level</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'Senior HS Graduate') ?></span> Senior HS Graduate</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['education'], 'Post-Graduate') ?></span> Post-Graduate</div></td>
            </tr>
        </table>

        <div style="margin-top: 5px;">Religion:</div>
        <table class="content-table" style="margin-left: 20px;">
            <tr>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'Aglipay') ?></span> Aglipay</div></td>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'Iglesia ni Cristo') ?></span> Iglesia ni Cristo</div></td>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'Iglesia Filipina Independente') ?></span> Iglesia Filipina Independente</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'Bible Baptist Church') ?></span> Bible Baptist Church</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'Islam') ?></span> Islam</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'None') ?></span> None</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'Church of Christ') ?></span> Church of Christ</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'Roman Catholic') ?></span> Roman Catholic</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'United Church of Christ in the PH') ?></span> United Church of Christ in the PH</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'Jehova’s Witness') ?></span> Jehova’s Witness</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'Seventh Day Adventist') ?></span> Seventh Day Adventist</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['religion'], 'Other religious affiliations') ?></span> Other religious affiliations</div></td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td><span class="input-line" style="width: 150px;"><?= !empty($female['other_religion']) ? htmlspecialchars($female['other_religion']) : '' ?></span></td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td colspan="2">Nationality: <span class="input-line" style="width: 200px;"><?= htmlspecialchars($female['nationality'] ?? '') ?></span></td>
            </tr>
            <tr>
                <td colspan="2">Wedding Type: <span class="input-line" style="width: 200px;"><?= htmlspecialchars($female['wedding_type'] ?? '') ?></span></td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td>
                    Employment Status: 
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['employment_status'], 'Employed') ?></span> Employed</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['employment_status'], 'Unemployed') ?></span> Unemployed</div>
                    Occupation: <span class="input-line" style="width: 150px;"><?= htmlspecialchars($female['occupation']) ?></span>
                    Company: <span class="input-line" style="width: 100px;"></span>
                </td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td width="15%">Monthly Income:</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['monthly_income'], 'Below 5,000') ?></span> Below 5,000</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['monthly_income'], '10,000-14,999') ?></span> 10,000-14,999</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['monthly_income'], '20,000-24,999') ?></span> 20,000-24,999</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['monthly_income'], '5,000-9,999') ?></span> 5,000-9,999</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['monthly_income'], '15,000-19,999') ?></span> 15,000-19,999</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['monthly_income'], '25,000 and above') ?></span> 25,000 and above</div>
                </td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td>Have you heard of Family Planning?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['heard_fp'], 'Yes') ?></span> YES</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['heard_fp'], 'No') ?></span> NO</div>
                </td>
            </tr>
            <tr>
                <td class="indent">If YES, from what facility?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_facility'], 'Gov’t Hospital') ?></span> Gov’t Hospital</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_facility'], 'RHU') ?></span> RHU</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_facility'], 'BHS') ?></span> BHS</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_facility'], 'Private Hospital/Clinic') ?></span> Private Hospital/Clinic</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_facility'], 'Pharmacy') ?></span> Pharmacy</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_facility'], 'Others') ?></span> Others (specify): <span class="input-line" style="width: 150px;"><?= !empty($female['other_facility']) ? htmlspecialchars($female['other_facility']) : '' ?></span></div>
                </td>
            </tr>
            <tr>
                <td class="indent">If YES, from what channel?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_channel'], 'Health worker') ?></span> Health worker</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_channel'], 'Family/friends') ?></span> Family/friends</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_channel'], 'Health education') ?></span> Health education</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_channel'], 'Spouse/partner') ?></span> Spouse/partner</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_channel'], 'Radio/TV') ?></span> Radio/TV</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_channel'], 'School') ?></span> School</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_channel'], 'Internet/Social Media') ?></span> Internet/Social Media</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_channel'], 'Others') ?></span> Others (specify): <span class="input-line" style="width: 100px;"><?= !empty($female['other_channel']) ? htmlspecialchars($female['other_channel']) : '' ?></span></div>
                </td>
            </tr>
            <tr>
                <td colspan="2">If NO, why? <span class="input-line" style="width: 500px;"><?= htmlspecialchars($female['not_heard_reason']) ?></span></td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td>Do you intend to use Family Planning?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['intend_fp'], 'Yes') ?></span> YES</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['intend_fp'], 'No') ?></span> NO</div>
                </td>
            </tr>
            <tr>
                <td class="indent">If YES, what method?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_female_method'], 'Intra-Uterine Device (IUD)') ?></span> Intra-Uterine Device (IUD)</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_female_method'], 'Implant') ?></span> Implant</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_female_method'], 'Pills') ?></span> Pills</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_female_method'], 'Depot medroxyprogesterone acetate (DMPA) / Injectables') ?></span> Depot medroxyprogesterone acetate (DMPA) / Injectables</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_female_method'], 'Bilateral Tubal Ligation (BTL)') ?></span> Bilateral Tubal Ligation (BTL)</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_female_method'], 'Natural FP Methods') ?></span> Natural FP Methods (specify): <span class="input-line" style="width: 200px;"><?= !empty($female['female_other_method']) ? htmlspecialchars($female['female_other_method']) : '' ?></span></div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['fp_female_method'], 'Other methods') ?></span> Other methods (specify): <span class="input-line" style="width: 200px;"><?= !empty($female['female_other_method']) ? htmlspecialchars($female['female_other_method']) : '' ?></span></div>
                </td>
            </tr>
            <tr>
                <td colspan="2">If NO, why? <span class="input-line" style="width: 500px;"><?= htmlspecialchars($female['not_intend_reason']) ?></span></td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td>Are you currently pregnant?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['currently_pregnant'], 'Yes') ?></span> YES</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['currently_pregnant'], 'No') ?></span> NO</div>
                    If yes, age of gestation (months): <span class="input-line" style="width: 50px;"><?= $female['gestation_age'] ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2">If NO, when do you want to have your pregnancy? <span class="input-line" style="width: 300px;"><?= htmlspecialchars($female['pregnancy_plan']) ?></span></td>
            </tr>
            <tr>
                <td colspan="2">Desired number of children: <span class="input-line" style="width: 50px;"><?= $female['desired_children'] ?></span> Why? <span class="input-line" style="width: 300px;"><?= htmlspecialchars($female['children_reason']) ?></span></td>
            </tr>
            <tr>
                <td colspan="2">If none, why? <span class="input-line" style="width: 500px;"><?= htmlspecialchars($female['no_children_reason']) ?></span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="input-line" style="width: 560px;"></span></td>
            </tr>
            <tr>
                <td>Do you have children from past union/marriages?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= ($female['past_children_count'] > 0) ? '✓' : '' ?></span> YES</div>
                    If YES, No. of children: <span class="input-line" style="width: 50px;"><?= $female['past_children_count'] ?></span>
                    <div class="checkbox-item"><span class="checkbox-box"><?= ($female['past_children_count'] == 0) ? '✓' : '' ?></span> NO</div>
                </td>
            </tr>
            <tr>
                <td>Are you a PhilHealth member?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['philhealth_member'], 'Yes') ?></span> YES</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($female['philhealth_member'], 'No') ?></span> NO</div>
                </td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td colspan="2">Nationality: <span class="input-line" style="width: 480px;"><?= htmlspecialchars($female['nationality'] ?? '') ?></span></td>
            </tr>
            <tr>
                <td colspan="2">Wedding Type: <span class="input-line" style="width: 480px;"><?= htmlspecialchars($female['wedding_type'] ?? '') ?></span></td>
            </tr>
            <tr>
                <td colspan="2">Reasons for Marriage: <span class="input-line" style="width: 480px;"><?= htmlspecialchars($female['marriage_reasons'] ?? '') ?></span></td>
            </tr>
        </table>

        <div class="footer-note">
            NON-DISCLOSURE OF PERSONAL INFORMATION<br>
            The Commission on Population and Development (CPD) Region VI and its authorized representative will not disclose your personal
            information without your consent or in any situation not sanctioned by law or any lawful order of the court or government agencies
        </div>
    </div>
    <?php endif; ?>

    <?php if (($sex_filter === 'Male' || empty($sex_filter)) && $profiles['male']): 
        $male = $profiles['male'];
    ?>
    <div class="paper paper-male">
        <!-- HEADER FOR MALE -->
        <table class="header-table">
            <tr>
                <td class="logo-cell" rowspan="2">
                    <div class="logo-container">
                        <img src="../images/City_of_Bago_Logo1.png" class="logo-img" alt="City of Bago Logo">
                        <img src="../images/bcpdo.png" class="logo-img" alt="BCPDO Logo">
                        <img src="../images/popcom1.png" class="logo-img" alt="POPCOM Logo">
                    </div>
                </td>
                <td class="title-cell">
                    <h1 class="main-title">PMOC COUPLE’S PROFILE FORM</h1>
                </td>
            </tr>
            <tr>
                <td class="meta-container">
                    <table class="meta-table">
                        <tr>
                            <td>Form No. PMOC-CPF-001</td>
                            <td>Version No. 01</td>
                            <td>Effective: August 13, 2024</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- MALE APPLICANT HEADER -->
        <div class="profile-header-container">
            <div class="header-left-male">
                PMOC Male Applicant’s Profile
            </div>
            <div class="header-right">
                <span style="margin-right: 5px;">COUPLE NO.</span>
                <span class="input-line" style="width: 120px; margin-right: 10px;"></span>
                <span style="margin-right: 5px;">DATE OF FILING:</span>
                <span class="input-line" style="width: 120px;"><?= !empty($male['date_of_filing']) ? date('F j, Y', strtotime($male['date_of_filing'])) : ($date_created_value ? date('F j, Y', strtotime($date_created_value)) : date('F j, Y')) ?></span>
            </div>
        </div>

        <div class="instruction">
            INSTRUCTIONS: Please fill out the form. Kindly check the option that corresponds to your answer.
        </div>

        <table class="content-table">
            <tr>
                <td colspan="2">
                    <div style="display: flex; align-items: flex-start;">
                        <span style="margin-right: 5px; padding-top: 3px;">Name:</span>
                        <div style="text-align: center; margin-right: 5px;">
                            <span class="input-line" style="width: 140px; text-align: center;"><?= htmlspecialchars($male['first_name']) ?></span>
                            <div style="font-size: 10px;">First</div>
                        </div>
                        <div style="text-align: center; margin-right: 5px;">
                            <span class="input-line" style="width: 100px; text-align: center;"><?= htmlspecialchars($male['middle_name']) ?></span>
                            <div style="font-size: 10px;">Middle</div>
                        </div>
                        <div style="text-align: center; margin-right: 15px;">
                            <span class="input-line" style="width: 100px; text-align: center;"><?= htmlspecialchars($male['last_name']) ?></span>
                            <div style="font-size: 10px;">Last</div>
                        </div>
                        <span style="margin-right: 5px; padding-top: 3px;">Date of Birth:</span>
                        <div style="text-align: center;">
                            <span class="input-line" style="width: 150px; text-align: center;"><?= date('m/d/Y', strtotime($male['date_of_birth'])) ?></span>
                            <div style="font-size: 10px;">mm/dd/yyyy</div>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    Age: <span class="input-line" style="width: 50px;"><?= $male['age'] ?></span>
                    <span style="margin-left: 20px;">Email:</span> <span class="input-line" style="width: 250px;"><?= htmlspecialchars($male['email_address'] ?? '') ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    Address: <span class="input-line" style="width: 350px;"><?= htmlspecialchars(($male['purok'] ?? '') . ', ' . ($male['barangay'] ?? '') . ', ' . ($male['city'] ?? 'Bago City')) ?></span>
                    Contact No: <span class="input-line" style="width: 150px;"><?= htmlspecialchars($male['contact_number']) ?></span>
                </td>
            </tr>
            <tr>
                <td width="15%">Civil Status:</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['civil_status'], 'Single') ?></span> Single</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['civil_status'], 'Widowed') ?></span> Widowed</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['civil_status'], 'Separated') ?></span> Separated</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['civil_status'], 'Annulled/Divorced') ?></span> Annulled/Divorced</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['civil_status'], 'Living In') ?></span> Living-in</div>
                    If living in, indicate the number of years: <span class="input-line" style="width: 50px;"><?= ($male['civil_status'] === 'Living In') ? $male['years_living_together'] : '' ?></span>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>Indicate reason/s for living in: <span class="input-line" style="width: 300px;"><?= ($male['civil_status'] === 'Living In') ? htmlspecialchars($male['living_in_reason']) : '' ?></span></td>
            </tr>
        </table>

        <div style="margin-top: 5px;">Highest Educational Attainment:</div>
        <table class="content-table" style="margin-left: 20px;">
            <tr>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'No education') ?></span> No education</div></td>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'High School Graduate') ?></span> High School Graduate</div></td>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'College Level') ?></span> College Level</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'Pre-School') ?></span> Pre-School</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'Junior HS Level') ?></span> Junior HS Level</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'College Graduate') ?></span> College Graduate</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'Elementary Level') ?></span> Elementary Level</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'Junior HS Graduate') ?></span> Junior HS Graduate</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'Vocational/Technical') ?></span> Vocational/Technical</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'Elementary Graduate') ?></span> Elementary Graduate</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'Senior HS Level') ?></span> Senior HS Level</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'Alternative Learning System (ALS)') ?></span> Alternative Learning System (ALS)</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'High School Level') ?></span> High School Level</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'Senior HS Graduate') ?></span> Senior HS Graduate</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['education'], 'Post-Graduate') ?></span> Post-Graduate</div></td>
            </tr>
        </table>

        <div style="margin-top: 5px;">Religion:</div>
        <table class="content-table" style="margin-left: 20px;">
            <tr>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'Aglipay') ?></span> Aglipay</div></td>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'Iglesia ni Cristo') ?></span> Iglesia ni Cristo</div></td>
                <td width="33%"><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'Iglesia Filipina Independente') ?></span> Iglesia Filipina Independente</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'Bible Baptist Church') ?></span> Bible Baptist Church</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'Islam') ?></span> Islam</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'None') ?></span> None</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'Church of Christ') ?></span> Church of Christ</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'Roman Catholic') ?></span> Roman Catholic</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'United Church of Christ in the PH') ?></span> United Church of Christ in the PH</div></td>
            </tr>
            <tr>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'Jehova’s Witness') ?></span> Jehova’s Witness</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'Seventh Day Adventist') ?></span> Seventh Day Adventist</div></td>
                <td><div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['religion'], 'Other religious affiliations') ?></span> Other religious affiliations</div></td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td><span class="input-line" style="width: 150px;"><?= !empty($male['other_religion']) ? htmlspecialchars($male['other_religion']) : '' ?></span></td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td>
                    Employment Status: 
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['employment_status'], 'Employed') ?></span> Employed</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['employment_status'], 'Unemployed') ?></span> Unemployed</div>
                    Occupation: <span class="input-line" style="width: 150px;"><?= htmlspecialchars($male['occupation']) ?></span>
                    Company: <span class="input-line" style="width: 100px;"></span>
                </td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td width="15%">Monthly Income:</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['monthly_income'], 'Below 5,000') ?></span> Below 5,000</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['monthly_income'], '10,000-14,999') ?></span> 10,000-14,999</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['monthly_income'], '20,000-24,999') ?></span> 20,000-24,999</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['monthly_income'], '5,000-9,999') ?></span> 5,000-9,999</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['monthly_income'], '15,000-19,999') ?></span> 15,000-19,999</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['monthly_income'], '25,000 and above') ?></span> 25,000 and above</div>
                </td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td>Have you heard of Family Planning?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['heard_fp'], 'Yes') ?></span> YES</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['heard_fp'], 'No') ?></span> NO</div>
                </td>
            </tr>
            <tr>
                <td class="indent">If YES, from what facility?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_facility'], 'Gov’t Hospital') ?></span> Gov’t Hospital</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_facility'], 'RHU') ?></span> RHU</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_facility'], 'BHS') ?></span> BHS</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_facility'], 'Private Hospital/Clinic') ?></span> Private Hospital/Clinic</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_facility'], 'Pharmacy') ?></span> Pharmacy</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_facility'], 'Others') ?></span> Others (specify): <span class="input-line" style="width: 150px;"><?= !empty($male['other_facility']) ? htmlspecialchars($male['other_facility']) : '' ?></span></div>
                </td>
            </tr>
            <tr>
                <td class="indent">If YES, from what channel?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_channel'], 'Health worker') ?></span> Health worker</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_channel'], 'Family/friends') ?></span> Family/friends</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_channel'], 'Health education') ?></span> Health education</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_channel'], 'Spouse/partner') ?></span> Spouse/partner</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_channel'], 'Radio/TV') ?></span> Radio/TV</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_channel'], 'School') ?></span> School</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_channel'], 'Internet/Social Media') ?></span> Internet/Social Media</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_channel'], 'Others') ?></span> Others (specify): <span class="input-line" style="width: 100px;"><?= !empty($male['other_channel']) ? htmlspecialchars($male['other_channel']) : '' ?></span></div>
                </td>
            </tr>
            <tr>
                <td colspan="2">If NO, why? <span class="input-line" style="width: 500px;"><?= htmlspecialchars($male['not_heard_reason']) ?></span></td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td>Do you intend to use Family Planning?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['intend_fp'], 'Yes') ?></span> YES</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['intend_fp'], 'No') ?></span> NO</div>
                </td>
            </tr>
            <tr>
                <td class="indent">If YES, what method?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_male_method'], 'Condom') ?></span> Condom</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_male_method'], 'Vasectomy') ?></span> Vasectomy</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_male_method'], 'Natural FP Methods') ?></span> Natural FP Methods (specify): <span class="input-line" style="width: 200px;"><?= !empty($male['male_other_method']) ? htmlspecialchars($male['male_other_method']) : '' ?></span></div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['fp_male_method'], 'Others') ?></span> Others (specify): <span class="input-line" style="width: 200px;"><?= !empty($male['male_other_method']) ? htmlspecialchars($male['male_other_method']) : '' ?></span></div>
                </td>
            </tr>
            <tr>
                <td colspan="2">If NO, why? <span class="input-line" style="width: 500px;"><?= htmlspecialchars($male['not_intend_reason']) ?></span></td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td colspan="2">Desired number of children: <span class="input-line" style="width: 50px;"><?= $male['desired_children'] ?></span> Why? <span class="input-line" style="width: 300px;"><?= htmlspecialchars($male['children_reason']) ?></span></td>
            </tr>
            <tr>
                <td colspan="2">If none, why? <span class="input-line" style="width: 500px;"><?= htmlspecialchars($male['no_children_reason']) ?></span></td>
            </tr>
            <tr>
                <td>Do you have children from past union/marriages?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= ($male['past_children_count'] > 0) ? '✓' : '' ?></span> YES</div>
                    If YES, No. of children: <span class="input-line" style="width: 50px;"><?= $male['past_children_count'] ?></span>
                    <div class="checkbox-item"><span class="checkbox-box"><?= ($male['past_children_count'] == 0) ? '✓' : '' ?></span> NO</div>
                </td>
            </tr>
            <tr>
                <td>Are you a PhilHealth member?</td>
                <td>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['philhealth_member'], 'Yes') ?></span> YES</div>
                    <div class="checkbox-item"><span class="checkbox-box"><?= isChecked($male['philhealth_member'], 'No') ?></span> NO</div>
                </td>
            </tr>
        </table>

        <table class="content-table" style="margin-top: 5px;">
            <tr>
                <td colspan="2">Nationality: <span class="input-line" style="width: 480px;"><?= htmlspecialchars($male['nationality'] ?? '') ?></span></td>
            </tr>
            <tr>
                <td colspan="2">Wedding Type: <span class="input-line" style="width: 480px;"><?= htmlspecialchars($male['wedding_type'] ?? '') ?></span></td>
            </tr>
            <tr>
                <td colspan="2">Reasons for Marriage: <span class="input-line" style="width: 480px;"><?= htmlspecialchars($male['marriage_reasons'] ?? '') ?></span></td>
            </tr>
        </table>

        <div class="footer-note">
            NON-DISCLOSURE OF PERSONAL INFORMATION<br>
            The Commission on Population and Development (CPD) Region VI and its authorized representative will not disclose your personal
            information without your consent or in any situation not sanctioned by law or any lawful order of the court or government agencies
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
