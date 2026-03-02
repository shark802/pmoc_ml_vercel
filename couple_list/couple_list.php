<?php
require_once '../includes/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Couple list</title>
    <?php include '../includes/header.php'; ?>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>
        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <!-- Page Header -->
                    <div class="d-flex align-items-center mb-4" style="gap:10px;">
                        <i class="fas fa-users text-primary"></i>
                        <h4 class="mb-0">Couple Management</h4>
                    </div>
                    <p class="text-muted" style="margin-top:-6px;">Manage registered couples and their information</p>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <?php include '../includes/messages.php'; ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Couple List</h3>
                        </div>
                        <div class="card-body">
                            <table id="couplesTable" class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Access Code</th>
                                        <th>Male Details</th>
                                        <th>Female Details</th>
                                        <th style="min-width:120px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $query = "SELECT 
                                                cp.*, 
                                                ca.access_code,
                                                ca.code_status
                                            FROM couple_profile cp
                                            LEFT JOIN couple_access ca 
                                                ON cp.access_id = ca.access_id
                                            ORDER BY ca.date_created DESC";
                                    $result = mysqli_query($conn, $query);

                                    $currentAccessId = null;
                                    $maleProfile = null;
                                    $femaleProfile = null;

                                    function displayCoupleRow($male, $female)
                                    {
                                        echo '<tr>';
                                        // Access Code
                                        echo '<td>' . htmlspecialchars($male['access_code'] ?? 'N/A') . '</td>';
                                        // Male Details
                                        echo '<td>';
                                        if ($male) {
                                            $maleDob = !empty($male['date_of_birth']) ?
                                                date('F j, Y', strtotime($male['date_of_birth'])) :
                                                'N/A';

                                            echo 'Name: ' . htmlspecialchars($male['first_name']) . ' ' .
                                                htmlspecialchars($male['middle_name']) . ' ' .
                                                htmlspecialchars($male['last_name']) . '<br>';
                                            echo 'Birth Date: ' . htmlspecialchars($maleDob) . '<br>';
                                            echo 'Age: ' . htmlspecialchars($male['age']) . '<br>';
                                            echo 'Contact: ' . htmlspecialchars($male['contact_number']);
                                        }
                                        echo '</td>';

                                        // Female Details
                                        echo '<td>';
                                        if ($female) {
                                            $femaleDob = !empty($female['date_of_birth']) ?
                                                date('F j, Y', strtotime($female['date_of_birth'])) :
                                                'N/A';

                                            echo 'Name: ' . htmlspecialchars($female['first_name']) . ' ' .
                                                htmlspecialchars($female['middle_name']) . ' ' .
                                                htmlspecialchars($female['last_name']) . '<br>';
                                            echo 'Birth Date: ' . htmlspecialchars($femaleDob) . '<br>';
                                            echo 'Age: ' . htmlspecialchars($female['age']) . '<br>';
                                            echo 'Contact: ' . htmlspecialchars($female['contact_number']);
                                        }
                                        echo '</td>';

                                        // Actions
                                        echo '<td>';
                                        // View Button
                                        echo '<a href="couple_details_form.php?access_id=' . htmlspecialchars($male['access_id'] ?? $female['access_id']) . '" 
                                            class="btn btn-sm btn-outline-primary mr-2"
                                            title="View Profile">
                                            <i class="fas fa-eye"></i> View Profile
                                        </a>';

                                        // Response Button
                                        echo '<a href="../couple_response/couple_response.php?access_id=' . htmlspecialchars($male['access_id'] ?? $female['access_id']) . '" 
                                            class="btn btn-sm btn-outline-secondary"
                                            title="View Responses">
                                            <i class="fas fa-file-alt"></i> View Response
                                        </a>';

                                        echo '</td>';
                                        echo '</tr>';
                                    }

                                    while ($row = mysqli_fetch_assoc($result)) {
                                        if ($row['access_id'] != $currentAccessId) {
                                            if ($currentAccessId !== null) {
                                                displayCoupleRow($maleProfile, $femaleProfile);
                                            }
                                            $currentAccessId = $row['access_id'];
                                            $maleProfile = null;
                                            $femaleProfile = null;
                                        }

                                        if ($row['sex'] == 'Male') {
                                            $maleProfile = $row;
                                        } else {
                                            $femaleProfile = $row;
                                        }
                                    }

                                    if ($currentAccessId !== null) {
                                        displayCoupleRow($maleProfile, $femaleProfile);
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <?php include '../includes/footer.php'; ?>
    </div>

    <?php include '../includes/scripts.php'; ?>

    <script>
        // Ensure jQuery is loaded before proceeding
        (function() {
            function waitForJQuery(callback) {
                if (typeof window.jQuery !== 'undefined') {
                    if (typeof window.$ === 'undefined') {
                        window.$ = window.jQuery;
                    }
                    callback();
                } else {
                    setTimeout(function() {
                        waitForJQuery(callback);
                    }, 50);
                }
            }

            // Prevent $ from being used before jQuery loads
            if (typeof window.$ === 'undefined') {
                window.$ = function() {
                    if (typeof window.jQuery !== 'undefined') {
                        window.$ = window.jQuery;
                        return window.jQuery.apply(window.jQuery, arguments);
                    }
                    console.error('jQuery ($) used before jQuery is loaded');
                    return null;
                };
            }

            waitForJQuery(function() {
                if (typeof window.jQuery !== 'undefined') {
                    window.$ = window.jQuery;
                }
                
                $(document).ready(function() {
            $('#couplesTable').DataTable({
                "responsive": true,
                "autoWidth": false
            });
        });
            }); // End of waitForJQuery
        })();
    </script>

    <style>
        .main-footer {
            margin-top: 20px;
            padding: 15px;
            background-color: #f4f6f9;
            border-top: 1px solid #dee2e6;
        }
        .content-wrapper {
            min-height: calc(100vh - 200px);
        }
    </style>
</body>
</html>