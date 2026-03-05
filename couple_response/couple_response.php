<?php
session_start();
require_once '../includes/conn.php';

// Require a specific couple; if none provided, redirect to the list
if (!isset($_GET['access_id']) || (int)$_GET['access_id'] <= 0) {
    header('Location: ../couple_list/couple_list.php');
    exit();
}

function getResponseClass($response) {
    return match(strtolower($response)) {
        'agree'    => 'success',
        'neutral'  => 'warning',
        'disagree' => 'danger',
        default    => 'secondary',
    };
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Couple Responses - BCPDO System</title>
    <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

<?php include '../includes/navbar.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-12 col-sm-6">
                    <h1 class="m-0"><i class="fas fa-comments mr-2"></i>Couple Responses</h1>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="d-flex justify-content-sm-end justify-content-start mt-2 mt-sm-0 no-print">
                        <a href="../couple_list/couple_list.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            
            <?php
            $summary = [
                'access_code' => '',
                'male_name' => '',
                'male_residency_type' => '',
                'female_name' => '',
                'female_residency_type' => ''
            ];
            $categories = [];

            try {
                $accessId = (int)$_GET['access_id'];
                $sql = "
                    SELECT 
                        ca.access_code,
                        qc.category_name,
                        male_cr.question_id AS question_id,
                        male_cr.sub_question_id AS sub_question_id,
                        qa.question_text,
                        COALESCE(sqa.sub_question_text, '—') AS sub_question_text,
                        CONCAT(male_cp.first_name, ' ', male_cp.middle_name, ' ', male_cp.last_name) AS male_name,
                        male_cp.residency_type AS male_residency_type,
                        male_cr.response AS male_response,
                        male_cr.reason AS male_reason,
                        CONCAT(female_cp.first_name, ' ', female_cp.middle_name, ' ', female_cp.last_name) AS female_name,
                        female_cp.residency_type AS female_residency_type,
                        female_cr.response AS female_response,
                        female_cr.reason AS female_reason
                    FROM couple_responses male_cr
                    LEFT JOIN couple_responses female_cr 
                        ON male_cr.access_id = female_cr.access_id 
                        AND male_cr.category_id = female_cr.category_id 
                        AND male_cr.question_id = female_cr.question_id 
                        AND COALESCE(male_cr.sub_question_id, 0) = COALESCE(female_cr.sub_question_id, 0)
                        AND female_cr.respondent = 'female'
                    INNER JOIN couple_access ca 
                        ON male_cr.access_id = ca.access_id
                    INNER JOIN question_category qc 
                        ON male_cr.category_id = qc.category_id
                    INNER JOIN question_assessment qa 
                        ON male_cr.question_id = qa.question_id
                    LEFT JOIN sub_question_assessment sqa 
                        ON male_cr.sub_question_id = sqa.sub_question_id
                    LEFT JOIN couple_profile male_cp 
                        ON male_cr.access_id = male_cp.access_id 
                        AND male_cp.sex = 'Male'
                    LEFT JOIN couple_profile female_cp 
                        ON male_cr.access_id = female_cp.access_id 
                        AND female_cp.sex = 'Female'
                    WHERE male_cr.respondent = 'male' AND male_cr.access_id = ?
                    ORDER BY qc.category_name, qa.question_id, COALESCE(sqa.sub_question_text, '\\u2014')";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $accessId);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $categoryName = $row['category_name'] ?? 'Uncategorized';
                    $questionId = (int)($row['question_id'] ?? 0);
                    $subQuestionId = $row['sub_question_id'] !== null ? (int)$row['sub_question_id'] : null;

                    if (!isset($categories[$categoryName])) {
                        $categories[$categoryName] = [
                            'questions' => [],
                        ];
                    }

                    if (!isset($categories[$categoryName]['questions'][$questionId])) {
                        $categories[$categoryName]['questions'][$questionId] = [
                            'question_text' => $row['question_text'] ?? 'Unknown Question',
                            'subs' => [],
                            'has_mismatch' => false
                        ];
                    }

                    // Update summary once
                    if ($summary['access_code'] === '') {
                        $summary['access_code'] = $row['access_code'] ?? '';
                    }
                    if ($summary['male_name'] === '' && !empty($row['male_name'])) {
                        $summary['male_name'] = $row['male_name'];
                        $summary['male_residency_type'] = $row['male_residency_type'] ?? '';
                    }
                    if ($summary['female_name'] === '' && !empty($row['female_name'])) {
                        $summary['female_name'] = $row['female_name'];
                        $summary['female_residency_type'] = $row['female_residency_type'] ?? '';
                    }

                    $maleResponse = strtolower($row['male_response'] ?? '');
                    $femaleResponse = strtolower($row['female_response'] ?? '');
                    $isMismatch = ($maleResponse !== '' && $femaleResponse !== '' && $maleResponse !== $femaleResponse);

                    $categories[$categoryName]['questions'][$questionId]['subs'][] = [
                        'sub_question_id' => $subQuestionId,
                        'sub_question_text' => $row['sub_question_text'] ?? '—',
                        'male_name' => $row['male_name'] ?: 'Anonymous (Male)',
                        'female_name' => $row['female_name'] ?: 'Anonymous (Female)',
                        'male_response' => $maleResponse ?: '—',
                        'female_response' => $femaleResponse ?: '—',
                        'male_reason' => $row['male_reason'] ?? '',
                        'female_reason' => $row['female_reason'] ?? '',
                        'mismatch' => $isMismatch
                    ];

                    if ($isMismatch) {
                        $categories[$categoryName]['questions'][$questionId]['has_mismatch'] = true;
                    }
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Error loading data: '.htmlspecialchars($e->getMessage()).'</div>';
            }

            // Compute totals based on unique questions and sub-questions
            $totalQuestions = 0;
            $totalQuestionMismatches = 0;
            $totalSubQuestions = 0;
            $totalSubMismatches = 0;
            
            foreach ($categories as $cat) {
                $totalQuestions += count($cat['questions']);
                foreach ($cat['questions'] as $q) {
                    if (!empty($q['has_mismatch'])) {
                        $totalQuestionMismatches++;
                    }
                    // Count unique sub-questions (not database rows)
                    $uniqueSubs = [];
                    foreach ($q['subs'] as $s) {
                        $subId = $s['sub_question_id'];
                        if ($subId !== null && !in_array($subId, $uniqueSubs)) {
                            $uniqueSubs[] = $subId;
                            $isRealSub = !empty($s['sub_question_text']) && $s['sub_question_text'] !== '—';
                            if ($isRealSub) {
                                $totalSubQuestions++;
                                if (!empty($s['mismatch'])) {
                                    $totalSubMismatches++;
                                }
                            }
                        }
                    }
                }
            }
            ?>

            <!-- Summary Header -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body d-flex flex-wrap align-items-center">
                            <div class="mr-3 mb-2 mb-sm-0">
                                <span class="text-muted">Access Code</span>
                                <div class="h5 mb-0"><?= htmlspecialchars($summary['access_code'] ?: 'N/A') ?></div>
                            </div>
                            <div class="mr-3 mb-2 mb-sm-0">
                                <span class="text-muted">Male</span>
                                <div class="h5 mb-0">
                                    <i class="fas fa-male text-primary mr-1"></i>
                                    <?= htmlspecialchars($summary['male_name'] ?: '—') ?>
                                    <?php if (!empty($summary['male_residency_type'])): ?>
                                        <span class="badge badge-info ml-1">(<?= htmlspecialchars($summary['male_residency_type']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mr-3 mb-2 mb-sm-0">
                                <span class="text-muted">Female</span>
                                <div class="h5 mb-0">
                                    <i class="fas fa-female text-danger mr-1"></i>
                                    <?= htmlspecialchars($summary['female_name'] ?: '—') ?>
                                    <?php if (!empty($summary['female_residency_type'])): ?>
                                        <span class="badge badge-info ml-1">(<?= htmlspecialchars($summary['female_residency_type']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="ml-auto text-muted small mb-2 text-right">
                                <div><strong>Questions:</strong> <?= (int)$totalQuestions ?> &nbsp;|&nbsp; <strong>Mismatches:</strong> <?= (int)$totalQuestionMismatches ?></div>
                                <div><strong>Sub-questions:</strong> <?= (int)$totalSubQuestions ?> &nbsp;|&nbsp; <strong>Mismatches:</strong> <?= (int)$totalSubMismatches ?></div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Categories and Questions -->
            <?php if (!empty($categories)): ?>
                <!-- Category Cards Grid -->
                <div class="row mb-4">
                    <?php 
                    $catIndex = 0; 
                    $categoryStats = [];
                    foreach ($categories as $categoryName => $categoryData): 
                        $catIndex++; 
                        $questions = $categoryData['questions'];
                        $categoryQuestionCount = count($questions);
                        $categoryQuestionMismatchCount = 0;
                        $categorySubCount = 0;
                        $categorySubMismatchCount = 0;
                        foreach ($questions as $q) {
                            if (!empty($q['has_mismatch'])) { $categoryQuestionMismatchCount++; }
                            foreach ($q['subs'] as $s) {
                                $isRealSub = !empty($s['sub_question_text']) && $s['sub_question_text'] !== '—';
                                if ($isRealSub) {
                                    $categorySubCount++;
                                    if (!empty($s['mismatch'])) { $categorySubMismatchCount++; }
                                }
                            }
                        }
                        $categoryStats[$catIndex] = [
                            'name' => $categoryName,
                            'id' => 'cat-' . $catIndex,
                            'questionCount' => $categoryQuestionCount,
                            'mismatchCount' => $categoryQuestionMismatchCount
                        ];
                    ?>
                        <div class="col-md-6 col-lg-6 mb-3">
                            <div class="category-card-btn <?= $catIndex === 1 ? 'active' : '' ?>" 
                                 data-category="<?= 'cat-' . $catIndex ?>" 
                                 data-category-index="<?= $catIndex ?>"
                                 role="button" 
                                 tabindex="0">
                                <i class="fas fa-tag mr-2"></i>
                                <span class="category-name"><?= htmlspecialchars($categoryName) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12 text-right mt-2">
                        <a href="#" class="show-all-link" id="showAllLink">
                            <i class="fas fa-list mr-1"></i> Show All
                        </a>
                    </div>
                </div>

            <!-- Toolbar -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body py-2">
                            <div class="d-flex flex-wrap align-items-center">
                                <div class="custom-control custom-switch mb-2 mr-3">
                                    <input type="checkbox" class="custom-control-input" id="toggleMismatch">
                                    <label class="custom-control-label" for="toggleMismatch">Show mismatches only</label>
                                </div>
                                <div class="custom-control custom-switch mb-2 mr-3">
                                    <input type="checkbox" class="custom-control-input" id="toggleMatch">
                                    <label class="custom-control-label" for="toggleMatch">Show matches only</label>
                                </div>
                                <div class="mb-2 ml-auto">
                                    <span class="text-muted mr-2">Legend:</span>
                                    <span class="badge badge-success mr-2">Agree</span>
                                    <span class="badge badge-warning mr-2">Neutral</span>
                                    <span class="badge badge-danger">Disagree</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                <!-- Category Content -->
                <div id="categoryContent">
                    <?php $catIndex = 0; foreach ($categories as $categoryName => $categoryData): $catIndex++; ?>
                        <?php 
                            $questions = $categoryData['questions'];
                            $categoryId = 'cat-' . $catIndex;
                            $isActive = $catIndex === 1 ? '' : 'd-none';
                        ?>
                        <div class="category-content <?= $isActive ?>" id="<?= $categoryId ?>" data-category-index="<?= $catIndex ?>">
                            <div class="card">
                                <div class="card-body p-2">
                                <?php $qNumber = 1; foreach ($questions as $qid => $qData): ?>
                                    <?php 
                                        $subs = $qData['subs'];
                                        $hasMismatch = !empty($qData['has_mismatch']);
                                        $hasRealSubs = false; 
                                        foreach ($subs as $s) { if (!empty($s['sub_question_text']) && $s['sub_question_text'] !== '—') { $hasRealSubs = true; break; } }
                                    ?>
                                    <div class="question-block mb-3 border rounded overflow-hidden <?= $hasMismatch ? 'mismatch' : 'match' ?>">
                                        <div class="bg-light p-2 border-bottom d-flex align-items-center">
                                            <strong class="mr-2"><?= $qNumber ?>. <?= htmlspecialchars($qData['question_text']) ?></strong>
                                            <?php if ($hasMismatch): ?>
                                                <span class="badge badge-warning ml-auto">Mismatch</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="p-2">
                                            <?php if ($hasRealSubs): ?>
                                                <?php $subNum = 0; foreach ($subs as $sub): ?>
                                                    <?php if (!empty($sub['sub_question_text']) && $sub['sub_question_text'] !== '—'): ?>
                                                        <?php $subNum++; $label = $qNumber . '.' . $subNum; ?>
                                                        <div class="mb-2 <?= !empty($sub['mismatch']) ? 'mismatch' : 'match' ?>">
                                                            <div class="d-flex align-items-center mb-1">
                                                                <div class="font-weight-bold mr-2"><?= $label ?></div>
                                                                <div class="text-muted font-italic mr-2">—</div>
                                                                <div class="text-muted font-italic"><?= htmlspecialchars($sub['sub_question_text']) ?></div>
                                                                <?php if (!empty($sub['mismatch'])): ?>
                                                                    <span class="badge badge-warning ml-auto">Mismatch</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6 mb-2 mb-md-0">
                                                                    <div class="card card-primary h-100 mb-0">
                                                                        <div class="card-header py-2"><i class="fas fa-male mr-2"></i>Male</div>
                                                                        <div class="card-body py-2">
                                                                            
                                                                            <div class="mb-1"><strong>Response:</strong> <span class="badge badge-<?= getResponseClass($sub['male_response']) ?>"><?= ucfirst($sub['male_response']) ?></span></div>
                                                                            <?php if (trim((string)$sub['male_reason']) !== ''): ?>
                                                                                <div><strong>Reason:</strong>
                                                                                    <div class="text-muted clamp-3" style="line-clamp: 3; -webkit-line-clamp: 3; -webkit-box-orient: vertical; display: -webkit-box; overflow: hidden;">
                                                                                        <?= nl2br(htmlspecialchars($sub['male_reason'])) ?>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="card card-danger h-100 mb-0">
                                                                        <div class="card-header py-2"><i class="fas fa-female mr-2"></i>Female</div>
                                                                        <div class="card-body py-2">
                                                                            
                                                                            <div class="mb-1"><strong>Response:</strong> <span class="badge badge-<?= getResponseClass($sub['female_response']) ?>"><?= ucfirst($sub['female_response']) ?></span></div>
                                                                            <?php if (trim((string)$sub['female_reason']) !== ''): ?>
                                                                                <div><strong>Reason:</strong>
                                                                                    <div class="text-muted clamp-3" style="line-clamp: 3; -webkit-line-clamp: 3; -webkit-box-orient: vertical; display: -webkit-box; overflow: hidden;">
                                                                                        <?= nl2br(htmlspecialchars($sub['female_reason'])) ?>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?php $sub = $subs[0]; ?>
                                                <div class="row">
                                                    <div class="col-md-6 mb-2 mb-md-0">
                                                        <div class="card card-primary h-100 mb-0">
                                                            <div class="card-header py-2"><i class="fas fa-male mr-2"></i>Male</div>
                                                            <div class="card-body py-2">
                                                                
                                                                <div class="mb-1"><strong>Response:</strong> <span class="badge badge-<?= getResponseClass($sub['male_response']) ?>"><?= ucfirst($sub['male_response']) ?></span></div>
                                                                <?php if (trim((string)$sub['male_reason']) !== ''): ?>
                                                                    <div><strong>Reason:</strong>
                                                                        <div class="text-muted clamp-3" style="line-clamp: 3; -webkit-line-clamp: 3; -webkit-box-orient: vertical; display: -webkit-box; overflow: hidden;">
                                                                            <?= nl2br(htmlspecialchars($sub['male_reason'])) ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card card-danger h-100 mb-0">
                                                            <div class="card-header py-2"><i class="fas fa-female mr-2"></i>Female</div>
                                                            <div class="card-body py-2">
                                                                
                                                                <div class="mb-1"><strong>Response:</strong> <span class="badge badge-<?= getResponseClass($sub['female_response']) ?>"><?= ucfirst($sub['female_response']) ?></span></div>
                                                                <?php if (trim((string)$sub['female_reason']) !== ''): ?>
                                                                    <div><strong>Reason:</strong>
                                                                        <div class="text-muted clamp-3" style="line-clamp: 3; -webkit-line-clamp: 3; -webkit-box-orient: vertical; display: -webkit-box; overflow: hidden;">
                                                                            <?= nl2br(htmlspecialchars($sub['female_reason'])) ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php $qNumber++; ?>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0">No responses found.</div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
</div>

<?php include '../includes/scripts.php'; ?>
<script>
$(function() {
    // Category card click handler
    $('.category-card-btn').on('click', function() {
        const categoryId = $(this).data('category');
        const categoryIndex = $(this).data('category-index');
        
        // Remove active class from all cards
        $('.category-card-btn').removeClass('active');
        // Add active class to clicked card
        $(this).addClass('active');
        
        // Hide all category content
        $('.category-content').addClass('d-none');
        // Show selected category content
        $('#' + categoryId).removeClass('d-none');
        
        // Reapply filters
        applyFilters();
    });

    // Show All link handler
    $('#showAllLink').on('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all cards
        $('.category-card-btn').removeClass('active');
        
        // Show all category content
        $('.category-content').removeClass('d-none');
        
        // Reapply filters
        applyFilters();
    });

    // Mismatch/Match filtering functionality
    function applyFilters() {
        if ($('#toggleMismatch').is(':checked')) {
            $('.question-block').hide();
            $('.question-block.mismatch').show();
        } else if ($('#toggleMatch').is(':checked')) {
            $('.question-block').hide();
            $('.question-block.match').show();
        } else {
            $('.question-block').show();
        }
    }

    $('#toggleMismatch').on('change', function(){
        if (this.checked) {
            $('#toggleMatch').prop('checked', false);
        }
        applyFilters();
    });

    $('#toggleMatch').on('change', function(){
        if (this.checked) {
            $('#toggleMismatch').prop('checked', false);
        }
        applyFilters();
    });

    // Keyboard navigation for category cards
    $('.category-card-btn').on('keypress', function(e) {
        if (e.which === 13 || e.which === 32) { // Enter or Space
            e.preventDefault();
            $(this).click();
        }
    });
});
</script>

<style>
/* Responsive design improvements */
@media (max-width: 1200px) {
    .responsive-title { font-size: 1.5rem; }
}
@media (max-width: 768px) {
    .responsive-title { font-size: 1.25rem; }
}

/* Badge styling */
.badge { 
    white-space: nowrap; 
    font-size: 0.75rem;
}

/* Category Card Grid Styling */
.category-card-btn {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    padding: 1.25rem 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    font-weight: 500;
    color: #495057;
    min-height: 80px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.category-card-btn:hover {
    border-color: #adb5bd;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.category-card-btn.active {
    background: #6c757d;
    border-color: #6c757d;
    color: #fff;
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

.category-card-btn.active i {
    color: #fff;
}

.category-card-btn i {
    color: #6c757d;
    font-size: 1.1rem;
    transition: color 0.3s ease;
}

.category-card-btn.active:hover {
    background: #5a6268;
    border-color: #5a6268;
}

.category-name {
    flex: 1;
    font-size: 0.95rem;
    line-height: 1.4;
    word-break: break-word;
}

.show-all-link {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
    display: inline-flex;
    align-items: center;
}

.show-all-link:hover {
    color: #0056b3;
    text-decoration: underline;
}

.show-all-link i {
    font-size: 0.9rem;
}

/* Category Content Styling */
.category-content {
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .category-card-btn {
        padding: 1rem;
        min-height: 70px;
    }
    
    .category-name {
        font-size: 0.85rem;
    }
}

/* Question block styling */
.question-block.match { 
    border-left: 4px solid #28a745; 
    border-radius: 0.25rem;
}

.question-block.mismatch { 
    border-left: 4px solid #ffc107; 
    border-radius: 0.25rem;
}

/* Card styling improvements */
.card.card-primary .card-header {
    background-color: #007bff;
    color: white;
}

.card.card-danger .card-header {
    background-color: #dc3545;
    color: white;
}

/* Text clamp for long content */
.clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
}

/* Responsive improvements for mobile */
@media (max-width: 576px) {
    .card-body.d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .card-body.d-flex > div {
        margin-bottom: 1rem;
    }
    
    .ml-auto {
        margin-left: 0 !important;
        margin-top: 1rem;
    }
    
    .question-block .row {
        margin: 0;
    }
    
    .question-block .col-md-6 {
        padding: 0.5rem;
    }
}

/* Dark mode compatibility */
body.dark-mode .question-block {
    background-color: #343a40;
    border-color: rgba(255,255,255,0.12);
}

body.dark-mode .question-block .bg-light {
    background-color: #2f3640 !important;
    color: #f8f9fa;
}

body.dark-mode .text-muted {
    color: #c2c7d0 !important;
}
</style>
</body>
</html>