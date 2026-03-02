<?php
session_start();

// Strict session validation before any output
if (!isset($_SESSION['access_id'], $_SESSION['respondent'], $_SESSION['access_code'])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Validate respondent type
if (!in_array($_SESSION['respondent'], ['male', 'female'])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?error=invalid_respondent");
    exit();
}

// Now safely access session variables
$access_id = (int)$_SESSION['access_id'];
$respondent = $_SESSION['respondent'];
$access_code = $_SESSION['access_code'];
$partnerSubmitted = $_SESSION['partner_submitted'] ?? false;



// Set form styling based on respondent type
$formColorClass = ($respondent === 'male') ? 'male-form' : 'female-form';
$formTitle = ($respondent === 'male') ? 'Male Profile Form' : 'Female Profile Form';

require_once '../includes/conn.php';

// Check if profile already submitted
$checkProfileStmt = $conn->prepare("
    SELECT {$respondent}_profile_submitted 
    FROM couple_access 
    WHERE access_id = ?
");
$checkProfileStmt->bind_param("i", $access_id);
$checkProfileStmt->execute();
$profileResult = $checkProfileStmt->get_result()->fetch_assoc();

if ($profileResult && $profileResult["{$respondent}_profile_submitted"]) {
    // Profile already completed, redirect to questionnaire
    header("Location: ../questionnaire/questionnaire.php");
    exit();
}

// Check if resuming session
$isResuming = false;
$savedProgress = null;
include '../includes/header.php';
include '../includes/scripts.php';

// Local draft recovery: auto-save and restore profile inputs per access code and respondent
?>
<script>
(function() {
  const accessCode = <?= json_encode($access_code) ?>;
  const respondent = <?= json_encode($respondent) ?>;
  const draftKey = `profileDraft:${accessCode}:${respondent}`;

  function getForm() {
    // Prefer the main profile form if identifiable
    return (
      document.querySelector("form[action*='couple_profile.php']") ||
      document.getElementById('profileForm') ||
      document.querySelector('form')
    );
  }

  function collectFormData(form) {
    const data = {};
    if (!form) return data;
    const fields = form.querySelectorAll('input[name], select[name], textarea[name]');
    fields.forEach(el => {
      const name = el.name;
      if (!name) return;
      if (el.type === 'password') return;
      if ((el.type === 'checkbox' || el.type === 'radio')) {
        if (el.checked) data[name] = el.value;
        return;
      }
      data[name] = el.value;
    });
    return data;
  }

  function applyDraft(form, draft) {
    if (!form || !draft) return;
    const fields = form.querySelectorAll('input[name], select[name], textarea[name]');
    fields.forEach(el => {
      const name = el.name;
      if (!name || !(name in draft)) return;
      const val = draft[name];
      if (el.type === 'checkbox' || el.type === 'radio') {
        if (el.value == val) el.checked = true;
      } else {
        el.value = val;
      }
      // Fire change event so dependent widgets react
      const evt = new Event('change', { bubbles: true });
      el.dispatchEvent(evt);
    });
  }

  function saveDraft() {
    const form = getForm();
    if (!form) return;
    const data = collectFormData(form);
    try { localStorage.setItem(draftKey, JSON.stringify(data)); } catch (e) {}
  }

  function loadDraft() {
    try {
      const raw = localStorage.getItem(draftKey);
      if (!raw) return;
      const draft = JSON.parse(raw);
      applyDraft(getForm(), draft);
    } catch (e) {}
  }

  function clearDraft() {
    try { localStorage.removeItem(draftKey); } catch (e) {}
  }

  let saveTimer = null;
  document.addEventListener('DOMContentLoaded', function() {
    loadDraft();
    const form = getForm();
    if (!form) return;

    form.addEventListener('input', function() {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(saveDraft, 800);
    });
    form.addEventListener('change', function() {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(saveDraft, 400);
    });
    // Save right before page unload to catch last-second edits
    window.addEventListener('beforeunload', function() {
      saveDraft();
    });

    form.addEventListener('submit', function() {
      clearTimeout(saveTimer);
      clearDraft();
    });

      // Address-specific recovery for Bago residents: wait for barangay options, then set
      try {
        const draftRaw = localStorage.getItem(draftKey);
        const draft = draftRaw ? JSON.parse(draftRaw) : null;

        // Ensure residency_type control is set BEFORE address population
        if (draft && draft['residency_type']) {
          const residencySelect = form.querySelector("select[name='residency_type'], input[name='residency_type']");
          if (residencySelect) {
            if (residencySelect.tagName === 'SELECT') {
              residencySelect.value = draft['residency_type'];
              residencySelect.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
              // radios/inputs named residency_type
              const radios = form.querySelectorAll("input[name='residency_type']");
              radios.forEach(r => { r.checked = (r.value === draft['residency_type']); });
              radios.forEach(r => r.dispatchEvent(new Event('change', { bubbles: true })));
            }
          }
        }
        
        // Store draft data globally for later use in initialization
        window.savedDraft = draft;
        
        if (draft && draft['residency_type'] === 'bago') {
          const barangaySelect = form.querySelector("select[name='barangay']");
          const purokInput = form.querySelector("input[name='purok']");
          let attempts = 0;
          const maxAttempts = 30; // Increased attempts
          const timer = setInterval(() => {
            attempts++;
            if (!barangaySelect) { clearInterval(timer); return; }
            // Ensure options are loaded (beyond placeholder)
            if (barangaySelect.options && barangaySelect.options.length > 1) {
              if (draft['barangay']) {
                // Try exact value match first, else try text match
                let setOk = false;
                for (let i = 0; i < barangaySelect.options.length; i++) {
                  const opt = barangaySelect.options[i];
                  if (opt.value === draft['barangay'] || opt.text.trim() === String(draft['barangay']).trim()) {
                    barangaySelect.value = opt.value;
                    barangaySelect.dispatchEvent(new Event('change', { bubbles: true }));
                    setOk = true;
                    break;
                  }
                }
                if (!setOk && draft['barangay']) {
                  // Fallback: set value directly
                  barangaySelect.value = draft['barangay'];
                  barangaySelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
              }
              if (purokInput && draft['purok']) {
                purokInput.value = draft['purok'];
                purokInput.dispatchEvent(new Event('input', { bubbles: true }));
                purokInput.dispatchEvent(new Event('change', { bubbles: true }));
              }
              clearInterval(timer);
            }
            if (attempts >= maxAttempts) clearInterval(timer);
          }, 100);
        }
      // Address-specific recovery for Non-Bago residents: chain city -> barangay, then set purok
      if (draft && draft['residency_type'] === 'non-bago') {
        const cityField = form.querySelector("select[name='non_bago_city'], input[name='non_bago_city']");
        const barangayField = form.querySelector("select[name='non_bago_barangay'], input[name='non_bago_barangay']");
        const purokInput = form.querySelector("input[name='non_bago_purok']");
        let cityAttempts = 0;
        const maxAttempts = 30; // allow a bit longer
        const cityTimer = setInterval(() => {
          cityAttempts++;
          if (!cityField) { clearInterval(cityTimer); return; }
          // If it's a SELECT, wait for options; if INPUT, set immediately
          if (cityField.tagName === 'SELECT') {
            if (cityField.options && cityField.options.length > 1) {
              if (draft['non_bago_city']) {
                let setCity = false;
                for (let i = 0; i < cityField.options.length; i++) {
                  const opt = cityField.options[i];
                  if (opt.value === draft['non_bago_city'] || opt.text.trim() === String(draft['non_bago_city']).trim()) {
                    cityField.value = opt.value;
                    cityField.dispatchEvent(new Event('change', { bubbles: true }));
                    setCity = true;
                    break;
                  }
                }
                if (!setCity) {
                  cityField.value = draft['non_bago_city'];
                  cityField.dispatchEvent(new Event('change', { bubbles: true }));
                }
              }
              clearInterval(cityTimer);
            }
          } else {
            // Input
            if (draft['non_bago_city']) {
              cityField.value = draft['non_bago_city'];
              cityField.dispatchEvent(new Event('input', { bubbles: true }));
              cityField.dispatchEvent(new Event('change', { bubbles: true }));
            }
            clearInterval(cityTimer);
            // After city set, proceed to barangay
            let brgyAttempts = 0;
            const brgyTimer = setInterval(() => {
              brgyAttempts++;
              if (!barangayField) { clearInterval(brgyTimer); return; }
              if (barangayField.tagName === 'SELECT') {
                if (barangayField.options && barangayField.options.length > 1) {
                  if (draft['non_bago_barangay']) {
                    let setBrgy = false;
                    for (let i = 0; i < barangayField.options.length; i++) {
                      const opt = barangayField.options[i];
                      if (opt.value === draft['non_bago_barangay'] || opt.text.trim() === String(draft['non_bago_barangay']).trim()) {
                        barangayField.value = opt.value;
                        barangayField.dispatchEvent(new Event('change', { bubbles: true }));
                        setBrgy = true;
                        break;
                      }
                    }
                    if (!setBrgy) {
                      barangayField.value = draft['non_bago_barangay'];
                      barangayField.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                  }
                  if (purokInput && draft['non_bago_purok']) {
                    purokInput.value = draft['non_bago_purok'];
                    purokInput.dispatchEvent(new Event('input', { bubbles: true }));
                    purokInput.dispatchEvent(new Event('change', { bubbles: true }));
                  }
                  clearInterval(brgyTimer);
                }
              } else {
                // Input
                if (draft['non_bago_barangay']) {
                  barangayField.value = draft['non_bago_barangay'];
                  barangayField.dispatchEvent(new Event('input', { bubbles: true }));
                  barangayField.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (purokInput && draft['non_bago_purok']) {
                  purokInput.value = draft['non_bago_purok'];
                  purokInput.dispatchEvent(new Event('input', { bubbles: true }));
                  purokInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                clearInterval(brgyTimer);
              }
              if (brgyAttempts >= maxAttempts) clearInterval(brgyTimer);
            }, 120);
          }
          if (cityAttempts >= maxAttempts) clearInterval(cityTimer);
        }, 120);
      }
      // Address-specific recovery for Foreigners
      if (draft && draft['residency_type'] === 'foreigner') {
        const countryField = form.querySelector("select[name='foreigner_country'], input[name='foreigner_country']");
        const stateField = form.querySelector("select[name='foreigner_state'], input[name='foreigner_state']");
        const cityFieldF = form.querySelector("select[name='foreigner_city'], input[name='foreigner_city']");
        // Some UIs load country/state lists asynchronously, so try a few times
        let tries = 0;
        const max = 30;
        const setForeign = setInterval(() => {
          tries++;
          const setField = (field, value) => {
            if (!field || !value) return false;
            if (field.tagName === 'SELECT') {
              if (field.options && field.options.length > 0) {
                let matched = false;
                for (let i = 0; i < field.options.length; i++) {
                  const opt = field.options[i];
                  if (opt.value === value || opt.text.trim() === String(value).trim()) {
                    field.value = opt.value;
                    matched = true;
                    break;
                  }
                }
                if (!matched) field.value = value;
                field.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
              }
              return false;
            } else {
              field.value = value;
              field.dispatchEvent(new Event('input', { bubbles: true }));
              field.dispatchEvent(new Event('change', { bubbles: true }));
              return true;
            }
          };

          const okCountry = setField(countryField, draft['foreigner_country']);
          const okState = setField(stateField, draft['foreigner_state']);
          const okCity = setField(cityFieldF, draft['foreigner_city']);
          if (okCountry && okState && okCity) { clearInterval(setForeign); }
          if (tries >= max) { clearInterval(setForeign); }
        }, 120);
      }
    } catch (e) {}
  });
})();
</script>
<?php

try {
    // Check profile submission status with row lock
    $column = "{$respondent}_profile_submitted";
    $checkStmt = $conn->prepare("
        SELECT $column 
        FROM couple_access 
        WHERE access_id = ?
        FOR UPDATE
    ");

    if (!$checkStmt) {
        throw new Exception("Database prepare failed");
    }

    $checkStmt->bind_param("i", $access_id);

    if (!$checkStmt->execute()) {
        throw new Exception("Query execution failed");
    }

    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("No access record found for ID: $access_id");
    }

    $submitted = (bool)$result->fetch_assoc()[$column];

    if ($submitted) {
        header("Location: ../questionnaire/questionnaire.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Profile Check Error [ID: $access_id]: " . $e->getMessage());
    $_SESSION['system_error'] = "Temporary system issue. Please try again later.";
    header("Location: ../index.php");
    exit();
}

// Set sex based on respondent type and lock if partner submitted
$sex = ucfirst($respondent);
$sexDisabled = $partnerSubmitted ? 'disabled' : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $formTitle ?> | Couple Profile Form</title>
</head>

<body class="bg-light">
    <div class="wrapper">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-9">
                    <div class="card shadow-lg <?= $formColorClass ?>">
                        <div class="card-header p-3 text-center <?= $respondent === 'male' ? 'bg-primary' : 'bg-pink' ?>">
                            <h3 class="m-0 text-white"><i class="fas fa-user-circle mr-2"></i><?= $formTitle ?></h3>
                            <p class="text-white-50 mb-0">Please complete all required fields (*)</p>
                        </div>

                        <div class="card-body p-4">
                            <!-- Form Progress Indicator -->
                            <div class="form-progress mb-4">
                                <div class="progress-steps">
                                    <div class="step active" data-step="1">
                                        <div class="step-circle">1</div>
                                        <div class="step-label">Personal Info</div>
                                    </div>
                                    <div class="step" data-step="2">
                                        <div class="step-circle">2</div>
                                        <div class="step-label">Background</div>
                                    </div>
                                    <div class="step" data-step="3">
                                        <div class="step-circle">3</div>
                                        <div class="step-label">Family Planning</div>
                                    </div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated 
                                        <?= $respondent === 'male' ? 'male-progress' : 'female-progress' ?>"
                                        role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                            </div>

                            <!-- Auto-save UI removed -->

                            <form id="coupleProfileForm" method="post" action="couple_profile.php" class="needs-validation" novalidate>
                                <?php 
                                require_once __DIR__ . '/../includes/csrf_helper.php';
                                if (isset($_SESSION['access_id'])) {
                                    echo '<input type="hidden" name="csrf_token" value="' . getCsrfToken() . '">';
                                }
                                ?>
                                <input type="hidden" name="couple_access" value="<?= htmlspecialchars($access_code) ?>">

                                <div class="tab-content" id="wizardTabContent">
                                    <!-- Step 1 - Personal Information -->
                                    <div class="tab-pane fade show active" id="step1" role="tabpanel" aria-labelledby="step1-tab">
                                        <div class="form-section animate__animated animate__fadeIn">
                                            <h5 class="section-title text-center mb-4"><i class="fas fa-user-tie mr-2"></i>Personal Information</h5>

                                            <!-- Full Name Row -->
                                            <!-- Name Container -->
                                            <div class="form-row mb-3">
                                                <div class="col-12">
                                                    <div class="form-group border rounded p-3 bg-light">
                                                        <div class="form-row">
                                                            <!-- First Name -->
                                                            <div class="col-md-4 mb-3">
                                                                <label class="form-label required-field">First Name</label>
                                                                <div class="input-group has-validation">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                                    </div>
                                                                    <input type="text" name="first_name" class="form-control capitalize" placeholder="Enter first name" autocomplete="off" required>
                                                                    <div class="invalid-feedback">Please provide your first name</div>
                                                                </div>
                                                            </div>

                                                            <!-- Middle Name -->
                                                            <div class="col-md-3 mb-3">
                                                                <label class="form-label">Middle Name</label>
                                                                <div class="input-group">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                                    </div>
                                                                    <input type="text" name="middle_name" class="form-control capitalize" placeholder="Enter middle name" autocomplete="off">
                                                                </div>
                                                            </div>

                                                            <!-- Last Name -->
                                                            <div class="col-md-3 mb-3">
                                                                <label class="form-label required-field">Last Name</label>
                                                                <div class="input-group has-validation">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                                    </div>
                                                                    <input type="text" name="last_name" class="form-control capitalize" placeholder="Enter last name" autocomplete="off" required>
                                                                    <div class="invalid-feedback">Please provide your last name</div>
                                                                </div>
                                                            </div>

                                                            <!-- Suffix -->
                                                            <div class="col-md-2 mb-3">
                                                                <label class="form-label">Suffix</label>
                                                                <div class="input-group">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                                                    </div>
                                                                    <select class="form-control" name="suffix">
                                                                        <option value="">None</option>
                                                                        <option value="Jr">Jr</option>
                                                                        <option value="Sr">Sr</option>
                                                                        <option value="II">II</option>
                                                                        <option value="III">III</option>
                                                                        <option value="IV">IV</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Birthdate Row -->
                                            <div class="form-row mb-3">
                                                <div class="col-12">
                                                    <div class="form-group border rounded p-3 bg-light">
                                                        <div class="form-row">
                                                            <!-- Month -->
                                                            <div class="col-md-3 mb-3">
                                                                <label class="form-label required-field">Month</label>
                                                                <div class="input-group has-validation">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                                                    </div>
                                                                    <select class="form-control" name="birth_month" id="birthMonth" required>
                                                                        <option value="">Select Month</option>
                                                                        <option value="01">January</option>
                                                                        <option value="02">February</option>
                                                                        <option value="03">March</option>
                                                                        <option value="04">April</option>
                                                                        <option value="05">May</option>
                                                                        <option value="06">June</option>
                                                                        <option value="07">July</option>
                                                                        <option value="08">August</option>
                                                                        <option value="09">September</option>
                                                                        <option value="10">October</option>
                                                                        <option value="11">November</option>
                                                                        <option value="12">December</option>
                                                                    </select>
                                                                    <div class="invalid-feedback">Please select birth month</div>
                                                                </div>
                                                            </div>

                                                            <!-- Day -->
                                                            <div class="col-md-3 mb-3">
                                                                <label class="form-label required-field">Day</label>
                                                                <div class="input-group has-validation">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
                                                                    </div>
                                                                    <input type="number" name="birth_day" class="form-control" placeholder="Day" min="1" max="31" required>
                                                                    <div class="invalid-feedback">Please enter valid day (1-31)</div>
                                                                </div>
                                                            </div>

                                                            <!-- Year -->
                                                            <div class="col-md-3 mb-3">
                                                                <label class="form-label required-field">Year</label>
                                                                <div class="input-group has-validation">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                                                    </div>
                                                                    <input type="number" name="birth_year" class="form-control" placeholder="Year" min="1900" max="<?= date('Y') ?>" required>
                                                                    <div class="invalid-feedback">Please enter valid year (1900-<?= date('Y') ?>)</div>
                                                                </div>
                                                            </div>

                                                            <!-- Age -->
                                                            <div class="col-md-3 mb-3">
                                                                <label class="form-label required-field">Age</label>
                                                                <div class="input-group has-validation">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><i class="fas fa-birthday-cake"></i></span>
                                                                    </div>
                                                                    <input type="number" name="age" class="form-control" id="age" placeholder="Auto-calculated" readonly required>
                                                                    <div class="invalid-feedback">Age must be 18 or older to register</div>
                                                                </div>
                                                            </div>
                                                            <input type="hidden" name="date_of_birth" id="date_of_birth">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-row">
                                                <!-- Sex -->
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label required-field">Sex</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                                        </div>
                                                        <input class="form-control" name="sex" value="<?= $sex ?>" readonly required>
                                                    </div>
                                                </div>

                                                <!-- Email address -->
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Email Address</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                                        </div>
                                                        <input type="email" name="email_address" class="form-control" placeholder="example@email.com" pattern="[^@\s]+@[^@\s]+\.[^@\s]+" autocomplete="off">
                                                        <div class="invalid-feedback">Please provide a valid email</div>
                                                    </div>
                                                </div>

                                                <!-- Contact -->
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label required-field">Contact Number</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                        </div>
                                                        <input type="tel" name="contact_number" class="form-control" placeholder="09XXXXXXXXX" autocomplete="off" required>
                                                        <div class="invalid-feedback">Please provide 11-digit number starting with 09</div>
                                                    </div>
                                                </div>

                                                <!-- Residency Status -->
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label required-field">Residency Status</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                                        </div>
                                                        <select class="form-control" name="residency_type" id="residencyType" required>
                                                            <option value="">Select Status</option>
                                                            <option value="bago">Bago</option>
                                                            <option value="non-bago">Non-Bago</option>
                                                            <option value="foreigner">Foreigner</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select your residency status</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Address Section - Hidden by default -->
                                            <div id="addressSection" style="display:none;">
                                                <!-- Bago City Address Fields -->
                                                <div id="bagoAddressFields" style="display:none;">
                                                    <div class="form-row">
                                                        <!-- City (fixed as Bago City) -->
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label required-field">City</label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-city"></i></span>
                                                                </div>
                                                                <input type="text" name="city" class="form-control" value="Bago" readonly>
                                                            </div>
                                                        </div>

                                                        <!-- Barangay (loaded from JSON) -->
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label required-field">Barangay</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                                                </div>
                                                                <select class="form-control" name="barangay" id="barangay" required>
                                                                    <option value="">Select Barangay</option>
                                                                </select>
                                                                <div class="invalid-feedback">Please select your barangay</div>
                                                            </div>
                                                        </div>

                                                        <!-- Purok -->
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label required-field">Purok</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-home"></i></span>
                                                                </div>
                                                                <input type="text" name="purok" class="form-control" placeholder="Enter purok" autocomplete="off" required>
                                                                <div class="invalid-feedback">Please provide your purok</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Non-Bago Address Fields -->
                                                <div id="nonBagoAddressFields" style="display:none;">
                                                    <div class="form-row">
                                                        <!-- City (manual input) -->
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label required-field">City</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-city"></i></span>
                                                                </div>
                                                                <input type="text" name="non_bago_city" class="form-control" placeholder="Enter your city" autocomplete="off" required>
                                                                <div class="invalid-feedback">Please provide your city</div>
                                                            </div>
                                                        </div>

                                                        <!-- Barangay (manual input) -->
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label required-field">Barangay</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                                                </div>
                                                                <input type="text" name="non_bago_barangay" class="form-control" placeholder="Enter your barangay" autocomplete="off" required>
                                                                <div class="invalid-feedback">Please provide your barangay</div>
                                                            </div>
                                                        </div>

                                                        <!-- Purok for Non-Bago -->
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label required-field">Purok</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-home"></i></span>
                                                                </div>
                                                                <input type="text" name="non_bago_purok" class="form-control" placeholder="Enter your purok" autocomplete="off" required>
                                                                <div class="invalid-feedback">Please provide your purok</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Foreigner Address Fields -->
                                                <div id="foreignerAddressFields" style="display:none;">
                                                    <div class="form-row">
                                                        <!-- Country -->
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label required-field">Country</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                                                </div>
                                                                <input type="text" name="foreigner_country" class="form-control" placeholder="Enter your country" autocomplete="off" required>
                                                                <div class="invalid-feedback">Please provide your country</div>
                                                            </div>
                                                        </div>

                                                        <!-- State/Province -->
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label required-field">State/Province</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-map"></i></span>
                                                                </div>
                                                                <input type="text" name="foreigner_state" class="form-control" placeholder="Enter your state/province" autocomplete="off" required>
                                                                <div class="invalid-feedback">Please provide your state/province</div>
                                                            </div>
                                                        </div>

                                                        <!-- City -->
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label required-field">City</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-city"></i></span>
                                                                </div>
                                                                <input type="text" name="foreigner_city" class="form-control" placeholder="Enter your city" autocomplete="off" required>
                                                                <div class="invalid-feedback">Please provide your city</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 2 - Background Information -->
                                    <div class="tab-pane fade" id="step2" role="tabpanel" aria-labelledby="step2-tab">
                                        <div class="form-section animate__animated animate__fadeIn">
                                            <h5 class="section-title text-center mb-4"><i class="fas fa-info-circle mr-2"></i>Background Information</h5>

                                            <!-- Civil Status and Education Row -->
                                            <div class="form-row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field">Civil Status</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-heart"></i></span>
                                                        </div>
                                                        <select class="form-control" name="civil_status" id="civilStatus" required>
                                                            <option value="">Select Civil Status</option>
                                                            <option value="Single">Single</option>
                                                            <option value="Living In">Living In</option>
                                                            <option value="Widowed">Widowed</option>
                                                            <option value="Separated">Separated</option>
                                                            <option value="Divorced">Divorced</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select your civil status</div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field">Highest Education Attainment</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                                                        </div>
                                                        <select class="form-control" name="education" required>
                                                            <option value="">Select Education</option>
                                                            <option value="No Education">No Education</option>
                                                            <option value="Pre School">Pre School</option>
                                                            <option value="Elementary Level">Elementary Level</option>
                                                            <option value="Elementary Graduate">Elementary Graduate</option>
                                                            <option value="High School Level">High School Level</option>
                                                            <option value="High School Graduate">High School Graduate</option>
                                                            <option value="Junior HS Level">Junior HS Level</option>
                                                            <option value="Junior HS Graduate">Junior HS Graduate</option>
                                                            <option value="Senior HS Level">Senior HS Level</option>
                                                            <option value="Senior HS Graduate">Senior HS Graduate</option>
                                                            <option value="College Level">College Level </option>
                                                            <option value="College Graduate">College Graduate</option>
                                                            <option value="Vocational/Technical">Vocational/Technical</option>
                                                            <option value="ALS">Alternative Learning System (ALS)</option>
                                                            <option value="Post Graduate">Post Graduate</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select your education level</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Living Together Fields (conditionally shown) -->
                                            <div id="livingInFields" style="display:none;" class="animate__animated animate__fadeIn">
                                                <div class="form-row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label required-field">Years Living Together</label>
                                                        <div class="input-group has-validation">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                                            </div>
                                                            <input type="number" name="years_living_together" class="form-control" min="0" placeholder="Enter number of years">
                                                            <div class="invalid-feedback">Please provide years living together</div>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label required-field">Reason for Living Together</label>
                                                        <div class="input-group has-validation">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fas fa-comment"></i></span>
                                                            </div>
                                                            <textarea name="living_in_reason" class="form-control" placeholder="Enter reason" rows="2"></textarea>
                                                            <div class="invalid-feedback">Please provide reason for living together</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Religion and Nationality Row -->
                                            <div class="form-row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field">Religion</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-pray"></i></span>
                                                        </div>
                                                        <select class="form-control" name="religion" id="religion" required>
                                                            <option value="">Select Religion</option>
                                                            <option value="Aglipay">Aglipay</option>
                                                            <option value="Bible Baptist Church">Bible Baptist Church</option>
                                                            <option value="Church of Christ">Church of Christ</option>
                                                            <option value="Jehovas Witness">Jehova's Witness</option>
                                                            <option value="Iglesia ni Cristo">Iglesia ni Cristo</option>
                                                            <option value="Islam">Islam</option>
                                                            <option value="Roman Catholic">Roman Catholic</option>
                                                            <option value="Seventh Day Adventist">Seventh Day Adventist</option>
                                                            <option value="Iglesia Filipina Independente">Iglesia Filipina Independente</option>
                                                            <option value="United Church of Christ in the PH">United Church of Christ in the PH</option>
                                                            <option value="None">None</option>
                                                            <option value="Other">Other</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select your religion</div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field">Nationality</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                                        </div>
                                                        <input type="text" name="nationality" class="form-control" value="Filipino" autocomplete="off" required>
                                                        <div class="invalid-feedback">Please provide your nationality</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Other Religion Field (conditionally shown) -->
                                            <div id="otherReligionField" style="display:none;" class="animate__animated animate__fadeIn">
                                                <div class="form-row">
                                                    <div class="col-12 mb-3">
                                                        <label class="form-label required-field">Specify Religion</label>
                                                        <div class="input-group has-validation">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fas fa-praying-hands"></i></span>
                                                            </div>
                                                            <input type="text" name="other_religion" class="form-control" placeholder="Enter religion" autocomplete="off">
                                                            <div class="invalid-feedback">Please specify your religion</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Wedding Type and Employment Status Row -->
                                            <div class="form-row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field">Wedding Type</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-ring"></i></span>
                                                        </div>
                                                        <select class="form-control" name="wedding_type" required>
                                                            <option value="">Select Wedding Type</option>
                                                            <option value="Civil">Civil</option>
                                                            <option value="Church">Church</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select wedding type</div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field">Employment Status</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                                        </div>
                                                        <select class="form-control" name="employment_status" id="employmentStatus" required>
                                                            <option value="">Select Employment Status</option>
                                                            <option value="Employed">Employed</option>
                                                            <option value="Self-employed">Self-employed</option>
                                                            <option value="Unemployed">Unemployed</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select your employment status</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Occupation and Monthly Income Row -->
                                            <div class="form-row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field">Occupation</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                                        </div>
                                                        <input type="text" name="occupation" class="form-control" id="occupation" placeholder="Enter occupation" autocomplete="off" required>
                                                        <div class="invalid-feedback">Please provide your occupation</div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label required-field">Monthly Income</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-money-bill-wave"></i></span>
                                                        </div>
                                                        <select class="form-control" name="monthly_income" required>
                                                            <option value="">Select Income Range</option>
                                                            <option value="5000 below">5,000 below</option>
                                                            <option value="5999-9999">5,999 - 9,999</option>
                                                            <option value="10000-14999">10,000 - 14,999</option>
                                                            <option value="15000-19999">15,000 - 19,999</option>
                                                            <option value="20000-24999">20,000 - 24,999</option>
                                                            <option value="25000 above">25,000 above</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select your income range</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 3 - Family Planning -->
                                    <div class="tab-pane fade" id="step3" role="tabpanel" aria-labelledby="step3-tab">
                                        <div class="form-section animate__animated animate__fadeIn">
                                            <h5 class="section-title text-center mb-4"><i class="fas fa-heartbeat mr-2"></i>Family Planning Information</h5>

                                            <!-- Heard about FP section -->
                                            <div class="form-row">
                                                <div class="col-12 mb-3">
                                                    <label class="form-label required-field">Have you heard about Family Planning?</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                                        </div>
                                                        <select class="form-control" name="heard_fp" id="heardFamilyPlanning" required>
                                                            <option value="">Select Option</option>
                                                            <option value="Yes">Yes</option>
                                                            <option value="No">No</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select an option</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="heardFPYes" style="display:none;" class="animate__animated animate__fadeIn">
                                                <div class="form-row">
                                                    <div class="col-12 col-md-6 mb-3" id="facilityContainer">
                                                        <label class="form-label required-field">Where did you hear about it? (Facility)</label>
                                                        <div class="input-group has-validation">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fas fa-clinic-medical"></i></span>
                                                            </div>
                                                            <select class="form-control" name="fp_facility" id="facilityType" required>
                                                                <option value="">Select Facility</option>
                                                                <option value="Government Hospital">Government Hospital</option>
                                                                <option value="Pharmacy">Pharmacy</option>
                                                                <option value="RHU">RHU</option>
                                                                <option value="BHS">BHS</option>
                                                                <option value="Private Hospital/Clinic">Private Hospital/Clinic</option>
                                                                <option value="Other">Other</option>
                                                            </select>
                                                            <div class="invalid-feedback">Please select facility type</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-6 mb-3" id="channelContainer">
                                                        <label class="form-label required-field">How did you hear about it? (Channel)</label>
                                                        <div class="input-group has-validation">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fas fa-bullhorn"></i></span>
                                                            </div>
                                                            <select class="form-control" name="fp_channel" id="channelType" required>
                                                                <option value="">Select Channel</option>
                                                                <option value="Health Worker">Health Worker</option>
                                                                <option value="Spouse/Partner">Spouse/Partner</option>
                                                                <option value="Internet/Social Media">Internet/Social Media</option>
                                                                <option value="Friends/Family">Friends/Family</option>
                                                                <option value="Radio/TV">Radio/TV</option>
                                                                <option value="Health Education">Health Education</option>
                                                                <option value="School">School</option>
                                                                <option value="Others">Others</option>
                                                            </select>
                                                            <div class="invalid-feedback">Please select channel type</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Move "Other" fields outside the form-row to maintain column alignment -->
                                                <div class="form-row" id="otherFieldsRow" style="display:none;">
                                                    <div class="col-12 col-md-6 mb-3" id="otherFacilityContainer">
                                                        <div class="mt-2" id="otherFacilityField" style="display:none;">
                                                            <label class="form-label required-field">Specify Facility</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-hospital"></i></span>
                                                                </div>
                                                                <input type="text" name="other_facility" class="form-control" placeholder="Enter facility name" autocomplete="off" required>
                                                                <div class="invalid-feedback">Please specify facility</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-6 mb-3" id="otherChannelContainer">
                                                        <div class="mt-2" id="otherChannelField" style="display:none;">
                                                            <label class="form-label required-field">Specify Channel</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-comments"></i></span>
                                                                </div>
                                                                <input type="text" name="other_channel" class="form-control" placeholder="Enter channel name" autocomplete="off" required>
                                                                <div class="invalid-feedback">Please specify channel</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="heardFPNo" style="display:none;" class="animate__animated animate__fadeIn">
                                                <div class="form-row">
                                                    <div class="col-12 mb-3">
                                                        <label class="form-label required-field">Why haven't you heard about Family Planning?</label>
                                                        <div class="input-group has-validation">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fas fa-question"></i></span>
                                                            </div>
                                                            <textarea name="not_heard_reason" class="form-control" placeholder="Enter reason" required></textarea>
                                                            <div class="invalid-feedback">Please provide reason</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Intend to use FP section -->
                                            <div class="form-row">
                                                <div class="col-12 mb-3">
                                                    <label class="form-label required-field">Do you intend to use Family Planning methods?</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-question-circle"></i></span>
                                                        </div>
                                                        <select class="form-control" name="intend_fp" id="intendFP" required>
                                                            <option value="">Select Option</option>
                                                            <option value="Yes">Yes</option>
                                                            <option value="No">No</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select an option</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="fpMethods" style="display:none;" class="animate__animated animate__fadeIn">
                                                <div class="form-row">
                                                    <div class="col-12 mb-3">
                                                        <label class="form-label required-field">Preferred Method</label>
                                                        <div id="femaleMethods" style="display:none;">
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-female"></i></span>
                                                                </div>
                                                                <select class="form-control" name="fp_female_method" id="femaleMethodType" required>
                                                                    <option value="">Select Method</option>
                                                                    <option value="IUD">IUD</option>
                                                                    <option value="Implant">Implant</option>
                                                                    <option value="Pills">Pills</option>
                                                                    <option value="DMPA/Injectables">Depot medroxyprogesterone acetate (DMPA)/Injectables</option>
                                                                    <option value="BTL">Bilateral Tubal Ligation(BTL)</option>
                                                                    <option value="Natural">Natural FP Methods</option>
                                                                    <option value="Other">Other Methods</option>
                                                                </select>
                                                                <div class="invalid-feedback">Please select preferred method</div>
                                                            </div>
                                                        </div>

                                                        <div id="maleMethods" style="display:none;">
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-male"></i></span>
                                                                </div>
                                                                <select class="form-control" name="fp_male_method" id="maleMethodType" required>
                                                                    <option value="">Select Method</option>
                                                                    <option value="Condom">Condom</option>
                                                                    <option value="Vasectomy">Vasectomy</option>
                                                                    <option value="Natural">Natural FP Methods</option>
                                                                    <option value="Other">Other Methods</option>
                                                                </select>
                                                                <div class="invalid-feedback">Please select preferred method</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Female Method Specification Fields -->
                                                <div id="naturalMethodField" style="display:none;" class="animate__animated animate__fadeIn">
                                                    <div class="form-row">
                                                        <div class="col-12 mb-3">
                                                            <label class="form-label required-field">Specify Natural FP Method</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-leaf"></i></span>
                                                                </div>
                                                                <input type="text" name="female_natural_method" class="form-control" placeholder="Enter method" autocomplete="off">
                                                                <div class="invalid-feedback">Please specify method</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div id="otherMethodField" style="display:none;" class="animate__animated animate__fadeIn">
                                                    <div class="form-row">
                                                        <div class="col-12 mb-3">
                                                            <label class="form-label required-field">Specify Other Method</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-plus-circle"></i></span>
                                                                </div>
                                                                <input type="text" name="female_other_method" class="form-control" placeholder="Enter method" autocomplete="off">
                                                                <div class="invalid-feedback">Please specify method</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Male Method Specification Fields -->
                                                <div id="maleNaturalMethodField" style="display:none;" class="animate__animated animate__fadeIn">
                                                    <div class="form-row">
                                                        <div class="col-12 mb-3">
                                                            <label class="form-label required-field">Specify Natural Method</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-leaf"></i></span>
                                                                </div>
                                                                <input type="text" name="male_natural_method" class="form-control" placeholder="Enter method" autocomplete="off">
                                                                <div class="invalid-feedback">Please specify method</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div id="maleOtherMethodField" style="display:none;" class="animate__animated animate__fadeIn">
                                                    <div class="form-row">
                                                        <div class="col-12 mb-3">
                                                            <label class="form-label required-field">Specify Other Method</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-plus-circle"></i></span>
                                                                </div>
                                                                <input type="text" name="male_other_method" class="form-control" placeholder="Enter method" autocomplete="off">
                                                                <div class="invalid-feedback">Please specify method</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="noIntendFP" style="display:none;" class="animate__animated animate__fadeIn">
                                                <div class="form-row">
                                                    <div class="col-12 mb-3">
                                                        <label class="form-label required-field">Why don't you intend to use Family Planning?</label>
                                                        <div class="input-group has-validation">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fas fa-comment-dots"></i></span>
                                                            </div>
                                                            <textarea name="not_intend_reason" class="form-control" placeholder="Enter reason"></textarea>
                                                            <div class="invalid-feedback">Please provide reason</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Female-specific questions -->
                                            <div id="femaleQuestions" style="display:none;">
                                                <div class="form-row">
                                                    <div class="col-12 mb-3">
                                                        <label class="form-label required-field">Are you currently pregnant?</label>
                                                        <div class="input-group has-validation">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fas fa-baby"></i></span>
                                                            </div>
                                                            <select class="form-control" name="currently_pregnant" id="currentlyPregnant">
                                                                <option value="">Select Option</option>
                                                                <option value="Yes">Yes</option>
                                                                <option value="No">No</option>
                                                            </select>
                                                            <div class="invalid-feedback">Please select an option</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div id="pregnantYes" style="display:none;" class="animate__animated animate__fadeIn">
                                                    <div class="form-row">
                                                        <div class="col-12 mb-3">
                                                            <label class="form-label required-field">Age of gestation (months)</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
                                                                </div>
                                                                <input type="number" name="gestation_age" class="form-control" min="1" max="9" placeholder="Enter months">
                                                                <div class="invalid-feedback">Please provide gestation age</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div id="pregnantNo" style="display:none;" class="animate__animated animate__fadeIn">
                                                    <div class="form-row">
                                                        <div class="col-12 mb-3">
                                                            <label class="form-label required-field">When do you want to have your pregnancy?</label>
                                                            <div class="input-group has-validation">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-calendar-plus"></i></span>
                                                                </div>
                                                                <textarea name="pregnancy_plan" class="form-control" placeholder="Enter plan"></textarea>
                                                                <div class="invalid-feedback">Please provide information</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Additional questions -->
                                            <div class="form-row">
                                                <div class="col-12 mb-3">
                                                    <label class="form-label required-field">Desired number of children</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-child"></i></span>
                                                        </div>
                                                        <select class="form-control" name="desired_children" id="desiredChildren" required>
                                                            <option value="">Select Option</option>
                                                            <option value="0">None</option>
                                                            <option value="1">1</option>
                                                            <option value="2">2</option>
                                                            <option value="3">3</option>
                                                            <option value="4">4</option>
                                                            <option value="5+">5 or more</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select desired number</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="desiredChildrenReason" style="display:none;" class="animate__animated animate__fadeIn">
                                                <div class="form-row">
                                                    <div class="col-12 mb-3">
                                                        <label class="form-label required-field">Why this number of children?</label>
                                                        <div class="input-group has-validation">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fas fa-question"></i></span>
                                                            </div>
                                                            <textarea name="children_reason" class="form-control" placeholder="Enter reason"></textarea>
                                                            <div class="invalid-feedback">Please provide reason</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="noChildrenReason" style="display:none;" class="animate__animated animate__fadeIn">
                                                <div class="form-row">
                                                    <div class="col-12 mb-3">
                                                        <label class="form-label required-field">Why don't you want children?</label>
                                                        <div class="input-group has-validation">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fas fa-question"></i></span>
                                                            </div>
                                                            <textarea name="no_children_reason" class="form-control" placeholder="Enter reason"></textarea>
                                                            <div class="invalid-feedback">Please provide reason</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-row">
                                                <div class="col-12 mb-3">
                                                    <label class="form-label required-field">Do you have children from past union/marriages?</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-users"></i></span>
                                                        </div>
                                                        <select class="form-control" name="past_children" id="pastChildren" required>
                                                            <option value="">Select Option</option>
                                                            <option value="Yes">Yes</option>
                                                            <option value="No">No</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select an option</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div id="pastChildrenYes" style="display:none;" class="animate__animated animate__fadeIn">
                                                <div class="form-row">
                                                    <div class="col-12 mb-3">
                                                        <label class="form-label required-field">Number of children</label>
                                                        <div class="input-group has-validation">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fas fa-sort-numeric-up"></i></span>
                                                            </div>
                                                            <input type="number" name="past_children_count" class="form-control" min="0" placeholder="Enter number">
                                                            <div class="invalid-feedback">Please provide number of children</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-row">
                                                <div class="col-12 mb-3">
                                                    <label class="form-label required-field">Are you a PhilHealth member?</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-heartbeat"></i></span>
                                                        </div>
                                                        <select class="form-control" name="philhealth_member" required>
                                                            <option value="">Select Option</option>
                                                            <option value="Yes">Yes</option>
                                                            <option value="No">No</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select an option</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-row">
                                                <div class="col-12 mb-3">
                                                    <label class="form-label required-field">What are your reasons for getting married?</label>
                                                    <div class="input-group has-validation">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-heart"></i></span>
                                                        </div>
                                                        <textarea name="marriage_reasons" class="form-control" placeholder="Enter reasons" required></textarea>
                                                        <div class="invalid-feedback">Please provide reasons</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        </div>

                        <!-- Navigation buttons -->
                        <div class="card-footer bg-white border-top p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <button type="button" class="btn btn-secondary" id="prevBtn">
                                        <i class="fas fa-arrow-left mr-2"></i>Previous
                                    </button>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-primary" id="nextBtn">
                                        Next<i class="fas fa-arrow-right ml-2"></i>
                                    </button>
                                    <button type="button" class="btn btn-success" id="submitBtn" style="display:none;">
                                        <i class="fas fa-check-circle mr-2"></i>Submit Form
                                    </button>
                                </div>
                            </div>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // ===== INITIALIZATION =====
            const progressBar = $('.progress-bar');
            const totalSteps = 3;
            let currentStep = 1;
            // Auto-save functionality removed

            // Pre-populate form if resuming
            <?php if ($isResuming && $savedProgress): ?>
            function populateFormWithSavedData() {
                <?php foreach ($savedProgress as $field => $value): ?>
                if ($('input[name="<?= $field ?>"]').length) {
                    $('input[name="<?= $field ?>"]').val('<?= addslashes($value) ?>');
                } else if ($('select[name="<?= $field ?>"]').length) {
                    $('select[name="<?= $field ?>"]').val('<?= addslashes($value) ?>');
                } else if ($('textarea[name="<?= $field ?>"]').length) {
                    $('textarea[name="<?= $field ?>"]').val('<?= addslashes($value) ?>');
                }
                <?php endforeach; ?>
                
                // Trigger change events to show conditional fields
                $('select[name="civil_status"], select[name="religion"], select[name="employment_status"], select[name="heard_fp"], select[name="intend_fp"], select[name="currently_pregnant"], select[name="desired_children"], select[name="past_children"]').trigger('change');
            }
            
            // Populate form after page loads
            populateFormWithSavedData();
            <?php endif; ?>

            const emailInput = $('input[name="email_address"]');
            const respondentType = '<?= $respondent ?>'; // 'male' or 'female'

            // Initialize step indicators
            updateStepIndicators();
            
            // Check age validation on page load
            validateAgeOnLoad();

            // ===== CORE FUNCTIONS =====
            
            function validateAgeOnLoad() {
                const ageField = $('#age');
                const ageValue = parseInt(ageField.val());
                
                if (!isNaN(ageValue) && ageValue < 18) {
                    showFieldError('#age', 'Age must be 18 or older to register');
                    // Disable the Next button if age is invalid
                    $('#nextBtn').prop('disabled', true).addClass('btn-danger').removeClass('btn-primary')
                        .html('<i class="fas fa-exclamation-triangle mr-2"></i>Age Restriction');
                }
            }
            
            function updateProgressBar() {
                const progressPercentage = (currentStep / totalSteps) * 100;
                progressBar.css('width', progressPercentage + '%').attr('aria-valuenow', progressPercentage);
            }

            function updateStepIndicators() {
                $('.step').removeClass('active completed');

                // Mark previous steps as completed
                for (let i = 1; i < currentStep; i++) {
                    $(`.step[data-step="${i}"]`).addClass('completed');
                }

                // Mark current step as active
                $(`.step[data-step="${currentStep}"]`).addClass('active');
            }

            function showFieldError(field, message) {
                const $field = $(field);
                $field.addClass('is-invalid');

                // Add shake animation to highlight error
                $field.addClass('animate__animated animate__shakeX');
                setTimeout(() => {
                    $field.removeClass('animate__animated animate__shakeX');
                }, 1000);

                let $feedback = $field.next('.invalid-feedback');
                if (!$feedback.length) {
                    $field.after(`<div class="invalid-feedback">${message}</div>`);
                } else {
                    $feedback.text(message).show();
                }
                
                // Set auto-clear timeout (5 seconds)
                const timeout = setTimeout(() => {
                    clearFieldError(field);
                }, 5000);
                $field.data('errorTimeout', timeout);
            }

            function clearFieldError(field) {
                const $field = $(field);
                $field.removeClass('is-invalid');
                $field.next('.invalid-feedback').hide();
                
                // Clear any timeout for this field
                if ($field.data('errorTimeout')) {
                    clearTimeout($field.data('errorTimeout'));
                    $field.removeData('errorTimeout');
                }
            }

            function debounce(func, timeout = 500) {
                let timer;
                return (...args) => {
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        func.apply(this, args);
                    }, timeout);
                };
            }

            // ===== TEMPORARY ERROR DISPLAY =====
            function showTemporaryError(field, message, duration) {
                const $field = $(field);
                const $feedback = $field.next('.invalid-feedback').length ?
                    $field.next('.invalid-feedback') :
                    $(`<div class="invalid-feedback"></div>`).insertAfter($field);

                $field.addClass('is-invalid animate__animated animate__shakeX');
                $feedback.text(message).show();

                // Set timeout to clear error
                const errorTimer = setTimeout(() => {
                    $field.removeClass('is-invalid animate__animated animate__shakeX');
                    $feedback.hide();
                }, duration);

                // Clear immediately on valid input
                $field.off('input.tempError').on('input.tempError', function() {
                    clearTimeout(errorTimer);
                    $field.removeClass('is-invalid animate__animated animate__shakeX');
                    $feedback.hide();
                    $field.off('input.tempError');
                });
            }

            // ===== VALIDATION FUNCTIONS =====

            // 1. NAME VALIDATION (NO SPECIAL CHARACTERS, AUTO-CAPITALIZE)
            $('input[name="first_name"], input[name="middle_name"], input[name="last_name"]').on('input', function() {
                const $field = $(this);
                let value = $field.val();
                let originalValue = value; // Store original for comparison

                // Remove special characters except spaces, hyphens, and apostrophes
                const cleanedValue = value.replace(/[^a-zA-Z\s\-']/g, '');
                if (cleanedValue !== value) {
                    $field.val(cleanedValue);
                    showTemporaryError(this, 'Names can only contain letters, spaces, hyphens, and apostrophes', 2000);
                }

                // Auto-capitalize first letter
                if (cleanedValue.length > 0) {
                    $field.val(cleanedValue.charAt(0).toUpperCase() + cleanedValue.slice(1).toLowerCase());
                }
            });

            // 2. BIRTHDATE VALIDATION    
            let dayTimeout, monthTimeout, yearTimeout;

            // Day validation
            $('input[name="birth_day"]').on('input change', function() {
                clearTimeout(dayTimeout);
                clearFieldError(this);

                dayTimeout = setTimeout(() => {
                    const day = parseInt($(this).val());
                    const month = $('#birthMonth').val();
                    const year = parseInt($('input[name="birth_year"]').val());

                    if (!isValidYear(year)) return; // Don't proceed if year is invalid

                    if (day && month && year) {
                        const daysInMonth = new Date(year, month, 0).getDate();
                        if (day < 1 || day > daysInMonth) {
                            showFieldError(this, `Day must be between 1 and ${daysInMonth}`);
                        } else {
                            clearFieldError(this);
                            calculateAge();
                            if ($('input[name="first_name"]').val() && $('input[name="last_name"]').val()) {
                                checkFullNameWithBirthdate();
                            }
                        }
                    }
                }, 500);
            });

            // Month validation
            $('#birthMonth').on('change', function() {
                clearTimeout(monthTimeout);
                clearFieldError(this);

                monthTimeout = setTimeout(() => {
                    const month = $(this).val();
                    const day = parseInt($('input[name="birth_day"]').val());
                    const year = parseInt($('input[name="birth_year"]').val());

                    if (!isValidYear(year)) return; // Don't proceed if year is invalid

                    if (month && day && year) {
                        const daysInMonth = new Date(year, month, 0).getDate();
                        if (day < 1 || day > daysInMonth) {
                            showFieldError('input[name="birth_day"]', `Day must be between 1 and ${daysInMonth}`);
                        } else {
                            clearFieldError('input[name="birth_day"]');
                            calculateAge();
                            if ($('input[name="first_name"]').val() && $('input[name="last_name"]').val()) {
                                checkFullNameWithBirthdate();
                            }
                        }
                    }
                }, 500);
            });

            // Year validation
            $('input[name="birth_year"]').on('input change', function() {
                clearTimeout(yearTimeout);
                clearFieldError(this);

                yearTimeout = setTimeout(() => {
                    const year = parseInt($(this).val());
                    const currentYear = new Date().getFullYear();

                    if (year && (year < 1900 || year > currentYear)) {
                        showFieldError(this, `Year must be between 1900 and ${currentYear}`);
                        // Clear age if year is invalid
                        $('#age').val('');
                        $('#date_of_birth').val('');
                    } else {
                        clearFieldError(this);
                        const month = $('#birthMonth').val();
                        const day = parseInt($('input[name="birth_day"]').val());

                        if (month && day) {
                            const daysInMonth = new Date(year, month, 0).getDate();
                            if (day < 1 || day > daysInMonth) {
                                showFieldError('input[name="birth_day"]', `Day must be between 1 and ${daysInMonth}`);
                            } else {
                                clearFieldError('input[name="birth_day"]');
                                calculateAge();
                                if ($('input[name="first_name"]').val() && $('input[name="last_name"]').val()) {
                                    checkFullNameWithBirthdate();
                                }
                            }
                        }
                    }
                }, 500);
            });

            // Helper function to validate year
            function isValidYear(year) {
                const currentYear = new Date().getFullYear();
                return year && year >= 1900 && year <= currentYear;
            }

            // 3. AGE CALCULATION
            function calculateAge() {
                const month = $('#birthMonth').val();
                const day = $('input[name="birth_day"]').val();
                const year = $('input[name="birth_year"]').val();

                // Don't calculate if year is invalid
                if (!isValidYear(parseInt(year))) {
                    $('#age').val('');
                    $('#date_of_birth').val('');
                    return;
                }

                if (month && day && year) {
                    const paddedDay = day.padStart(2, '0');
                    const birthDate = new Date(`${year}-${month}-${paddedDay}`);
                    const today = new Date();

                    let age = today.getFullYear() - birthDate.getFullYear();
                    const monthDiff = today.getMonth() - birthDate.getMonth();

                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                        age--;
                    }

                    $('#age').val(age >= 0 ? age : '');
                    $('#date_of_birth').val(`${year}-${month}-${paddedDay}`);
                    
                    // Validate age immediately after calculation
                    if (age >= 0) {
                        if (age < 18) {
                            showFieldError('#age', 'Age must be 18 or older to register');
                            // Disable the Next button if age is invalid
                            $('#nextBtn').prop('disabled', true).addClass('btn-danger').removeClass('btn-primary')
                                .html('<i class="fas fa-exclamation-triangle mr-2"></i>Age Restriction');
                        } else {
                            clearFieldError('#age');
                            // Re-enable the Next button if age is valid
                            $('#nextBtn').prop('disabled', false).removeClass('btn-danger').addClass('btn-primary')
                                .html('Next<i class="fas fa-arrow-right ml-2"></i>');
                        }
                    } else {
                        // Clear age validation if no valid age
                        clearFieldError('#age');
                        // Re-enable the Next button if no age
                        $('#nextBtn').prop('disabled', false).removeClass('btn-danger').addClass('btn-primary')
                            .html('Next<i class="fas fa-arrow-right ml-2"></i>');
                    }
                } else {
                    // Clear age and validation if birthdate is incomplete
                    $('#age').val('');
                    $('#date_of_birth').val('');
                    clearFieldError('#age');
                    // Re-enable the Next button when birthdate is incomplete
                    $('#nextBtn').prop('disabled', false).removeClass('btn-danger').addClass('btn-primary')
                        .html('Next<i class="fas fa-arrow-right ml-2"></i>');
                }
            }

            //  NAME + BIRTHDATE DUPLICATE CHECK
            const checkFullNameWithBirthdate = debounce(function() {
                const firstName = $('input[name="first_name"]').val().trim();
                const middleName = $('input[name="middle_name"]').val().trim();
                const lastName = $('input[name="last_name"]').val().trim();
                const suffix = $('select[name="suffix"]').val();
                const birthDate = $('#date_of_birth').val();

                if (!firstName || !lastName || !birthDate) return;

                $.ajax({
                    url: 'couple_check_fullname.php',
                    type: 'POST',
                    data: {
                        first_name: firstName,
                        middle_name: middleName || '',
                        last_name: lastName,
                        suffix: suffix || '',
                        date_of_birth: birthDate
                    },
                    success: function(response) {
                        if (response.exists) {
                            // Disable Next button
                            $('#nextBtn').prop('disabled', true).addClass('btn-danger').removeClass('btn-primary')
                                .html('<i class="fas fa-exclamation-triangle mr-2"></i>Duplicate Found');

                            // Clear problematic fields with animation
                            $('input[name="first_name"], input[name="middle_name"], input[name="last_name"]')
                                .addClass('animate__animated animate__shakeX')
                                .val('')
                                .one('animationend', function() {
                                    $(this).removeClass('animate__animated animate__shakeX');
                                });

                            // Clear birthdate fields
                            $('#birthMonth, input[name="birth_day"], input[name="birth_year"]').val('');
                            $('#date_of_birth').val('');
                            $('#age').val('');
                            clearFieldError('#age');
                            // Re-enable the Next button when birthdate is cleared
                            $('#nextBtn').prop('disabled', false).removeClass('btn-danger').addClass('btn-primary')
                                .html('Next<i class="fas fa-arrow-right ml-2"></i>');

                            // Show alert
                            Swal.fire({
                                icon: 'error',
                                title: 'Duplicate Profile',
                                html: `<div class="text-left">
                            <p>This combination already exists in our records:</p>
                            <ul class="mb-3">
                                <li><strong>Name:</strong> ${firstName} ${middleName || ''} ${lastName} ${suffix || ''}</li>
                                <li><strong>Birthdate:</strong> ${birthDate}</li>
                            </ul>
                            <small class="text-muted">Please correct the information to continue</small>
                        </div>`,
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#dc3545'
                            }).then(() => {
                                $('input[name="first_name"]').focus();
                            });
                        } else {
                            // Re-enable Next button if no duplicate
                            $('#nextBtn').prop('disabled', false).removeClass('btn-danger').addClass('btn-primary')
                                .html('Next<i class="fas fa-arrow-right ml-2"></i>');
                        }
                    },
                    error: function(xhr) {
                        console.error("Check failed:", xhr.responseText);
                    }
                });
            });

            // Trigger name+birthdate check
            $('input[name="first_name"], input[name="middle_name"], input[name="last_name"], select[name="suffix"]')
                .on('input change', function() {
                    if ($('#date_of_birth').val()) {
                        checkFullNameWithBirthdate();
                    }
                });

            // 4. EMAIL VALIDATION (FORMAT + AVAILABILITY) - OPTIONAL FIELD
            let emailTimeout;
            emailInput.on('input', function() {
                clearTimeout(emailTimeout);
                const email = $(this).val().trim();
                clearFieldError(this);
                // Don't validate empty field - email is now optional
                if (email === '') {
                    clearFieldError(this);
                    return;
                }

                // Only validate after user stops typing for 1 second
                emailTimeout = setTimeout(() => {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        showFieldError(this, 'Please enter a valid email (e.g., user@example.com)');
                    } else {
                        clearFieldError(this);
                        checkEmailAvailability(email);
                    }
                }, 1000);
            });

            function checkEmailAvailability(email) {
                $.ajax({
                    url: 'couple_check_email.php',
                    type: 'POST',
                    data: {
                        email: email
                    },
                    success: function(response) {
                        if (response.exists) {
                            showFieldError(emailInput[0], 'This email is already registered');
                        } else {
                            clearFieldError(emailInput[0]);
                        }
                    },
                    error: function() {
                        showFieldError(emailInput[0], 'Error checking email availability');
                    }
                });
            }

            // 5.  CONTACT NUMBER (EXACTLY 11 DIGITS, STARTS WITH 09)
            let contactTimeout;

            $('input[name="contact_number"]').on('input', function() {
                const $field = $(this);
                let value = $field.val().replace(/\D/g, ''); // Remove non-digit characters
                // Clear any existing errors during typing
                clearFieldError(this);
                // Clear previous timeout if it exists
                clearTimeout(contactTimeout);

                // Limit to 11 digits
                if (value.length > 11) {
                    value = value.substring(0, 11);
                }
                $field.val(value);

                // Only validate if there's some input
                if (value.length > 0) {
                    // Set timeout to validate after user stops typing (1 second delay)
                    contactTimeout = setTimeout(() => {
                        if (!value.startsWith('09')) {
                            showFieldError(this, 'Contact number must start with 09');
                        } else if (value.length < 11) {
                            showFieldError(this, 'Contact number must be exactly 11 digits');
                        } else {
                            clearFieldError(this);
                        }
                    }, 1000); // 1 second delay
                }
            }).on('blur', function() {
                // Immediate validation when field loses focus
                const value = $(this).val();
                if (value.length > 0) {
                    if (!value.startsWith('09')) {
                        showFieldError(this, 'Contact number must start with 09');
                    } else if (value.length < 11) {
                        showFieldError(this, 'Contact number must be exactly 11 digits');
                    }
                }
            });

            // 6. RESIDENCY TYPE VALIDATION AND ADDRESS FIELD TOGGLE
            $('#residencyType').change(function() {
                clearFieldError(this);
                const residencyType = $(this).val();
                
                // Hide all address sections first
                $('#addressSection').hide();
                $('#bagoAddressFields, #nonBagoAddressFields, #foreignerAddressFields').hide();
                
                // Clear all address fields
                $('input[name="city"], input[name="barangay"], input[name="purok"], input[name="non_bago_city"], input[name="non_bago_barangay"], input[name="non_bago_purok"], input[name="foreigner_country"], input[name="foreigner_state"], input[name="foreigner_city"]').val('');
                $('#barangay').empty().append('<option value="">Select Barangay</option>');
                
                // Remove required attributes from all address fields
                $('input[name="city"], select[name="barangay"], input[name="purok"], input[name="non_bago_city"], input[name="non_bago_barangay"], input[name="non_bago_purok"], input[name="foreigner_country"], input[name="foreigner_state"], input[name="foreigner_city"]').prop('required', false);
                
                if (residencyType === 'bago') {
                    // Show Bago City address fields
                    $('#addressSection').show();
                    $('#bagoAddressFields').show().addClass('animate__animated animate__fadeIn');
                    
                    // Make Bago fields required
                    $('input[name="city"], select[name="barangay"], input[name="purok"]').prop('required', true);
                    
                    // Set city to Bago City and make it readonly
                    $('input[name="city"]').val('Bago').prop('readonly', true);
                    
                    // Load barangays for Bago City
                    loadBarangays();
                    
                } else if (residencyType === 'non-bago') {
                    // Show Non-Bago address fields
                    $('#addressSection').show();
                    $('#nonBagoAddressFields').show().addClass('animate__animated animate__fadeIn');
                    
                    // Make Non-Bago fields required
                    $('input[name="non_bago_city"], input[name="non_bago_barangay"], input[name="non_bago_purok"]').prop('required', true);
                    
                } else if (residencyType === 'foreigner') {
                    // Show Foreigner address fields
                    $('#addressSection').show();
                    $('#foreignerAddressFields').show().addClass('animate__animated animate__fadeIn');
                    
                    // Make Foreigner fields required
                    $('input[name="foreigner_country"], input[name="foreigner_state"], input[name="foreigner_city"]').prop('required', true);
                }
            });

            // 7. Barangay VALIDATION AND LOADING
            let barangayValidationTimeout;

            // Main function to load barangays
            function loadBarangays() {
                const barangaySelect = $('#barangay');

                // Enhanced loading state with spinner
                barangaySelect.prop('disabled', true)
                    .empty()
                    .append('<option value=""><span class="spinner-border spinner-border-sm" role="status"></span> Loading barangays...</option>');

                // Fallback data
                const fallbackBarangays = [
                    "Abuanan", "Alianza", "Atipuluan", "Bacong-Montilla", "Bagroy",
                    "Balingasag", "Binubuhan", "Busay", "Calumangan", "Caridad",
                    "Don Jorge L. Araneta", "Dulao", "Ilijan", "Lag-Asan", "Ma-ao",
                    "Mailum", "Malingin", "Napoles", "Pacol", "Poblacion",
                    "Sagasa", "Sampinit", "Tabunan", "Taloc"
                ];

                // AJAX request with enhanced error handling
                $.ajax({
                        url: '../ph_address/ph-json/barangay.json',
                        dataType: 'json',
                        timeout: 5000,
                        beforeSend: function() {
                            // Optional: Add any pre-request logic
                        }
                    })
                    .done(function(data) {
                        try {
                            validateAndPopulateBarangays(data, barangaySelect);
                        } catch (e) {
                            console.error("Data processing error:", e);
                            useFallbackData(barangaySelect, fallbackBarangays);
                        }
                    })
                    .fail(function(jqXHR, textStatus, errorThrown) {
                        console.error("AJAX error:", textStatus, errorThrown);
                        useFallbackData(barangaySelect, fallbackBarangays);
                    })
                    .always(function() {
                        barangaySelect.prop('disabled', false);
                        initializeBarangayValidation();
                    });
            }

            // Helper function to validate and populate barangays
            function validateAndPopulateBarangays(data, selectElement) {
                if (!Array.isArray(data)) {
                    throw new Error('Invalid data format - expected array');
                }

                const cityCode = '064502'; // Bago City code
                const bagoBarangays = data
                    .filter(brgy => brgy.city_code === cityCode)
                    .sort((a, b) => a.brgy_name.localeCompare(b.brgy_name));

                if (bagoBarangays.length === 0) {
                    throw new Error('No barangays found for Bago City');
                }

                selectElement.empty().append('<option value="">Select Barangay</option>');

                bagoBarangays.forEach(brgy => {
                    selectElement.append(
                        `<option value="${brgy.brgy_name}" 
              data-brgy-code="${brgy.brgy_code}"
              title="${brgy.brgy_name}">
              ${brgy.brgy_name}
            </option>`
                    );
                });
            }

            // Fallback data handler
            function useFallbackData(selectElement, barangays) {
                selectElement.empty().append('<option value="">Select Barangay</option>');
                barangays.forEach(brgy => {
                    selectElement.append(`<option value="${brgy}">${brgy}</option>`);
                });
                console.warn('Using fallback barangay data');
            }

            // Initialize validation for barangay select
            function initializeBarangayValidation() {
                const barangaySelect = $('#barangay');

                barangaySelect.off('change input').on('change input', function() {
                    clearTimeout(barangayValidationTimeout);
                    clearFieldError(this);

                    barangayValidationTimeout = setTimeout(() => {}, 300);
                });

                // Add blur validation if field is required
                if (barangaySelect.prop('required')) {
                    barangaySelect.off('blur').on('blur', function() {});
                }
            }

            // 7. PUROK CAPITALIZATION (FIRST LETTER)
            let purokTimeout
            $('input[name="purok"]').on('input', function() {
                const $field = $(this);
                let value = $field.val();

                // Capitalize first letter of each word
                if (value.length > 0) {
                    $field.val(value.replace(/\w\S*/g, function(txt) {
                        return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
                    }));
                }

                // Clear any existing errors during typing
                clearFieldError(this);

                // Clear previous timeout if it exists
                clearTimeout(purokTimeout);
            });

            // 8. CIVIL STATUS VALIDATION AND CONDITIONAL FIELDS
            $('#civilStatus').change(function() {
                clearFieldError(this);
                const isLivingIn = $(this).val() === 'Living In';
                $('#livingInFields').toggle(isLivingIn);

                if (isLivingIn) {
                    $('#livingInFields').addClass('animate__animated animate__fadeIn');
                    $('input[name="years_living_together"], textarea[name="living_in_reason"]').prop('required', true);
                } else {
                    $('input[name="years_living_together"], textarea[name="living_in_reason"]').prop('required', false).val('');
                }
            });

            // 9.HIGHEST EDUCATIONAL ATTAINMENT VALIDATION
            let educationTimeout;

            // Education field validation
            $('select[name="education"]').on('change', function() {
                clearTimeout(educationTimeout);
                clearFieldError(this);
            });



            // 10. RELIGION VALIDATION AND CONDITIONAL FIELDS
            // Show/hide other religion field based on selection
            $('#religion').change(function() {
                clearFieldError(this); // Add this line
                if ($(this).val() === 'Other') {
                    $('#otherReligionField').show().addClass('animate__animated animate__fadeIn');
                    $('input[name="other_religion"]').prop('required', true);
                } else {
                    $('#otherReligionField').hide();
                    $('input[name="other_religion"]').prop('required', false);
                    $('input[name="other_religion"]').val(''); // Clear the field when hiding
                }
            });

            // Trigger change event on page load in case 'Other' is already selected
            $('#religion').trigger('change');

            // 11. Nationality Field (even with default 'Filipino' value)
            $('input[name="nationality"]').on('input', function() {
                clearFieldError(this);
            });

            // 12. Wedding Type Field
            $('select[name="wedding_type"]').on('change', function() {
                clearFieldError(this);
            });

            // 13. Employment status field
            $('#employmentStatus').change(function() {
                clearFieldError(this); // Add this line
            });

            // 14. OCCUPATION AUTOCOMPLETE - wait until jQuery UI is ready to avoid "$().autocomplete is not a function"
            function initOccupationAutocomplete(){
                if (!(window.jQuery && $.ui && $.ui.autocomplete)) { return false; }
                $('#occupation').autocomplete({
                source: function(request, response) {
                    // Sample occupations - replace with your actual data source
                    const occupations = [
                        "Accountant", "Architect", "Artist", "Baker", "Banker",
                        "Barber", "Bartender", "Business Owner", "Carpenter", "Cashier",
                        "Chef", "Cleaner", "Clerk", "Construction Worker", "Cook",
                        "Dentist", "Designer", "Doctor", "Driver", "Electrician",
                        "Engineer", "Farmer", "Fisherman", "Graphic Designer", "Hairdresser",
                        "Housekeeper", "IT Professional", "Journalist", "Lawyer", "Mechanic",
                        "Nurse", "Pharmacist", "Photographer", "Plumber", "Police Officer",
                        "Receptionist", "Salesperson", "Secretary", "Security Guard", "Teacher",
                        "Technician", "Waiter", "Welder", "Writer", "Other"
                    ];

                    // Filter the occupations based on user input
                    const results = $.ui.autocomplete.filter(
                        occupations,
                        request.term
                    );

                    response(results);
                },
                minLength: 2,
                select: function(event, ui) {
                    $(this).val(ui.item.value);
                    clearFieldError(this); // Clear error when item is selected
                    return false;
                },
                focus: function(event, ui) {
                    $(this).val(ui.item.value);
                    return false;
                },
                change: function(event, ui) {
                    clearFieldError(this); // Clear error when field changes
                }
                }).autocomplete("instance")._renderItem = function(ul, item) {
                    return $("<li>")
                        .append(`<div>${item.label}</div>`)
                        .appendTo(ul);
                };
                return true;
            }
            (function waitForJqui(){
                if (!initOccupationAutocomplete()) {
                    setTimeout(waitForJqui, 150);
                }
            })();

            // Add input event handler to clear errors while typing
            $('#occupation').on('input', function() {
                clearFieldError(this);
            });

            // 15. Monthly Income Field
            $('select[name="monthly_income"]').on('change', function() {
                clearFieldError(this);
            });



            // 8. FAMILY PLANNING SECTION
            $('#heardFamilyPlanning').change(function() {
                clearFieldError(this);
                const heardFP = $(this).val();

                $('#heardFPYes').toggle(heardFP === 'Yes').addClass('animate__animated animate__fadeIn');
                $('#heardFPNo').toggle(heardFP === 'No').addClass('animate__animated animate__fadeIn');

                // Make fields required when shown
                if (heardFP === 'Yes') {
                    $('#heardFPYes select[name="fp_facility"], #heardFPYes select[name="fp_channel"]').prop('required', true);
                    $('#heardFPNo textarea[name="not_heard_reason"]').prop('required', false);
                } else if (heardFP === 'No') {
                    $('#heardFPNo textarea[name="not_heard_reason"]').prop('required', true);
                    $('#heardFPYes select[name="fp_facility"], #heardFPYes select[name="fp_channel"]').prop('required', false);
                } else {
                    $('#heardFPYes select[name="fp_facility"], #heardFPYes select[name="fp_channel"], #heardFPNo textarea[name="not_heard_reason"]').prop('required', false);
                }

                if (heardFP !== 'Yes') {
                    $('#heardFPYes input, #heardFPYes select').val('');
                    $('#otherFacilityField, #otherChannelField, #otherFieldsRow').hide();
                    $('#otherFacilityField input[name="other_facility"], #otherChannelField input[name="other_channel"]').prop('required', false);
                }
                if (heardFP !== 'No') {
                    $('#heardFPNo input').val('');
                }
            });

            $('#facilityType').change(function() {
                clearFieldError(this);
                const facilityType = $(this).val();
                const showOther = facilityType === 'Other';
                $('#otherFacilityField').toggle(showOther).addClass('animate__animated animate__fadeIn');
                // Show/hide the entire other fields row
                $('#otherFieldsRow').toggle(showOther || $('#channelType').val() === 'Others');
                
                if (facilityType === 'Other') {
                    $('input[name="other_facility"]').prop('required', true);
                } else {
                    $('input[name="other_facility"]').prop('required', false).val('');
                }
            });

            $('#channelType').change(function() {
                clearFieldError(this);
                const channelType = $(this).val();
                const showOtherCh = channelType === 'Others';
                $('#otherChannelField').toggle(showOtherCh).addClass('animate__animated animate__fadeIn');
                // Show/hide the entire other fields row
                $('#otherFieldsRow').toggle(showOtherCh || $('#facilityType').val() === 'Other');
                
                if (channelType === 'Others') {
                    $('input[name="other_channel"]').prop('required', true);
                } else {
                    $('input[name="other_channel"]').prop('required', false).val('');
                }
            });

            // 9. INTEND TO USE FP
            $('#intendFP').change(function() {
                clearFieldError(this);
                const intendFP = $(this).val();
                $('#fpMethods').toggle(intendFP === 'Yes').addClass('animate__animated animate__fadeIn');
                $('#noIntendFP').toggle(intendFP === 'No').addClass('animate__animated animate__fadeIn');

                // Make fields required when shown
                if (intendFP === 'Yes') {
                    $('#fpMethods select[name="fp_female_method"], #fpMethods select[name="fp_male_method"]').prop('required', true);
                    $('#noIntendFP textarea[name="not_intend_reason"]').prop('required', false);
                    $('#noIntendFP input').val('');
                    // Show gender-specific methods
                    $('#femaleMethods').toggle(respondentType === 'female');
                    $('#maleMethods').toggle(respondentType === 'male');
                } else if (intendFP === 'No') {
                    $('#noIntendFP textarea[name="not_intend_reason"]').prop('required', true);
                    $('#fpMethods select[name="fp_female_method"], #fpMethods select[name="fp_male_method"]').prop('required', false);
                    $('#fpMethods input, #fpMethods select').val('');
                } else {
                    $('#fpMethods, #noIntendFP').hide();
                    $('#fpMethods select[name="fp_female_method"], #fpMethods select[name="fp_male_method"], #noIntendFP textarea[name="not_intend_reason"]').prop('required', false);
                }
            });

            // 10. FP METHOD SPECIFIC FIELDS
            $('#femaleMethodType').change(function() {
                clearFieldError(this);
                const method = $(this).val();
                $('#naturalMethodField').toggle(method === 'Natural').addClass('animate__animated animate__fadeIn');
                $('#otherMethodField').toggle(method === 'Other').addClass('animate__animated animate__fadeIn');

                // Make fields required when shown
                if (method === 'Natural') {
                    $('input[name="female_natural_method"]').prop('required', true);
                    $('input[name="female_other_method"]').prop('required', false).val('');
                } else if (method === 'Other') {
                    $('input[name="female_other_method"]').prop('required', true);
                    $('input[name="female_natural_method"]').prop('required', false).val('');
                } else {
                    $('input[name="female_natural_method"], input[name="female_other_method"]').prop('required', false).val('');
                }
            });

            $('#maleMethodType').change(function() {
                clearFieldError(this);
                const method = $(this).val();
                $('#maleNaturalMethodField').toggle(method === 'Natural').addClass('animate__animated animate__fadeIn');
                $('#maleOtherMethodField').toggle(method === 'Other').addClass('animate__animated animate__fadeIn');

                // Make fields required when shown
                if (method === 'Natural') {
                    $('input[name="male_natural_method"]').prop('required', true);
                    $('input[name="male_other_method"]').prop('required', false).val('');
                } else if (method === 'Other') {
                    $('input[name="male_other_method"]').prop('required', true);
                    $('input[name="male_natural_method"]').prop('required', false).val('');
                } else {
                    $('input[name="male_natural_method"], input[name="male_other_method"]').prop('required', false).val('');
                }
            });

            // 11. FEMALE-SPECIFIC QUESTIONS
            $('#currentlyPregnant').change(function() {
                clearFieldError(this);
                const isPregnant = $(this).val();
                $('#pregnantYes').toggle(isPregnant === 'Yes').addClass('animate__animated animate__fadeIn');
                $('#pregnantNo').toggle(isPregnant === 'No').addClass('animate__animated animate__fadeIn');

                // Make fields required when shown
                if (isPregnant === 'Yes') {
                    $('input[name="gestation_age"]').prop('required', true);
                    $('input[name="pregnancy_plan"]').prop('required', false).val('');
                } else if (isPregnant === 'No') {
                    $('input[name="pregnancy_plan"]').prop('required', true);
                    $('input[name="gestation_age"]').prop('required', false).val('');
                } else {
                    $('input[name="gestation_age"], input[name="pregnancy_plan"]').prop('required', false).val('');
                }
            });

            // 12. DESIRED CHILDREN
            $('#desiredChildren').change(function() {
                clearFieldError(this);
                const desired = $(this).val();
                $('#desiredChildrenReason').toggle(desired !== '' && desired !== '0').addClass('animate__animated animate__fadeIn');
                $('#noChildrenReason').toggle(desired === '0').addClass('animate__animated animate__fadeIn');

                // Make fields required when shown
                if (desired !== '' && desired !== '0') {
                    $('input[name="children_reason"]').prop('required', true);
                    $('input[name="no_children_reason"]').prop('required', false).val('');
                } else if (desired === '0') {
                    $('input[name="no_children_reason"]').prop('required', true);
                    $('input[name="children_reason"]').prop('required', false).val('');
                } else {
                    $('input[name="children_reason"], input[name="no_children_reason"]').prop('required', false).val('');
                }
            });

            // 13. PAST CHILDREN
            $('#pastChildren').change(function() {
                clearFieldError(this);
                const hasPastChildren = $(this).val();
                $('#pastChildrenYes').toggle(hasPastChildren === 'Yes').addClass('animate__animated animate__fadeIn');

                // Make fields required when shown
                if (hasPastChildren === 'Yes') {
                    $('input[name="past_children_count"]').prop('required', true);
                } else {
                    $('input[name="past_children_count"]').prop('required', false).val('');
                }
            });

            // 14. MARRIAGE REASONS
            $('textarea[name="marriage_reasons"]').on('input', function() {
                clearFieldError(this);
            });

            // 15. YEARS LIVING TOGETHER
            $('input[name="years_living_together"]').on('input', function() {
                clearFieldError(this);
            });

            // 16. OTHER RELIGION
            $('input[name="other_religion"]').on('input', function() {
                clearFieldError(this);
            });

            // 17. OTHER FACILITY
            $('input[name="other_facility"]').on('input', function() {
                clearFieldError(this);
            });

            // 18. OTHER CHANNEL
            $('input[name="other_channel"]').on('input', function() {
                clearFieldError(this);
            });

            // 19. FEMALE NATURAL METHOD
            $('input[name="female_natural_method"]').on('input', function() {
                clearFieldError(this);
            });

            // 20. FEMALE OTHER METHOD
            $('input[name="female_other_method"]').on('input', function() {
                clearFieldError(this);
            });

            // 21. MALE NATURAL METHOD
            $('input[name="male_natural_method"]').on('input', function() {
                clearFieldError(this);
            });

            // 22. MALE OTHER METHOD
            $('input[name="male_other_method"]').on('input', function() {
                clearFieldError(this);
            });

            // 23. GESTATION AGE
            $('input[name="gestation_age"]').on('input', function() {
                clearFieldError(this);
            });

            // 24. PREGNANCY PLAN
            $('input[name="pregnancy_plan"]').on('input', function() {
                clearFieldError(this);
            });

            // 25. CHILDREN REASON
            $('input[name="children_reason"]').on('input', function() {
                clearFieldError(this);
            });

            // 26. NO CHILDREN REASON
            $('input[name="no_children_reason"]').on('input', function() {
                clearFieldError(this);
            });

            // 27. PAST CHILDREN COUNT
            $('input[name="past_children_count"]').on('input', function() {
                clearFieldError(this);
            });

            // 28. LIVING IN REASON
            $('textarea[name="living_in_reason"]').on('input', function() {
                clearFieldError(this);
            });

            // 29. NOT HEARD REASON
            $('textarea[name="not_heard_reason"]').on('input', function() {
                clearFieldError(this);
            });

            // 30. NOT INTEND REASON
            $('textarea[name="not_intend_reason"]').on('input', function() {
                clearFieldError(this);
            });

            // 31. PREGNANCY PLAN
            $('textarea[name="pregnancy_plan"]').on('input', function() {
                clearFieldError(this);
            });

            // 32. CHILDREN REASON
            $('textarea[name="children_reason"]').on('input', function() {
                clearFieldError(this);
            });

            // 33. NO CHILDREN REASON
            $('textarea[name="no_children_reason"]').on('input', function() {
                clearFieldError(this);
            });

            // 34. RESIDENCY TYPE FIELDS
            $('input[name="non_bago_city"], input[name="non_bago_barangay"], input[name="non_bago_purok"]').on('input', function() {
                clearFieldError(this);
            });

            $('input[name="foreigner_country"], input[name="foreigner_state"], input[name="foreigner_city"]').on('input', function() {
                clearFieldError(this);
            });



            // ===== FORM NAVIGATION =====
            function validateCurrentStep() {
                const currentTab = $('.tab-pane.active');
                let isValid = true;
                let firstInvalidField = null;

                // First clear all errors in current step
                currentTab.find('.is-invalid').removeClass('is-invalid');
                currentTab.find('.invalid-feedback').hide();

                // Then validate each field
                currentTab.find('[required]').each(function() {
                    const $field = $(this);

                    // Skip validation for hidden fields
                    if ($field.is(':hidden')) return true;

                    // Field-specific validations
                    if ($field.attr('name') === 'contact_number' && $field.val().length !== 11) {
                        showFieldError(this, 'Contact number must be exactly 11 digits');
                        isValid = false;
                        if (!firstInvalidField) firstInvalidField = this;
                    } else if ($field.attr('name') === 'email_address' && $field.val().trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($field.val())) {
                        showFieldError(this, 'Please enter a valid email address');
                        isValid = false;
                        if (!firstInvalidField) firstInvalidField = this;
                    } else if ($field.attr('name') === 'age') {
                        const ageValue = parseInt($field.val());
                        if (isNaN(ageValue) || ageValue < 18) {
                            // When age is invalid/missing, validate DOB components and highlight the exact culprit
                            const currentYear = new Date().getFullYear();
                            const month = $('#birthMonth').val();
                            const dayStr = $('input[name="birth_day"]').val();
                            const yearStr = $('input[name="birth_year"]').val();

                            // Clear any previous errors on DOB fields before re-validating
                            clearFieldError('#birthMonth');
                            clearFieldError('input[name="birth_day"]');
                            clearFieldError('input[name="birth_year"]');

                            // Track first offending field
                            let dobFirstInvalid = null;

                            if (!month) {
                                showFieldError('#birthMonth', 'Please select birth month');
                                dobFirstInvalid = dobFirstInvalid || '#birthMonth';
                            }
                            if (!dayStr) {
                                showFieldError('input[name="birth_day"]', 'Please enter valid day (1-31)');
                                dobFirstInvalid = dobFirstInvalid || 'input[name="birth_day"]';
                            }
                            if (!yearStr) {
                                showFieldError('input[name="birth_year"]', `Year must be between 1900 and ${currentYear}`);
                                dobFirstInvalid = dobFirstInvalid || 'input[name="birth_year"]';
                            }

                            const day = parseInt(dayStr);
                            const year = parseInt(yearStr);
                            if (yearStr && (isNaN(year) || year < 1900 || year > currentYear)) {
                                showFieldError('input[name="birth_year"]', `Year must be between 1900 and ${currentYear}`);
                                dobFirstInvalid = dobFirstInvalid || 'input[name="birth_year"]';
                            } else if (month && dayStr && yearStr && !isNaN(day) && !isNaN(year)) {
                                const daysInMonth = new Date(year, month, 0).getDate();
                                if (day < 1 || day > daysInMonth) {
                                    showFieldError('input[name="birth_day"]', `Day must be between 1 and ${daysInMonth}`);
                                    dobFirstInvalid = dobFirstInvalid || 'input[name="birth_day"]';
                                }
                            }

                            // Keep age error for clarity
                            showFieldError(this, 'Age is required. Please correct your birthdate.');
                            isValid = false;
                            if (!firstInvalidField) firstInvalidField = dobFirstInvalid ? $(dobFirstInvalid)[0] : this;
                        }
                    } else if ($field.val() === '' || ($field.is('select') && $field.val() === '')) {
                        showFieldError(this, 'This field is required');
                        isValid = false;
                        if (!firstInvalidField) firstInvalidField = this;
                    } else {
                        clearFieldError(this);
                    }
                });

                if (!isValid && firstInvalidField) {
                    // Scroll to the first invalid field
                    $('html, body').animate({
                        scrollTop: $(firstInvalidField).offset().top - 100
                    }, 500);

                    // Add animation to highlight error
                    $(firstInvalidField).addClass('animate__animated animate__shakeX');
                    setTimeout(() => {
                        $(firstInvalidField).removeClass('animate__animated animate__shakeX');
                    }, 1000);
                }

                return isValid;
            }

            function navigateToNextStep() {
                if (!validateCurrentStep()) {
                    return false;
                }

                const currentTab = $('.tab-pane.active');
                const nextTab = currentTab.next('.tab-pane');

                if (nextTab.length) {
                    currentTab.removeClass('show active');
                    nextTab.addClass('show active animate__animated animate__fadeIn');
                    currentStep++;
                    updateProgressBar();
                    updateButtons();
                    updateStepIndicators();

                    $('html, body').animate({
                        scrollTop: $('.card-body').offset().top - 20
                    }, 500);
                }
            }

            function navigateToPreviousStep() {
                const currentTab = $('.tab-pane.active');
                const prevTab = currentTab.prev('.tab-pane');

                if (prevTab.length) {
                    currentTab.removeClass('show active');
                    prevTab.addClass('show active animate__animated animate__fadeIn');
                    currentStep--;
                    updateProgressBar();
                    updateButtons();
                    updateStepIndicators();

                    $('html, body').animate({
                        scrollTop: $('.card-body').offset().top - 20
                    }, 500);
                }
            }

            function updateButtons() {
                const currentTab = $('.tab-pane.active');
                $('#prevBtn').toggle(currentTab.attr('id') !== 'step1');

                if (currentTab.attr('id') === 'step3') {
                    $('#nextBtn').hide();
                    $('#submitBtn').show();
                } else {
                    $('#nextBtn').show();
                    $('#submitBtn').hide();
                }
            }

            $('#nextBtn').click(function() {
                navigateToNextStep();
            });

            $('#prevBtn').click(function() {
                navigateToPreviousStep();
            });

            // Handle submit button click
            $('#submitBtn').click(function(e) {
                e.preventDefault();
                
                // Validate all steps before submission
                if (validateAllSteps()) {
                $('#coupleProfileForm').submit();
                } else {
                    // Scroll to first invalid field
                    const $firstInvalid = $('.is-invalid').first();
                    if ($firstInvalid.length) {
                        $('html, body').animate({ scrollTop: $firstInvalid.offset().top - 120 }, 300);
                        setTimeout(() => { $firstInvalid.trigger('focus'); }, 320);
                    }
                }
            });

            // ===== VALIDATION FUNCTIONS =====
            function validateAllSteps() {
                let allValid = true;
                let firstInvalidField = null;

                // Hide all error messages first
                $('.is-invalid').removeClass('is-invalid');
                $('.invalid-feedback').hide();

                // Check all required fields in all steps
                $('[required]').each(function() {
                    const $field = $(this);

                    // Skip validation for hidden fields
                    if ($field.is(':hidden')) return true;

                    // Field-specific validations
                    if ($field.attr('name') === 'contact_number' && $field.val().length !== 11) {
                        showFieldError(this, 'Contact number must be exactly 11 digits');
                        allValid = false;
                        if (!firstInvalidField) firstInvalidField = this;
                    } else if ($field.attr('name') === 'email_address' && $field.val().trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($field.val())) {
                        showFieldError(this, 'Please enter a valid email address');
                        allValid = false;
                        if (!firstInvalidField) firstInvalidField = this;
                    } else if ($field.attr('name') === 'marriage_reasons' && $field.val().trim() === '') {
                        showFieldError(this, 'Please provide your reasons for getting married');
                        allValid = false;
                        if (!firstInvalidField) firstInvalidField = this;
                    } else if ($field.attr('name') === 'residency_type' && $field.val() === '') {
                        showFieldError(this, 'Please select your residency type');
                        allValid = false;
                        if (!firstInvalidField) firstInvalidField = this;
                    } else if ($field.attr('name') === 'age') {
                        const ageValue = parseInt($field.val());
                        if (isNaN(ageValue) || ageValue < 18) {
                            // When age is invalid/missing, validate DOB components and highlight the exact culprit
                            const currentYear = new Date().getFullYear();
                            const month = $('#birthMonth').val();
                            const dayStr = $('input[name="birth_day"]').val();
                            const yearStr = $('input[name="birth_year"]').val();

                            // Clear any previous errors on DOB fields before re-validating
                            clearFieldError('#birthMonth');
                            clearFieldError('input[name="birth_day"]');
                            clearFieldError('input[name="birth_year"]');

                            // Track first offending field
                            let dobFirstInvalid = null;

                            if (!month) {
                                showFieldError('#birthMonth', 'Please select birth month');
                                dobFirstInvalid = dobFirstInvalid || '#birthMonth';
                            }
                            if (!dayStr) {
                                showFieldError('input[name="birth_day"]', 'Please enter valid day (1-31)');
                                dobFirstInvalid = dobFirstInvalid || 'input[name="birth_day"]';
                            }
                            if (!yearStr) {
                                showFieldError('input[name="birth_year"]', `Year must be between 1900 and ${currentYear}`);
                                dobFirstInvalid = dobFirstInvalid || 'input[name="birth_year"]';
                            }

                            const day = parseInt(dayStr);
                            const year = parseInt(yearStr);
                            if (yearStr && (isNaN(year) || year < 1900 || year > currentYear)) {
                                showFieldError('input[name="birth_year"]', `Year must be between 1900 and ${currentYear}`);
                                dobFirstInvalid = dobFirstInvalid || 'input[name="birth_year"]';
                            } else if (month && dayStr && yearStr && !isNaN(day) && !isNaN(year)) {
                                const daysInMonth = new Date(year, month, 0).getDate();
                                if (day < 1 || day > daysInMonth) {
                                    showFieldError('input[name="birth_day"]', `Day must be between 1 and ${daysInMonth}`);
                                    dobFirstInvalid = dobFirstInvalid || 'input[name="birth_day"]';
                                }
                            }

                            // Keep age error for clarity
                            showFieldError(this, 'Age is required. Please correct your birthdate.');
                            allValid = false;
                            if (!firstInvalidField) firstInvalidField = dobFirstInvalid ? $(dobFirstInvalid)[0] : this;
                        }
                    } else if ($field.val().trim() === '' || ($field.is('select') && $field.val() === '')) {
                        showFieldError(this, 'This field is required');
                        allValid = false;
                        if (!firstInvalidField) firstInvalidField = this;
                    }
                });

                // Handle conditional required fields for step 3
                if (!validateConditionalFields()) {
                    allValid = false;
                }

                if (!allValid && firstInvalidField) {
                    // Activate the tab containing the invalid field
                    const tabId = $(firstInvalidField).closest('.tab-pane').attr('id');
                    if (tabId) {
                        $('.tab-pane').removeClass('show active');
                        $(`#${tabId}`).addClass('show active animate__animated animate__fadeIn');
                        currentStep = parseInt(tabId.replace('step', ''));
                        updateProgressBar();
                        updateButtons();
                        updateStepIndicators();
                    }

                    // Scroll to the first invalid field
                    $('html, body').animate({
                        scrollTop: $(firstInvalidField).offset().top - 100
                    }, 500);

                    // Add animation to highlight error
                    $(firstInvalidField).addClass('animate__animated animate__shakeX');
                    setTimeout(() => {
                        $(firstInvalidField).removeClass('animate__animated animate__shakeX');
                    }, 1000);
                }

                return allValid;
            }

            function validateConditionalFields() {
                let isValid = true;

                // Family Planning - Heard about FP
                const heardFP = $('#heardFamilyPlanning').val();
                if (heardFP === 'Yes') {
                    // Validate facility and channel fields
                    if (!$('select[name="fp_facility"]').val()) {
                        showFieldError('select[name="fp_facility"]', 'Please select facility type');
                        isValid = false;
                    }
                    if (!$('select[name="fp_channel"]').val()) {
                        showFieldError('select[name="fp_channel"]', 'Please select channel type');
                        isValid = false;
                    }
                    
                    // Check if "Other" is selected and validate
                    if ($('select[name="fp_facility"]').val() === 'Other' && !$('input[name="other_facility"]').val()) {
                        showFieldError('input[name="other_facility"]', 'Please specify facility');
                        isValid = false;
                    }
                    if ($('select[name="fp_channel"]').val() === 'Others' && !$('input[name="other_channel"]').val()) {
                        showFieldError('input[name="other_channel"]', 'Please specify channel');
                        isValid = false;
                    }
                } else if (heardFP === 'No') {
                    // Validate reason for not hearing about FP
                    if (!$('textarea[name="not_heard_reason"]').val()) {
                        showFieldError('textarea[name="not_heard_reason"]', 'Please provide reason');
                        isValid = false;
                    }
                }

                // Family Planning - Intend to use FP
                const intendFP = $('#intendFP').val();
                if (intendFP === 'Yes') {
                    // Validate method selection based on respondent type
                    if (respondentType === 'female') {
                        if (!$('select[name="fp_female_method"]').val()) {
                            showFieldError('select[name="fp_female_method"]', 'Please select preferred method');
                            isValid = false;
                        }
                        // Check if "Natural" or "Other" is selected and validate
                        const method = $('select[name="fp_female_method"]').val();
                        if (method === 'Natural' && !$('input[name="female_natural_method"]').val()) {
                            showFieldError('input[name="female_natural_method"]', 'Please specify method');
                            isValid = false;
                        }
                        if (method === 'Other' && !$('input[name="female_other_method"]').val()) {
                            showFieldError('input[name="female_other_method"]', 'Please specify method');
                            isValid = false;
                        }
                    } else {
                        if (!$('select[name="fp_male_method"]').val()) {
                            showFieldError('select[name="fp_male_method"]', 'Please select preferred method');
                            isValid = false;
                        }
                        // Check if "Natural" or "Other" is selected and validate
                        const method = $('select[name="fp_male_method"]').val();
                        if (method === 'Natural' && !$('input[name="male_natural_method"]').val()) {
                            showFieldError('input[name="male_natural_method"]', 'Please specify method');
                            isValid = false;
                        }
                        if (method === 'Other' && !$('input[name="male_other_method"]').val()) {
                            showFieldError('input[name="male_other_method"]', 'Please specify method');
                            isValid = false;
                        }
                    }
                } else if (intendFP === 'No') {
                    // Validate reason for not intending to use FP
                    if (!$('textarea[name="not_intend_reason"]').val()) {
                        showFieldError('textarea[name="not_intend_reason"]', 'Please provide reason');
                        isValid = false;
                    }
                }

                // Female-specific questions
                if (respondentType === 'female') {
                    const isPregnant = $('#currentlyPregnant').val();
                    if (isPregnant === 'Yes') {
                        if (!$('input[name="gestation_age"]').val()) {
                            showFieldError('input[name="gestation_age"]', 'Please provide gestation age');
                            isValid = false;
                        }
                    } else if (isPregnant === 'No') {
                        if (!$('textarea[name="pregnancy_plan"]').val()) {
                            showFieldError('textarea[name="pregnancy_plan"]', 'Please provide pregnancy plan');
                            isValid = false;
                        }
                    }
                }

                // Desired children
                const desiredChildren = $('#desiredChildren').val();
                if (desiredChildren && desiredChildren !== '0') {
                    if (!$('textarea[name="children_reason"]').val()) {
                        showFieldError('textarea[name="children_reason"]', 'Please provide reason');
                        isValid = false;
                    }
                } else if (desiredChildren === '0') {
                    if (!$('textarea[name="no_children_reason"]').val()) {
                        showFieldError('textarea[name="no_children_reason"]', 'Please provide reason');
                        isValid = false;
                    }
                }

                // Past children
                const pastChildren = $('#pastChildren').val();
                if (pastChildren === 'Yes') {
                    if (!$('input[name="past_children_count"]').val()) {
                        showFieldError('input[name="past_children_count"]', 'Please provide number of children');
                        isValid = false;
                    }
                }

                return isValid;
            }

            // ===== FORM SUBMISSION =====
            $('#coupleProfileForm').on('submit', function(e) {
                e.preventDefault();

                // Validate birthdate/age before proceeding
                if (!validateBirthdateBeforeSubmit()) {
                    return; // Halt submit until fields are corrected
                }

                // Check if both partners are non-bago or foreigner
                const currentResidencyType = $('#residencyType').val();
                
                // Get partner's residency type from session or form data
                // For now, we'll check if the current partner is non-bago or foreigner
                // and show a warning if they are
                if (currentResidencyType === 'non-bago' || currentResidencyType === 'foreigner') {
                    Swal.fire({
                        title: 'Residency Restriction',
                        html: `<div class="text-center">
                            <p><strong>Warning:</strong> The system does not allow both partners to be outside Bago City.</p>
                            <p>If your partner is also non-Bago or foreigner, the form cannot be submitted.</p>
                            <small class="text-muted">Please ensure at least one partner is from Bago City</small>
                        </div>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#ffc107',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Continue Anyway',
                        cancelButtonText: 'Go Back',
                        reverseButtons: true,
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Proceed with normal submission
                            showSubmitConfirmation();
                        }
                    });
                } else {
                    // Normal submission for Bago residents
                    showSubmitConfirmation();
                }
            });

            // Submit-time validation to ensure invalid/required fields are highlighted
            function validateBirthdateBeforeSubmit() {
                let isValid = true;
                const currentYear = new Date().getFullYear();
                const month = $('#birthMonth').val();
                const dayStr = $('input[name="birth_day"]').val();
                const yearStr = $('input[name="birth_year"]').val();

                // Clear previous errors for these fields
                clearFieldError('#birthMonth');
                clearFieldError('input[name="birth_day"]');
                clearFieldError('input[name="birth_year"]');
                clearFieldError('#age');

                // Basic required checks
                if (!month) {
                    showFieldError('#birthMonth', 'Please select birth month');
                    isValid = false;
                }
                if (!dayStr) {
                    showFieldError('input[name="birth_day"]', 'Please enter valid day (1-31)');
                    isValid = false;
                }
                if (!yearStr) {
                    showFieldError('input[name="birth_year"]', `Year must be between 1900 and ${currentYear}`);
                    isValid = false;
                }

                // Range checks if present
                const day = parseInt(dayStr);
                const year = parseInt(yearStr);
                if (yearStr && (isNaN(year) || year < 1900 || year > currentYear)) {
                    showFieldError('input[name="birth_year"]', `Year must be between 1900 and ${currentYear}`);
                    isValid = false;
                }
                if (month && dayStr && yearStr && !isNaN(day) && !isNaN(year)) {
                    const daysInMonth = new Date(year, month, 0).getDate();
                    if (day < 1 || day > daysInMonth) {
                        showFieldError('input[name="birth_day"]', `Day must be between 1 and ${daysInMonth}`);
                        isValid = false;
                    }
                }

                // Age must be present (auto-calculated). If missing, DOB is invalid/incomplete
                const ageVal = $('#age').val();
                if (!ageVal) {
                    showFieldError('#age', 'Age is required. Please correct your birthdate.');
                    isValid = false;
                }

                // Focus/scroll to the first invalid field
                if (!isValid) {
                    const $firstInvalid = $('.is-invalid').first();
                    if ($firstInvalid.length) {
                        $('html, body').animate({ scrollTop: $firstInvalid.offset().top - 120 }, 300);
                        setTimeout(() => { $firstInvalid.trigger('focus'); }, 320);
                    }
                }

                return isValid;
            }

            function showSubmitConfirmation() {
                // Helpers
                function textOf(name) {
                    const $el = $(`[name="${name}"]`);
                    if (!$el.length) {
                        console.log(`Field not found: ${name}`);
                        return '';
                    }
                    if ($el.is('select')) {
                        const val = $el.val();
                        const text = val ? $el.find('option:selected').text() : '';
                        console.log(`Select field ${name}: val="${val}", text="${text}"`);
                        return val; // Return the value, not the text
                    }
                    if ($el.attr('type') === 'radio') {
                        const $r = $(`input[name="${name}"]:checked`);
                        const val = $r.length ? $r.val() : '';
                        console.log(`Radio field ${name}: "${val}"`);
                        return val;
                    }
                    const val = ($el.val() || '').toString();
                    console.log(`Input field ${name}: "${val}"`);
                    return val;
                }

                function valOrDash(v) {
                    return (v && String(v).trim()) ? $('<div>').text(v).html() : '<em>—</em>';
                }

                function rowInline(label1, value1, label2, value2) {
                    return `
                        <div style="display:flex;gap:8px;padding:6px 10px;border-bottom:1px solid #e5e7eb;flex-wrap:wrap;">
                            <div style="display:flex;gap:4px;min-width:250px;flex:1;word-wrap:break-word;overflow-wrap:break-word;">
                                <div style="color:#64748b;font-weight:600;font-size:14px;flex-shrink:0;">${label1}:</div>
                                <div style="color:#0f172a;font-weight:600;font-size:14px;word-wrap:break-word;overflow-wrap:break-word;">${valOrDash(value1)}</div>
                        </div>
                            <div style="display:flex;gap:4px;min-width:250px;flex:1;word-wrap:break-word;overflow-wrap:break-word;">
                                ${label2 ? `<div style=\"color:#64748b;font-weight:600;font-size:14px;flex-shrink:0;\">${label2}:</div>` : ''}
                                ${label2 ? `<div style=\"color:#0f172a;font-weight:600;font-size:14px;word-wrap:break-word;overflow-wrap:break-word;\">${valOrDash(value2)}</div>` : ''}
                            </div>
                        </div>`;
                }

                function rowSingle(label, value) {
                    return `
                        <div style="padding:6px 10px;border-bottom:1px solid #e5e7eb;background:#fafafa;text-align:left;">
                            <div style="font-size:14px;line-height:1.3;text-align:left;">
                                <span style="color:#64748b;font-weight:600;">${label}:</span><span style="color:#0f172a;font-weight:600;white-space:pre-wrap;word-wrap:break-word;">${valOrDash(value)}</span>
                            </div>
                        </div>`;
                }

                // Compose fields
                const firstName = textOf('first_name');
                const middleName = textOf('middle_name');
                const lastName = textOf('last_name');
                const suffix = textOf('suffix');
                const fullName = [firstName, middleName, lastName, suffix].filter(Boolean).join(' ');

                const birthMonth = textOf('birth_month');
                const birthDay = textOf('birth_day');
                const birthYear = textOf('birth_year');
                const dob = [birthMonth, birthDay, birthYear].filter(Boolean).join(' ');
                const age = $('#age').val();

                const email = textOf('email_address');
                const contact = textOf('contact_number');

                const residency = textOf('residency_type');
                let addressDisplay = '';
                
                console.log('Residency type:', residency);
                
                if (residency === 'bago') {
                    const city = 'Bago City';
                    const barangay = textOf('barangay');
                    const purok = textOf('purok');
                    console.log('Bago - barangay:', barangay, 'purok:', purok);
                    addressDisplay = [city, barangay ? `, ${barangay}` : '', purok ? ` , Purok ${purok}` : ''].join('').trim();
                } else if (residency === 'non-bago') {
                    const city = textOf('non_bago_city');
                    const barangay = textOf('non_bago_barangay');
                    const purok = textOf('non_bago_purok');
                    console.log('Non-Bago - city:', city, 'barangay:', barangay, 'purok:', purok);
                    addressDisplay = [city, barangay ? `, ${barangay}` : '', purok ? ` , Purok ${purok}` : ''].join('').trim();
                } else if (residency === 'foreigner') {
                    const country = textOf('foreigner_country');
                    const state = textOf('foreigner_state');
                    const city = textOf('foreigner_city');
                    console.log('Foreigner - country:', country, 'state:', state, 'city:', city);
                    addressDisplay = [country, state, city].filter(Boolean).join(', ');
                } else {
                    // No residency type selected or invalid
                    console.log('No valid residency type selected, residency value:', residency);
                    addressDisplay = 'Please select residency type first';
                }
                
                console.log('Final address display:', addressDisplay);
                
                // Debug: Check if address is empty and show fallback
                if (!addressDisplay || addressDisplay.trim() === '' || addressDisplay === 'Please select residency type first') {
                    addressDisplay = 'Address not provided';
                }

                const civilStatus = textOf('civil_status');
                const education = textOf('education');
                const religion = textOf('religion');
                const nationality = textOf('nationality');
                const weddingType = textOf('wedding_type');
                const employment = textOf('employment_status');
                const occupation = textOf('occupation');
                const income = textOf('monthly_income');

				const heardFP = textOf('heard_fp');
				const facility = textOf('fp_facility') === 'Other' ? `Other: ${textOf('other_facility')}` : textOf('fp_facility');
				const channel = textOf('fp_channel') === 'Others' ? `Others: ${textOf('other_channel')}` : textOf('fp_channel');
				const notHeardReason = textOf('not_heard_reason');

				const intendFP = textOf('intend_fp');
				// Preferred methods (combine sensible answers)
				const femaleMethodType = textOf('fp_female_method');
				const maleMethodType = textOf('fp_male_method');
				const femaleMethod = femaleMethodType === 'Natural' ? textOf('female_natural_method') : 
									femaleMethodType === 'Other' ? `Other: ${textOf('female_other_method')}` : femaleMethodType;
				const maleMethod = maleMethodType === 'Natural' ? textOf('male_natural_method') : 
									maleMethodType === 'Other' ? `Other: ${textOf('male_other_method')}` : maleMethodType;
				const preferredMethod = [femaleMethod, maleMethod].filter(Boolean).join(' / ');

				const pastChildren = textOf('past_children');
				const pastChildrenCount = textOf('past_children_count');
				const philhealth = textOf('philhealth_member');
				const marriageReasons = textOf('marriage_reasons');

                // Header
                const headerTitle = `${'<?= ucfirst($respondent) ?>'} Profile Form`;

                let html = `
                    <div style="max-width:1000px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,0.06);text-align:left;">
                        <div style="padding:12px 16px;border-bottom:2px solid #3b82f6;display:flex;align-items:center;gap:8px;">
                            <div style="background:#3b82f6;color:#fff;width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center"><i class="fas fa-id-card"></i></div>
                            <div style="font-size:18px;font-weight:800;color:#0f172a;">${headerTitle}</div>
                        </div>
                        <div style="padding:4px 0 1px 0;background:#e5e7eb"></div>
                        <div style="padding:6px 8px;">
                            ${rowInline('Name', fullName, 'Date of Birth', dob + (age ? ` (Age: ${age})` : ''))}
                            ${rowInline('Email Address', email, 'Contact Number', contact)}
                            ${rowInline('Residency Status', residency, 'Address', addressDisplay)}
                            ${rowInline('Civil Status', civilStatus, 'Highest Education Attainment', education)}
                            ${rowInline('Religion', religion, 'Nationality', nationality)}
                            ${rowInline('Wedding Type', weddingType, '', '')}
                            ${rowInline('Employment Status', employment, 'Occupation', occupation)}
							${rowInline('Monthly Income', income, 'PhilHealth Member', philhealth)}
							${rowSingle('Have you heard Family Planning', heardFP)}
							${heardFP === 'Yes' ? rowSingle('Where did you hear about it? (Facility)', facility) : ''}
							${heardFP === 'Yes' ? rowSingle('How did you hear about it? (Channel)', channel) : ''}
							${heardFP === 'No' ? rowSingle("Why haven't you heard about Family Planning?", notHeardReason) : ''}
							<div style="height:4px"></div>
							${rowSingle('Do you intend to use Family Planning methods?', intendFP)}
							${intendFP === 'Yes' ? rowSingle('Preferred Method', preferredMethod) : ''}
							<div style="height:4px"></div>
							${rowSingle('Do you have children from past union/marriages?', pastChildren)}
							${pastChildren === 'Yes' ? rowSingle('Number of children', pastChildrenCount) : ''}
							<div style="height:4px"></div>
							${rowSingle('What are your reasons for getting married?', marriageReasons)}
                        </div>
                        </div>
                    `;

                Swal.fire({
                    title: 'Review your details',
                    html: html,
                    icon: undefined,
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Submit',
                    cancelButtonText: 'Edit',
                    reverseButtons: true,
                    width: 1000,
                    didOpen: () => {
                        const icon = document.querySelector('.swal2-icon');
                        if (icon) icon.style.display = 'none';
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Interactive loading animation then submit
                            let progress = 0;
                            let dots = 0;
                            Swal.fire({
                            title: 'Finalizing your profile',
                                html: `
                                    <div style="text-align:left">
                                    <div id="qStatus" style="font-size:14px;color:#6b7280;margin-bottom:8px;">Submitting<span id="qdotz"></span></div>
                                        <div style="height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden">
                                        <div id="qBar" style="height:100%;width:0%;background:linear-gradient(90deg,#60a5fa,#2563eb);transition:width .2s"></div>
                                        </div>
                                        <div style="display:flex;gap:8px;margin-top:10px;color:#6b7280;font-size:12px">
                                        <span id="qs1" style="opacity:.6">✓ Validating</span>
                                        <span id="qs2" style="opacity:.6">• Saving</span>
                                        <span id="qs3" style="opacity:.6">• Completing</span>
                                        </div>
                                    </div>
                                `,
                                allowOutsideClick: false,
                                showConfirmButton: false,
                                willOpen: () => {
                                const bar = document.getElementById('qBar');
                                const dotz = document.getElementById('qdotz');
                                const status = document.getElementById('qStatus');
                                const s1 = document.getElementById('qs1');
                                const s2 = document.getElementById('qs2');
                                const s3 = document.getElementById('qs3');
                                    const interval = setInterval(() => {
                                        progress = Math.min(100, progress + Math.floor(Math.random() * 12) + 5);
                                        if (bar) bar.style.width = progress + '%';
                                        dots = (dots + 1) % 4;
                                        if (dotz) dotz.textContent = '.'.repeat(dots);
                                        if (progress > 30) { s1.style.opacity = '1'; }
                                    if (progress > 60) { s2.style.opacity = '1'; status.textContent = 'Saving'; }
                                    if (progress >= 95) { s3.style.opacity = '1'; status.textContent = 'Completing'; }
                                        if (progress >= 100) {
                                            clearInterval(interval);
                                        // Submit form via AJAX to handle response properly
                                        const form = document.querySelector("form[action*='couple_profile.php']") || document.getElementById('profileForm') || document.querySelector('form');
                                        if (form) {
                                            const formData = new FormData(form);
                                            formData.append('submit_profile', '1');
                                            
                                            fetch(form.action, {
                                                method: 'POST',
                                                body: formData
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                if (data.success) {
                                                    window.location.href = data.redirect;
                                                } else {
                                                    Swal.fire('Error', data.message || 'Submission failed', 'error');
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error:', error);
                                                Swal.fire('Error', 'Submission failed', 'error');
                                            });
                                        }
                                        }
                                    }, 180);
                                }
                            });
                    }
                });
            }


            // ===== INITIALIZATION =====
            updateProgressBar();
            updateButtons();
            // Don't load barangays by default - only when Bago is selected
            // loadBarangays();

            // Initialize conditional fields based on respondent type
            $('#femaleMethods').toggle(respondentType === 'female');
            $('#maleMethods').toggle(respondentType === 'male');
            $('#femaleQuestions').toggle(respondentType === 'female');

            // Trigger residency type change if there's a saved value
            if ($('#residencyType').val()) {
                $('#residencyType').trigger('change');
            }

            // Initialize all conditional fields on page load
            function initializeConditionalFields() {
                // First, handle residency and address fields with special timing for barangay loading
                if ($('#residencyType').val()) {
                    $('#residencyType').trigger('change');
                    
                    // For Bago residents, wait for barangay options to load before setting values
                    if ($('#residencyType').val() === 'bago') {
                        const barangaySelect = $('#barangay');
                        const purokInput = $('input[name="purok"]');
                        
                        // Use global draft data if available, otherwise get from localStorage
                        let draft = window.savedDraft;
                        if (!draft) {
                            const savedBarangay = localStorage.getItem(`profileDraft:${accessCode}:${respondent}`);
                            if (savedBarangay) {
                                try {
                                    draft = JSON.parse(savedBarangay);
                                } catch (e) {
                                    console.error('Error parsing saved draft:', e);
                                }
                            }
                        }
                        
                        if (draft) {
                            // Wait for barangay options to be loaded
                            let attempts = 0;
                            const maxAttempts = 30;
                            const checkBarangay = setInterval(() => {
                                attempts++;
                                if (barangaySelect.length && barangaySelect.find('option').length > 1) {
                                    // Barangay options are loaded, now set the values
                                    if (draft.barangay) {
                                        // Set barangay value
                                        barangaySelect.val(draft.barangay);
                                        barangaySelect.trigger('change');
                                    }
                                    if (draft.purok && purokInput.length) {
                                        // Set purok value
                                        purokInput.val(draft.purok);
                                        purokInput.trigger('input');
                                    }
                                    clearInterval(checkBarangay);
                                }
                                if (attempts >= maxAttempts) {
                                    // Fallback: try to set values even if options aren't fully loaded
                                    if (draft.barangay && barangaySelect.length) {
                                        barangaySelect.val(draft.barangay);
                                    }
                                    if (draft.purok && purokInput.length) {
                                        purokInput.val(draft.purok);
                                    }
                                    clearInterval(checkBarangay);
                                }
                            }, 100);
                        }
                    }
                }
                
                // Civil status fields
                if ($('#civilStatus').val()) {
                    $('#civilStatus').trigger('change');
                }
                
                // Religion fields
                if ($('#religion').val()) {
                    $('#religion').trigger('change');
                }
                
                // Family planning fields
                if ($('#heardFamilyPlanning').val()) {
                    $('#heardFamilyPlanning').trigger('change');
                }
                
                // Facility and channel fields
                if ($('#facilityType').val()) {
                    $('#facilityType').trigger('change');
                }
                if ($('#channelType').val()) {
                    $('#channelType').trigger('change');
                }
                
                // Intend FP fields
                if ($('#intendFP').val()) {
                    $('#intendFP').trigger('change');
                }
                
                // Method type fields
                if ($('#femaleMethodType').val()) {
                    $('#femaleMethodType').trigger('change');
                }
                if ($('#maleMethodType').val()) {
                    $('#maleMethodType').trigger('change');
                }
                
                // Female-specific fields
                if ($('#currentlyPregnant').val()) {
                    $('#currentlyPregnant').trigger('change');
                }
                
                // Desired children fields
                if ($('#desiredChildren').val()) {
                    $('#desiredChildren').trigger('change');
                }
                
                // Past children fields
                if ($('#pastChildren').val()) {
                    $('#pastChildren').trigger('change');
                }
            }
            
            // Run initialization after a short delay to ensure all elements are ready
            setTimeout(initializeConditionalFields, 200);

            // Add tooltips to all elements with title attribute
            $('[title]').tooltip({
                trigger: 'hover',
                placement: 'top'
            });


        });
    </script>

    <style>
        /* Base Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .card {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card-header {
            border-bottom: none;
            padding: 1.25rem;
        }

        .card-body {
            padding: 2rem;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .required-field:after {
            content: " *";
            color: #dc3545;
        }

        /* Form Progress Indicator */
        .form-progress {
            margin-bottom: 2rem;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            position: relative;
        }

        .progress-steps:before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #e9ecef;
            z-index: 1;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .step-label {
            font-size: 0.875rem;
            color: #6c757d;
            text-align: center;
            transition: all 0.3s ease;
        }

        .step.active .step-circle {
            background-color: #2A3F54;
            color: white;
            transform: scale(1.1);
        }

        .step.active .step-label {
            color: #2A3F54;
            font-weight: 600;
        }

        .step.completed .step-circle {
            background-color: #28a745;
            color: white;
        }

        .step.completed .step-label {
            color: #28a745;
        }

        /* Progress Bar */
        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
        }

        .progress-bar {
            transition: width 0.6s ease;
        }

        /* Male Progress Bar - Blue with White Stripes */
        .male-progress {
            background-color: #3c8dbc;
            background-image: linear-gradient(45deg,
                    rgba(255, 255, 255, 0.3) 25%,
                    transparent 25%,
                    transparent 50%,
                    rgba(255, 255, 255, 0.3) 50%,
                    rgba(255, 255, 255, 0.3) 75%,
                    transparent 75%,
                    transparent);
        }

        /* Female Progress Bar - Pink with White Stripes */
        .female-progress {
            background-color: #ff6b9d;
            background-image: linear-gradient(45deg,
                    rgba(255, 255, 255, 0.3) 25%,
                    transparent 25%,
                    transparent 50%,
                    rgba(255, 255, 255, 0.3) 50%,
                    rgba(255, 255, 255, 0.3) 75%,
                    transparent 75%,
                    transparent);
        }

        /* Animation for both */
        .progress-bar-animated {
            animation: progress-bar-stripes 1s linear infinite;
        }

        @keyframes progress-bar-stripes {
            from {
                background-position: 1rem 0;
            }

            to {
                background-position: 0 0;
            }
        }

        /* Form Sections */
        .section-title {
            color: #2A3F54;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        /* Input Groups */
        .input-group-text {
            min-width: 40px;
            justify-content: center;
            background-color: #f8f9fa;
            border-right: none;
        }

        .form-control {
            border-left: none;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #ced4da;
        }

        /* Validation Styles */
        .is-invalid {
            border-color: #dc3545;
            background-image: none;
        }

        .is-invalid:focus {
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .invalid-feedback {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Buttons */
        .btn {
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #3c8dbc;
            border-color: #3c8dbc;
        }

        .btn-primary:hover {
            background-color: #367fa9;
            border-color: #367fa9;
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }

        .btn-success:hover {
            background-color: #218838;
            border-color: #218838;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
        }

        /* Male/Female Form Styling */
        .male-form .card-header {
            background-color: #3c8dbc !important;
        }

        .female-form .card-header {
            background-color: #ff6b9d !important;
        }

        .male-form .btn-primary {
            background-color: #3c8dbc;
            border-color: #3c8dbc;
        }

        .female-form .btn-primary {
            background-color: #ff6b9d;
            border-color: #ff6b9d;
        }

        .male-form .btn-primary:hover {
            background-color: #367fa9;
            border-color: #367fa9;
        }

        .female-form .btn-primary:hover {
            background-color: #e55d8c;
            border-color: #e55d8c;
        }

        /* Textarea styling */
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* Autocomplete styling */
        .ui-autocomplete {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            background-color: white;
            z-index: 1000 !important;
        }

        .ui-autocomplete .ui-menu-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f8f9fa;
        }

        .ui-autocomplete .ui-menu-item:hover {
            background-color: #f8f9fa;
        }

        .ui-autocomplete .ui-state-active {
            background-color: #f8f9fa;
            border-color: #f8f9fa;
            color: #495057;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }

            .form-row>div {
                margin-bottom: 1rem;
            }

            .step-label {
                font-size: 0.75rem;
            }

            .section-title {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .card-body {
                padding: 1rem;
            }

            .step-circle {
                width: 25px;
                height: 25px;
                font-size: 0.875rem;
            }

            .input-group-prepend .input-group-text {
                min-width: 35px;
            }
        }

        /* Animation classes */
        .animate__animated {
            --animate-duration: 0.5s;
        }

        /* Capitalize first letter */
        .capitalize {
            text-transform: capitalize;
        }

        /* Input group validation fix */
        .has-validation .form-control.is-invalid {
            z-index: 3;
        }

        .has-validation .input-group-prepend+.form-control.is-invalid {
            gap: 10px;
            border-left: 1px solid #dc3545;
        }

        /* Card footer button alignment */
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-footer>div {
            display: flex;
            gap: 10px;
        }

        .card-footer>div:last-child {
            margin-left: auto;
        }

        /* Address section styling */
        #addressSection {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            background-color: #f8f9fa;
            margin-top: 1rem;
        }

        #bagoAddressFields,
        #nonBagoAddressFields,
        #foreignerAddressFields {
            animation-duration: 0.5s;
        }

        /* Readonly fields styling */
        .form-control[readonly] {
            background-color: #f8f9fa;
            border-color: #ced4da;
            color: #495057;
        }

        /* Birthdate container styling */
        .birthdate-container {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            padding: 15px;
            background-color: #f8f9fa;
        }

        .birthdate-container .form-row {
            margin-left: -5px;
            margin-right: -5px;
        }

        .birthdate-container .col-md-4 {
            padding-left: 5px;
            padding-right: 5px;
        }
    </style>
</body>

</html>