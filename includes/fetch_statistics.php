<?php
// Prevent any output before JSON - start output buffering immediately
if (!ob_get_level()) {
    ob_start();
}

// Turn off error display completely
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Suppress any warnings/notices that might output HTML
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log errors but don't output them
    error_log("PHP Error in fetch_statistics.php: [$errno] $errstr in $errfile on line $errline");
    return true; // Suppress default error handler
}, E_ALL);

// Set JSON header first (before any output)
header('Content-Type: application/json');

try {
    // Include connection file - suppress any output
    ob_start();
    require_once 'conn.php';
    ob_end_clean();
    
    // Check connection and handle errors properly
    if (!isset($conn) || ($conn instanceof mysqli && $conn->connect_error)) {
        throw new Exception('Database connection failed: ' . ($conn->connect_error ?? 'Unknown error'));
    }
} catch (Exception $e) {
    // Clear any output and send JSON error
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
} catch (Error $e) {
    // Catch fatal errors from conn.php
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Clear any output that might have been generated (whitespace, etc.)
ob_clean();

$range = $_POST['range'] ?? 'this_month';
$startDate = $_POST['start_date'] ?? null;
$endDate = $_POST['end_date'] ?? null;
$barangay = $_POST['barangay'] ?? 'all';

// Helper function to generate date filter SQL based on range
function getDateFilterSQL($range, $startDate = null, $endDate = null, $dateColumn = 'cp.date_of_filing') {
    if ($startDate && $endDate) {
        return " AND $dateColumn BETWEEN ? AND ?";
    }
    
    switch ($range) {
        case 'today':
            return " AND DATE($dateColumn) = CURDATE()";
        case 'yesterday':
            return " AND DATE($dateColumn) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        case 'past_week':
        case 'past_7_days':
            return " AND DATE($dateColumn) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
        case 'past_14_days':
            return " AND DATE($dateColumn) BETWEEN DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND CURDATE()";
        case 'past_30_days':
            return " AND $dateColumn BETWEEN ? AND ?";
        case 'this_month':
            return " AND $dateColumn BETWEEN ? AND ?";
        case 'this_year':
            return " AND YEAR($dateColumn) = YEAR(CURDATE())";
        case 'present_week':
        case 'this_week':
            return " AND DATE($dateColumn) BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)";
        case 'all_time':
        default:
            return ""; // No date filter for all time
    }
}

// Helper function to get date filter parameters for binding
function getDateFilterParams($range, $startDate = null, $endDate = null) {
    if ($startDate && $endDate) {
        return ['ss', $startDate, $endDate];
    }
    
    switch ($range) {
        case 'this_month':
            return ['ss', date('Y-m-01') . ' 00:00:00', date('Y-m-d') . ' 23:59:59'];
        case 'past_30_days':
            return ['ss', date('Y-m-d', strtotime('-29 days')) . ' 00:00:00', date('Y-m-d') . ' 23:59:59'];
        default:
            return null; // No parameters needed
    }
}

// Helper function to safely bind date parameters (extracts array values to variables for bind_param)
function bindDateParams($stmt, $dateParams, $barangay = 'all') {
    if ($dateParams && $barangay !== 'all') {
        $date1 = $dateParams[1];
        $date2 = $dateParams[2];
        $typeStr = $dateParams[0] . "s";
        $stmt->bind_param($typeStr, $date1, $date2, $barangay);
    } elseif ($dateParams) {
        $date1 = $dateParams[1];
        $date2 = $dateParams[2];
        $stmt->bind_param($dateParams[0], $date1, $date2);
    } elseif ($barangay !== 'all') {
        $stmt->bind_param("s", $barangay);
    }
}

try {
    // Registration Trend Data
    $registrationData = ['labels' => [], 'values' => []];
    
    // Log the range being processed for debugging
    error_log("Processing statistics for range: $range, barangay: $barangay");

    if ($range === 'today') {
        $sql = "SELECT HOUR(cp.date_of_filing) as period, COUNT(DISTINCT cp.access_id) as count 
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE DATE(cp.date_of_filing) = CURDATE()
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2 
                    WHERE cp2.access_id = cp.access_id 
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY HOUR(cp.date_of_filing) ORDER BY HOUR(cp.date_of_filing)";

        // Generate labels for 8am to 5pm
        for ($hour = 8; $hour <= 17; $hour++) {
            $registrationData['labels'][] = sprintf('%02d:00', $hour);
            $registrationData['values'][] = 0;
        }

        $stmt = $conn->prepare($sql);
        if ($barangay !== 'all') {
            $stmt->bind_param("s", $barangay);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $hour = $row['period'];
            if ($hour >= 8 && $hour <= 17) {
                $index = $hour - 8;
                $registrationData['values'][$index] = (int)$row['count'];
            }
        }
    } elseif ($range === 'yesterday') {
        $sql = "SELECT HOUR(cp.date_of_filing) as hour, COUNT(DISTINCT cp.access_id) as count 
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE DATE(cp.date_of_filing) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2 
                    WHERE cp2.access_id = cp.access_id 
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY HOUR(cp.date_of_filing) ORDER BY HOUR(cp.date_of_filing)";

        // Generate labels for 8am to 5pm
        for ($hour = 8; $hour <= 17; $hour++) {
            $registrationData['labels'][] = sprintf('%02d:00', $hour);
            $registrationData['values'][] = 0;
        }

        $stmt = $conn->prepare($sql);
        if ($barangay !== 'all') {
            $stmt->bind_param("s", $barangay);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $hour = $row['hour'];
            if ($hour >= 8 && $hour <= 17) {
                $index = $hour - 8;
                $registrationData['values'][$index] = (int)$row['count'];
            }
        }
    } elseif ($range === 'past_week' || $range === 'past_7_days') {
        // Past 7 days including today
        $sql = "SELECT DATE(cp.date_of_filing) as day_date, COUNT(DISTINCT cp.access_id) as count
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE DATE(cp.date_of_filing) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2 
                    WHERE cp2.access_id = cp.access_id 
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY DATE(cp.date_of_filing) ORDER BY DATE(cp.date_of_filing)";

        // Generate labels for the past 7 days
        $start = new DateTime(date('Y-m-d', strtotime('-6 days')));
        $end = new DateTime(date('Y-m-d'));
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        foreach ($period as $d) {
            $registrationData['labels'][] = $d->format('M j');
            $registrationData['values'][] = 0;
        }

        $stmt = $conn->prepare($sql);
        if ($barangay !== 'all') {
            $stmt->bind_param("s", $barangay);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $label = date('M j', strtotime($row['day_date']));
            $index = array_search($label, $registrationData['labels']);
            if ($index !== false) {
                $registrationData['values'][$index] = (int)$row['count'];
            }
        }
    } elseif ($range === 'past_14_days') {
        // Past 14 days including today
        $sql = "SELECT DATE(cp.date_of_filing) as day_date, COUNT(DISTINCT cp.access_id) as count
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE DATE(cp.date_of_filing) BETWEEN DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND CURDATE()
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2 
                    WHERE cp2.access_id = cp.access_id 
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY DATE(cp.date_of_filing) ORDER BY DATE(cp.date_of_filing)";

        // Generate labels for the past 14 days
        $start = new DateTime(date('Y-m-d', strtotime('-13 days')));
        $end = new DateTime(date('Y-m-d'));
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        foreach ($period as $d) {
            $registrationData['labels'][] = $d->format('M j');
            $registrationData['values'][] = 0;
        }

        $stmt = $conn->prepare($sql);
        if ($barangay !== 'all') {
            $stmt->bind_param("s", $barangay);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $label = date('M j', strtotime($row['day_date']));
            $index = array_search($label, $registrationData['labels']);
            if ($index !== false) {
                $registrationData['values'][$index] = (int)$row['count'];
            }
        }
    } elseif ($range === 'past_month') {
        $sql = "SELECT DAY(cp.date_of_filing) as day, COUNT(DISTINCT cp.access_id) as count 
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE cp.date_of_filing >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                AND cp.date_of_filing < CURDATE()
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2 
                    WHERE cp2.access_id = cp.access_id 
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY DAY(cp.date_of_filing) ORDER BY DAY(cp.date_of_filing)";

        // Generate labels for day 1 to 31
        $daysInMonth = date('t');
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $registrationData['labels'][] = 'Day ' . $day;
            $registrationData['values'][] = 0;
        }

        $stmt = $conn->prepare($sql);
        if ($barangay !== 'all') {
            $stmt->bind_param("s", $barangay);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $day = $row['day'];
            if ($day >= 1 && $day <= $daysInMonth) {
                $index = $day - 1;
                $registrationData['values'][$index] = (int)$row['count'];
            }
        }
    } elseif ($range === 'past_year' || $range === 'this_year') {
        // This year (calendar year to date)
        $sql = "SELECT DATE_FORMAT(cp.date_of_filing, '%Y-%m') as month, COUNT(DISTINCT cp.access_id) as count 
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE YEAR(cp.date_of_filing) = YEAR(CURDATE())
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2 
                    WHERE cp2.access_id = cp.access_id 
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY DATE_FORMAT(cp.date_of_filing, '%Y-%m') ORDER BY DATE_FORMAT(cp.date_of_filing, '%Y-%m')";

        // Generate labels for months Jan to current month
        $currentYear = date('Y');
        $currentMonth = (int)date('n');
        for ($m = 1; $m <= $currentMonth; $m++) {
            $monthStr = DateTime::createFromFormat('Y-n-j', $currentYear . '-' . $m . '-01')->format('M Y');
            $registrationData['labels'][] = $monthStr;
            $registrationData['values'][] = 0;
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare this_year query: " . $conn->error);
        } else {
            if ($barangay !== 'all') {
                $stmt->bind_param("s", $barangay);
            }
            if (!$stmt->execute()) {
                error_log("Failed to execute this_year query: " . $stmt->error);
            } else {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $month = date('M Y', strtotime($row['month'] . '-01'));
                    $index = array_search($month, $registrationData['labels']);
                    if ($index !== false) {
                        $registrationData['values'][$index] = (int)$row['count'];
                    }
                }
            }
        }
    } elseif ($range === 'this_month') {
        // From first day of current month to today
        $startDateWithTime = date('Y-m-01') . ' 00:00:00';
        $endDateWithTime = date('Y-m-d') . ' 23:59:59';

        $sql = "SELECT DATE(cp.date_of_filing) as date, COUNT(DISTINCT cp.access_id) as count 
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE cp.date_of_filing BETWEEN ? AND ?
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2 
                    WHERE cp2.access_id = cp.access_id 
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY DATE(cp.date_of_filing) ORDER BY DATE(cp.date_of_filing)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare this_month query: " . $conn->error);
        } else {
            if ($barangay !== 'all') {
                $stmt->bind_param("sss", $startDateWithTime, $endDateWithTime, $barangay);
            } else {
                $stmt->bind_param("ss", $startDateWithTime, $endDateWithTime);
            }
            if (!$stmt->execute()) {
                error_log("Failed to execute this_month query: " . $stmt->error);
            } else {
                $result = $stmt->get_result();

                // Generate labels from day 1 to today
                $start = new DateTime(date('Y-m-01'));
                $end = new DateTime(date('Y-m-d'));
                $interval = new DateInterval('P1D');
                $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));

                foreach ($dateRange as $date) {
                    $registrationData['labels'][] = $date->format('M j');
                    $registrationData['values'][] = 0;
                }

                while ($row = $result->fetch_assoc()) {
                    $date = date('M j', strtotime($row['date']));
                    $index = array_search($date, $registrationData['labels']);
                    if ($index !== false) {
                        $registrationData['values'][$index] = (int)$row['count'];
                    }
                }
            }
        }
    } elseif ($range === 'past_30_days') {
        // Rolling past 30 days including today
        $startDateWithTime = date('Y-m-d', strtotime('-29 days')) . ' 00:00:00';
        $endDateWithTime = date('Y-m-d') . ' 23:59:59';

        $sql = "SELECT DATE(cp.date_of_filing) as date, COUNT(DISTINCT cp.access_id) as count 
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE cp.date_of_filing BETWEEN ? AND ?
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2 
                    WHERE cp2.access_id = cp.access_id 
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY DATE(cp.date_of_filing) ORDER BY DATE(cp.date_of_filing)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare past_30_days query: " . $conn->error);
        } else {
            if ($barangay !== 'all') {
                $stmt->bind_param("sss", $startDateWithTime, $endDateWithTime, $barangay);
            } else {
                $stmt->bind_param("ss", $startDateWithTime, $endDateWithTime);
            }
            if (!$stmt->execute()) {
                error_log("Failed to execute past_30_days query: " . $stmt->error);
            } else {
                $result = $stmt->get_result();

                // Generate labels for the past 30 days
                $start = new DateTime(date('Y-m-d', strtotime('-29 days')));
                $end = new DateTime(date('Y-m-d'));
                $interval = new DateInterval('P1D');
                $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));

                foreach ($dateRange as $date) {
                    $registrationData['labels'][] = $date->format('M j');
                    $registrationData['values'][] = 0;
                }

                while ($row = $result->fetch_assoc()) {
                    $date = date('M j', strtotime($row['date']));
                    $index = array_search($date, $registrationData['labels']);
                    if ($index !== false) {
                        $registrationData['values'][$index] = (int)$row['count'];
                    }
                }
            }
        }
    } elseif ($range === 'weekly_view') {
        $sql = "SELECT YEARWEEK(cp.date_of_filing) as yearweek, COUNT(DISTINCT cp.access_id) as count 
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE cp.date_of_filing >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2 
                    WHERE cp2.access_id = cp.access_id 
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY YEARWEEK(cp.date_of_filing) ORDER BY YEARWEEK(cp.date_of_filing)";

        $stmt = $conn->prepare($sql);
        if ($barangay !== 'all') {
            $stmt->bind_param("s", $barangay);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $year = substr($row['yearweek'], 0, 4);
            $week = substr($row['yearweek'], 4);
            $registrationData['labels'][] = "Week $week, $year";
            $registrationData['values'][] = (int)$row['count'];
        }
    } elseif ($range === 'present_week' || $range === 'this_week') {
        // Current week: Monday to Sunday
        // Determine Monday of current week (WEEKDAY: 0=Mon..6=Sun)
        $sql = "SELECT DAYOFWEEK(cp.date_of_filing) as dow, COUNT(DISTINCT cp.access_id) as count
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE DATE(cp.date_of_filing) BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                                              AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2
                    WHERE cp2.access_id = cp.access_id
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY DAYOFWEEK(cp.date_of_filing) ORDER BY DAYOFWEEK(cp.date_of_filing)";

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        // Labels Monday (2) to Sunday (1 at end)
        $ordered = [2,3,4,5,6,7,1];
        foreach ($ordered as $d) {
            $registrationData['labels'][] = $days[$d-1];
            $registrationData['values'][] = 0;
        }

        $stmt = $conn->prepare($sql);
        if ($barangay !== 'all') {
            $stmt->bind_param("s", $barangay);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $dow = (int)$row['dow'];
            // Map to our ordered index
            $map = [2=>0,3=>1,4=>2,5=>3,6=>4,7=>5,1=>6];
            if (isset($map[$dow])) {
                $registrationData['values'][$map[$dow]] = (int)$row['count'];
            }
        }
    } elseif ($range === 'monthly_view') {
        $sql = "SELECT DATE_FORMAT(cp.date_of_filing, '%Y-%m') as month, COUNT(DISTINCT cp.access_id) as count 
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE cp.date_of_filing >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2 
                    WHERE cp2.access_id = cp.access_id 
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY DATE_FORMAT(cp.date_of_filing, '%Y-%m') ORDER BY DATE_FORMAT(cp.date_of_filing, '%Y-%m')";

        $stmt = $conn->prepare($sql);
        if ($barangay !== 'all') {
            $stmt->bind_param("s", $barangay);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $date = new DateTime($row['month'] . '-01');
            $registrationData['labels'][] = $date->format('M Y');
            $registrationData['values'][] = (int)$row['count'];
        }
    } elseif ($range === 'custom' && $startDate && $endDate) {
        // Add time to the dates to cover the full day
        $startDateWithTime = $startDate . ' 00:00:00';
        $endDateWithTime = $endDate . ' 23:59:59';

        $sql = "SELECT DATE(cp.date_of_filing) as date, COUNT(DISTINCT cp.access_id) as count 
                FROM couple_profile cp
                JOIN address a ON cp.address_id = a.address_id
                WHERE cp.date_of_filing BETWEEN ? AND ?
                AND EXISTS (
                    SELECT 1 FROM couple_profile cp2 
                    WHERE cp2.access_id = cp.access_id 
                    AND cp2.sex != cp.sex
                )";

        if ($barangay !== 'all') {
            $sql .= " AND a.barangay = ?";
        }

        $sql .= " GROUP BY DATE(cp.date_of_filing) ORDER BY DATE(cp.date_of_filing)";

        $stmt = $conn->prepare($sql);
        if ($barangay !== 'all') {
            $stmt->bind_param("sss", $startDateWithTime, $endDateWithTime, $barangay);
        } else {
            $stmt->bind_param("ss", $startDateWithTime, $endDateWithTime);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Generate all dates in the range
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));

        foreach ($dateRange as $date) {
            $registrationData['labels'][] = $date->format('M j, Y');
            $registrationData['values'][] = 0;
        }

        while ($row = $result->fetch_assoc()) {
            $date = date('M j, Y', strtotime($row['date']));
            $index = array_search($date, $registrationData['labels']);
            if ($index !== false) {
                $registrationData['values'][$index] = (int)$row['count'];
            }
        }
    }

    // Age Population Pyramid Data
    $ageGroups = ['18-25', '26-30', '31-35', '36-40', '41-45', '46-50', '51+'];
    $populationData = ['labels' => $ageGroups, 'male' => [], 'female' => []];

    foreach ($ageGroups as $group) {
        if ($group === '51+') {
            $sql = "SELECT 
                        COUNT(CASE WHEN cp.sex = 'Male' THEN 1 END) as male_count,
                        COUNT(CASE WHEN cp.sex = 'Female' THEN 1 END) as female_count
                    FROM couple_profile cp
                    JOIN address a ON cp.address_id = a.address_id
                    WHERE FLOOR(DATEDIFF(CURRENT_DATE, STR_TO_DATE(cp.date_of_birth, '%Y-%m-%d'))/365) >= 51";
            
            // Add date filter
            $sql .= getDateFilterSQL($range, $startDate, $endDate);

            if ($barangay !== 'all') {
                $sql .= " AND a.barangay = ?";
            }
        } else {
            list($min, $max) = explode('-', $group);
            $sql = "SELECT 
                        COUNT(CASE WHEN cp.sex = 'Male' THEN 1 END) as male_count,
                        COUNT(CASE WHEN cp.sex = 'Female' THEN 1 END) as female_count
                    FROM couple_profile cp
                    JOIN address a ON cp.address_id = a.address_id
                    WHERE FLOOR(DATEDIFF(CURRENT_DATE, STR_TO_DATE(cp.date_of_birth, '%Y-%m-%d'))/365) BETWEEN ? AND ?";
            
            // Add date filter
            $sql .= getDateFilterSQL($range, $startDate, $endDate);

            if ($barangay !== 'all') {
                $sql .= " AND a.barangay = ?";
            }
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare population query for group $group: " . $conn->error . " | SQL: " . $sql);
            $populationData['male'][] = 0;
            $populationData['female'][] = 0;
            continue;
        }
        
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        
        if ($group !== '51+') {
            if ($dateParams && $barangay !== 'all') {
                $date1 = $dateParams[1];
                $date2 = $dateParams[2];
                $typeStr = "ii" . $dateParams[0] . "s";
                $stmt->bind_param($typeStr, $min, $max, $date1, $date2, $barangay);
            } elseif ($dateParams) {
                $date1 = $dateParams[1];
                $date2 = $dateParams[2];
                $typeStr = "ii" . $dateParams[0];
                $stmt->bind_param($typeStr, $min, $max, $date1, $date2);
            } elseif ($barangay !== 'all') {
                $stmt->bind_param("iis", $min, $max, $barangay);
            } else {
                $stmt->bind_param("ii", $min, $max);
            }
        } else {
            bindDateParams($stmt, $dateParams, $barangay);
        }

        if (!$stmt->execute()) {
            error_log("Population query error for group $group: " . $stmt->error);
            $populationData['male'][] = 0;
            $populationData['female'][] = 0;
        } else {
            $result = $stmt->get_result()->fetch_assoc();
            $populationData['male'][] = $result['male_count'] ?? 0;
            $populationData['female'][] = $result['female_count'] ?? 0;
        }
    }

    // Civil Status Data
    $civilStatusColors = [
        'Single' => 'rgba(54, 162, 235, 0.7)',
        'Living In' => 'rgba(255, 159, 64, 0.7)',
        'Widowed' => 'rgba(255, 206, 86, 0.7)',
        'Separated' => 'rgba(153, 102, 255, 0.7)',
        'Divorced' => 'rgba(75, 192, 192, 0.7)'
    ];

    $civilData = ['labels' => [], 'values' => [], 'colors' => []];
    // Count individuals (not couples) for the civil status chart
    $sql = "SELECT cp.civil_status, COUNT(*) as count 
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE cp.civil_status IS NOT NULL";
    
    // Add date filter
    $sql .= getDateFilterSQL($range, $startDate, $endDate);

    if ($barangay !== 'all') {
        $sql .= " AND a.barangay = ?";
    }

    $sql .= " GROUP BY cp.civil_status";
    
    // Also get total couples count for the analysis display
    $totalCouplesSql = "SELECT COUNT(DISTINCT cp.access_id) as total_couples
                        FROM couple_profile cp
                        JOIN address a ON cp.address_id = a.address_id
                        WHERE EXISTS (
                            SELECT 1 FROM couple_profile cp2 
                            WHERE cp2.access_id = cp.access_id 
                            AND cp2.sex != cp.sex
                        )";
    $totalCouplesSql .= getDateFilterSQL($range, $startDate, $endDate);
    if ($barangay !== 'all') {
        $totalCouplesSql .= " AND a.barangay = ?";
    }

    $stmt = $conn->prepare($sql);
    $totalCouples = 0;
    if (!$stmt) {
        error_log("Failed to prepare civil status query: " . $conn->error . " | SQL: " . $sql);
    } else {
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        if ($dateParams && $barangay !== 'all') {
            $date1 = $dateParams[1];
            $date2 = $dateParams[2];
            $stmt->bind_param($dateParams[0] . "s", $date1, $date2, $barangay);
        } elseif ($dateParams) {
            $date1 = $dateParams[1];
            $date2 = $dateParams[2];
            $stmt->bind_param($dateParams[0], $date1, $date2);
        } elseif ($barangay !== 'all') {
            $stmt->bind_param("s", $barangay);
        }
        
        if (!$stmt->execute()) {
            error_log("Civil status query error: " . $stmt->error . " | SQL: " . $sql);
        } else {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $status = $row['civil_status'];
                $civilData['labels'][] = $status;
                $civilData['values'][] = (int)$row['count'];
                $civilData['colors'][] = $civilStatusColors[$status] ?? 'rgba(201, 203, 207, 0.7)';
            }
        }
    }
    
    // Get total couples count
    $totalCouplesStmt = $conn->prepare($totalCouplesSql);
    if ($totalCouplesStmt) {
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        if ($dateParams && $barangay !== 'all') {
            $date1 = $dateParams[1];
            $date2 = $dateParams[2];
            $totalCouplesStmt->bind_param($dateParams[0] . "s", $date1, $date2, $barangay);
        } elseif ($dateParams) {
            $date1 = $dateParams[1];
            $date2 = $dateParams[2];
            $totalCouplesStmt->bind_param($dateParams[0], $date1, $date2);
        } elseif ($barangay !== 'all') {
            $totalCouplesStmt->bind_param("s", $barangay);
        }
        
        if ($totalCouplesStmt->execute()) {
            $totalCouplesResult = $totalCouplesStmt->get_result();
            if ($totalCouplesRow = $totalCouplesResult->fetch_assoc()) {
                $totalCouples = (int)$totalCouplesRow['total_couples'];
            }
        }
        $totalCouplesStmt->close();
    }
    
    // Add total couples to the response
    $civilData['total_couples'] = $totalCouples;

    // Religion Data
    $religionColors = [
        'Aglipay' => 'rgba(230, 25, 75, 0.8)',
        'Bible Baptist Church' => 'rgba(60, 180, 75, 0.8)',
        'Church of Christ' => 'rgba(255, 225, 25, 0.8)',
        'Jehova\'s Witness' => 'rgba(0, 130, 200, 0.8)',
        'Iglesia ni Cristo' => 'rgba(145, 30, 180, 0.8)',
        'Islam' => 'rgba(245, 130, 48, 0.8)',
        'Roman Catholic' => 'rgba(128, 128, 128, 0.8)',
        'Seventh Day Adventist' => 'rgba(240, 50, 230, 0.8)',
        'Iglesia Filipina Independente' => 'rgba(210, 245, 60, 0.8)',
        'United Church of Christ in the PH' => 'rgba(70, 240, 240, 0.8)',
        'None' => 'rgba(250, 190, 190, 0.8)',
        'Other' => 'rgba(255, 215, 180, 0.8)'
    ];

    $religionData = ['labels' => [], 'values' => [], 'colors' => []];
    $sql = "SELECT cp.religion, COUNT(*) as count 
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE cp.religion IS NOT NULL";
    
    // Add date filter
    $sql .= getDateFilterSQL($range, $startDate, $endDate);

    if ($barangay !== 'all') {
        $sql .= " AND a.barangay = ?";
    }

    $sql .= " GROUP BY cp.religion";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare religion query: " . $conn->error);
    } else {
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        bindDateParams($stmt, $dateParams, $barangay);
        
        if (!$stmt->execute()) {
            error_log("Religion query error: " . $stmt->error);
        } else {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $religion = $row['religion'];
                $religionData['labels'][] = $religion;
                $religionData['values'][] = (int)$row['count'];
                $religionData['colors'][] = $religionColors[$religion] ?? 'rgba(201, 203, 207, 0.7)';
            }
        }
    }

    // Wedding Type Data - Count individuals, not couples
    $weddingData = ['labels' => [], 'values' => []];
    $sql = "SELECT cp.wedding_type, COUNT(*) as count 
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE cp.wedding_type IS NOT NULL
            AND EXISTS (
                SELECT 1 FROM couple_profile cp2 
                WHERE cp2.access_id = cp.access_id 
                AND cp2.sex != cp.sex
            )";
    
    // Add date filter
    $sql .= getDateFilterSQL($range, $startDate, $endDate);

    if ($barangay !== 'all') {
        $sql .= " AND a.barangay = ?";
    }

    $sql .= " GROUP BY cp.wedding_type ORDER BY count DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare wedding query: " . $conn->error);
    } else {
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        bindDateParams($stmt, $dateParams, $barangay);
        
        if (!$stmt->execute()) {
            error_log("Wedding query error: " . $stmt->error);
        } else {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $weddingData['labels'][] = $row['wedding_type'];
                $weddingData['values'][] = (int)$row['count'];
            }
        }
    }

    // Pregnancy Status (Female only)
    $pregnancyStatus = ['labels' => ['Pregnant', 'Not Pregnant'], 'values' => [0, 0]];
    $sql = "SELECT 
                SUM(CASE WHEN cp.sex = 'Female' AND cp.currently_pregnant = 'Yes' THEN 1 ELSE 0 END) AS pregnant,
                SUM(CASE WHEN cp.sex = 'Female' AND (cp.currently_pregnant = 'No' OR cp.currently_pregnant IS NULL) THEN 1 ELSE 0 END) AS not_pregnant
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE 1=1";
    
    // Add date filter
    $sql .= getDateFilterSQL($range, $startDate, $endDate);
    
    if ($barangay !== 'all') {
        $sql .= " AND a.barangay = ?";
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare pregnancy query: " . $conn->error);
    } else {
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        bindDateParams($stmt, $dateParams, $barangay);
        
        if (!$stmt->execute()) {
            error_log("Pregnancy query error: " . $stmt->error);
        } else {
            $row = $stmt->get_result()->fetch_assoc();
            $pregnancyStatus['values'][0] = (int)($row['pregnant'] ?? 0);
            $pregnancyStatus['values'][1] = (int)($row['not_pregnant'] ?? 0);
        }
    }



    // Highest Education Attainment
    $educationData = ['labels' => [], 'values' => []];
    $sql = "SELECT cp.education AS label, COUNT(*) AS count
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE cp.education IS NOT NULL";
    
    // Add date filter
    $sql .= getDateFilterSQL($range, $startDate, $endDate);
    
    if ($barangay !== 'all') {
        $sql .= " AND a.barangay = ?";
    }
    $sql .= " GROUP BY cp.education ORDER BY count DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare education query: " . $conn->error);
    } else {
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        bindDateParams($stmt, $dateParams, $barangay);
        
        if (!$stmt->execute()) {
            error_log("Education query error: " . $stmt->error);
        } else {
            $result = $stmt->get_result();
            while ($r = $result->fetch_assoc()) {
                $educationData['labels'][] = $r['label'] ?? 'Unknown';
                $educationData['values'][] = (int)$r['count'];
            }
        }
    }

    // Employment Status
    $employmentData = ['labels' => [], 'values' => []];
    $sql = "SELECT cp.employment_status AS label, COUNT(*) AS count
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE cp.employment_status IS NOT NULL";
    
    // Add date filter
    $sql .= getDateFilterSQL($range, $startDate, $endDate);
    
    if ($barangay !== 'all') {
        $sql .= " AND a.barangay = ?";
    }
    $sql .= " GROUP BY cp.employment_status ORDER BY count DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare employment query: " . $conn->error);
    } else {
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        bindDateParams($stmt, $dateParams, $barangay);
        
        if (!$stmt->execute()) {
            error_log("Employment query error: " . $stmt->error);
        } else {
            $result = $stmt->get_result();
            while ($r = $result->fetch_assoc()) {
                $employmentData['labels'][] = $r['label'] ?? 'Unknown';
                $employmentData['values'][] = (int)$r['count'];
            }
        }
    }

    // Income Bracket Distribution
    $incomeData = ['labels' => [], 'values' => []];
    $sql = "SELECT cp.monthly_income AS label, COUNT(*) AS count
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE cp.monthly_income IS NOT NULL";
    
    // Add date filter
    $sql .= getDateFilterSQL($range, $startDate, $endDate);
    
    if ($barangay !== 'all') {
        $sql .= " AND a.barangay = ?";
    }
    $sql .= " GROUP BY cp.monthly_income ORDER BY FIELD(cp.monthly_income, '5000 below','5999-9999','10000-14999','15000-19999','20000-24999','25000 above'), cp.monthly_income";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare income query: " . $conn->error);
    } else {
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        bindDateParams($stmt, $dateParams, $barangay);
        
        if (!$stmt->execute()) {
            error_log("Income query error: " . $stmt->error);
        } else {
            $result = $stmt->get_result();
            while ($r = $result->fetch_assoc()) {
                $incomeData['labels'][] = $r['label'] ?? 'Unknown';
                $incomeData['values'][] = (int)$r['count'];
            }
        }
    }

    // Attendance Rate (Present vs Absent) - derive from attendance_logs
    // Present: schedules with at least one 'present' record in attendance_logs
    // Absent: schedules with no 'present' but with at least one 'absent' record
    $attendanceData = ['labels' => ['Present', 'Absent'], 'values' => [0, 0]];

    // Build date filter for attendance queries
    $dateFilter = getDateFilterSQL($range, $startDate, $endDate, 's.session_date');
    $dateParams = getDateFilterParams($range, $startDate, $endDate);
    
    $baseFilter = $dateFilter;
    if ($barangay !== 'all') {
        $baseFilter .= " AND EXISTS (
            SELECT 1 FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE cp.access_id = s.access_id AND a.barangay = ?
        )";
    }

    // Count present schedules
    $presentCnt = 0;
    $sqlPresent = "SELECT COUNT(*) AS cnt
                    FROM scheduling s
                    WHERE EXISTS (
                        SELECT 1 FROM attendance_logs al
                        WHERE al.schedule_id = s.schedule_id AND al.status = 'present'
                    )" . $baseFilter;
    $stmt = $conn->prepare($sqlPresent);
    if (!$stmt) {
        error_log("Failed to prepare present attendance query: " . $conn->error);
    } else {
        if ($dateParams && $barangay !== 'all') {
            $date1 = $dateParams[1];
            $date2 = $dateParams[2];
            $stmt->bind_param('sss', $date1, $date2, $barangay);
        } elseif ($dateParams) {
            $date1 = $dateParams[1];
            $date2 = $dateParams[2];
            $stmt->bind_param('ss', $date1, $date2);
        } elseif ($barangay !== 'all') {
            $stmt->bind_param('s', $barangay);
        }
        if ($stmt->execute()) {
            $presentCnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        } else {
            error_log("Failed to execute present attendance query: " . $stmt->error);
        }
    }

    // Count absent schedules
    $absentCnt = 0;
    $sqlAbsent = "SELECT COUNT(*) AS cnt
                  FROM scheduling s
                  WHERE NOT EXISTS (
                            SELECT 1 FROM attendance_logs al
                            WHERE al.schedule_id = s.schedule_id AND al.status = 'present'
                        )
                    AND EXISTS (
                            SELECT 1 FROM attendance_logs al2
                            WHERE al2.schedule_id = s.schedule_id AND al2.status = 'absent'
                        )" . $baseFilter;
    $stmt = $conn->prepare($sqlAbsent);
    if (!$stmt) {
        error_log("Failed to prepare absent attendance query: " . $conn->error);
    } else {
        if ($dateParams && $barangay !== 'all') {
            $date1 = $dateParams[1];
            $date2 = $dateParams[2];
            $stmt->bind_param('sss', $date1, $date2, $barangay);
        } elseif ($dateParams) {
            $date1 = $dateParams[1];
            $date2 = $dateParams[2];
            $stmt->bind_param('ss', $date1, $date2);
        } elseif ($barangay !== 'all') {
            $stmt->bind_param('s', $barangay);
        }
        if ($stmt->execute()) {
            $absentCnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        } else {
            error_log("Failed to execute absent attendance query: " . $stmt->error);
        }
    }

    $attendanceData['values'][0] = $presentCnt;
    $attendanceData['values'][1] = $absentCnt;

    // Top 5 Barangays with Most Registrations (distinct couples)
    $topBarangays = ['labels' => [], 'values' => []];
    $sql = "SELECT a.barangay, COUNT(DISTINCT cp.access_id) AS cnt
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE EXISTS (
                SELECT 1 FROM couple_profile cp2
                WHERE cp2.access_id = cp.access_id AND cp2.sex <> cp.sex
            )";
    if ($barangay !== 'all') {
        $sql .= " AND a.barangay = ?";
    }
    $sql .= " GROUP BY a.barangay ORDER BY cnt DESC LIMIT 5";
    $stmt = $conn->prepare($sql);
    if ($barangay !== 'all') { $stmt->bind_param("s", $barangay); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $topBarangays['labels'][] = $r['barangay'] ?? 'Unknown';
        $topBarangays['values'][] = (int)$r['cnt'];
    }

    // Seasonal/Monthly Marriage Patterns (month-of-year over all years)
    $marriageSeasonality = ['labels' => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], 'values' => array_fill(0,12,0)];
    $sql = "SELECT MONTH(cp.date_of_filing) AS m, COUNT(DISTINCT cp.access_id) AS cnt
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE cp.date_of_filing IS NOT NULL
            AND EXISTS (
                SELECT 1 FROM couple_profile cp2
                WHERE cp2.access_id = cp.access_id AND cp2.sex <> cp.sex
            )";
    if ($barangay !== 'all') { $sql .= " AND a.barangay = ?"; }
    $sql .= " GROUP BY MONTH(cp.date_of_filing)";
    $stmt = $conn->prepare($sql);
    if ($barangay !== 'all') { $stmt->bind_param("s", $barangay); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $m = (int)$r['m']; if ($m>=1 && $m<=12) { $marriageSeasonality['values'][$m-1] = (int)$r['cnt']; }
    }

    // Session Trends per Month (last 12 months)
    $sessionsMonthly = ['labels' => [], 'values' => []];
    $sql = "SELECT DATE_FORMAT(s.session_date,'%Y-%m') AS ym, COUNT(*) AS cnt
            FROM scheduling s
            WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(s.session_date,'%Y-%m')
            ORDER BY ym";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare sessions monthly query: " . $conn->error . " | SQL: " . $sql);
    } else {
        $stmt->execute();
        $res = $stmt->get_result();
    }
    if (!$res) {
        error_log("Failed to execute sessions monthly query: " . $conn->error . " | SQL: " . $sql);
        // Initialize with empty data
        $start = new DateTime(date('Y-m-01', strtotime('-11 months')));
        for ($i=0; $i<12; $i++) {
            $label = $start->format('M Y');
            $sessionsMonthly['labels'][] = $label;
            $sessionsMonthly['values'][] = 0;
            $start->modify('+1 month');
        }
    } else {
        // Build last 12 months timeline
    $start = new DateTime(date('Y-m-01', strtotime('-11 months')));
    for ($i=0; $i<12; $i++) {
        $label = $start->format('M Y');
        $sessionsMonthly['labels'][] = $label;
        $sessionsMonthly['values'][] = 0;
        $start->modify('+1 month');
    }
        $resArr = [];
        while ($row = $res->fetch_assoc()) { $resArr[$row['ym']] = (int)$row['cnt']; }
        // Fill values
        $start = new DateTime(date('Y-m-01', strtotime('-11 months')));
        for ($i=0; $i<12; $i++) {
            $ym = $start->format('Y-m');
            $sessionsMonthly['values'][$i] = $resArr[$ym] ?? 0;
            $start->modify('+1 month');
        }
        if (isset($stmt)) $stmt->close();
    }

    // Peak Session Days (weekday)
    $sessionsWeekday = ['labels' => ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'], 'values' => array_fill(0,7,0)];
    $sql = "SELECT DAYOFWEEK(s.session_date) AS dow, COUNT(*) AS cnt
            FROM scheduling s";
    if ($barangay !== 'all') {
        $sql .= " WHERE EXISTS (
                    SELECT 1 FROM couple_profile cp
                    JOIN address a ON cp.address_id = a.address_id
                    WHERE cp.access_id = s.access_id AND a.barangay = ?
                 )";
    }
    $sql .= " GROUP BY DAYOFWEEK(s.session_date)";
    $stmt = $conn->prepare($sql);
    if ($barangay !== 'all') { $stmt->bind_param("s", $barangay); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $dow = (int)$r['dow']; if ($dow>=1 && $dow<=7) { $sessionsWeekday['values'][$dow-1] = (int)$r['cnt']; }
    }

    // Preferred Family Planning Methods (Male vs Female)
    // Aligned with form options: Female (IUD, Implant, Pills, DMPA/Injectables, BTL, Natural, Other)
    // Male (Condom, Vasectomy, Natural, Other)
    $fpMethods = ['labels' => [], 'male' => [], 'female' => []];
    
    // Define all possible methods from the form
    $femaleMethods = ['IUD', 'Implant', 'Pills', 'DMPA/Injectables', 'BTL', 'Natural', 'Other'];
    $maleMethods = ['Condom', 'Vasectomy', 'Natural', 'Other'];
    
    // Initialize counts for all methods
    $maleCounts = array_fill_keys($maleMethods, 0);
    $femaleCounts = array_fill_keys($femaleMethods, 0);
    
    $sql = "SELECT 
                CASE 
                    WHEN cp.sex = 'Male' THEN cp.fp_male_method
                    ELSE cp.fp_female_method
                END AS method,
                cp.sex,
                COUNT(*) AS cnt
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE (
                    (cp.sex='Male' AND TRIM(COALESCE(cp.fp_male_method, '')) <> '')
                 OR (cp.sex='Female' AND TRIM(COALESCE(cp.fp_female_method, '')) <> '')
                  )";
    
    // Add date filter
    $sql .= getDateFilterSQL($range, $startDate, $endDate);
    
    if ($barangay !== 'all') { $sql .= " AND a.barangay = ?"; }
    $sql .= " GROUP BY method, cp.sex";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare FP methods query: " . $conn->error);
        $res = null;
    } else {
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        bindDateParams($stmt, $dateParams, $barangay);
        
        if (!$stmt->execute()) {
            error_log("FP methods query error: " . $stmt->error);
            $res = null;
        } else {
            $res = $stmt->get_result();
        }
    }

    // Aggregate by method (using base method name, not custom text)
    if ($res) {
        while ($row = $res->fetch_assoc()) {
        $method = trim($row['method'] ?? '');
        if ($method === '') continue;
        
        $sex = $row['sex'] === 'Male' ? 'Male' : 'Female';
        $cnt = (int)$row['cnt'];
        
        // Normalize method names to match form options
        if ($sex === 'Male') {
            if (isset($maleCounts[$method])) {
                $maleCounts[$method] += $cnt;
            } else {
                // If method not in predefined list, count as "Other"
                $maleCounts['Other'] += $cnt;
            }
        } else {
            if (isset($femaleCounts[$method])) {
                $femaleCounts[$method] += $cnt;
            } else {
                // If method not in predefined list, count as "Other"
                $femaleCounts['Other'] += $cnt;
            }
        }
        }
    }

    // Sort male methods by count descending
    $maleSorted = $maleCounts;
    arsort($maleSorted);
    
    // Sort female methods by count descending
    $femaleSorted = $femaleCounts;
    arsort($femaleSorted);
    
    // Build separate arrays for male and female methods
    $fpMethods = [
        'male_labels' => array_keys($maleSorted),
        'male' => array_values($maleSorted),
        'female_labels' => array_keys($femaleSorted),
        'female' => array_values($femaleSorted)
    ];

        // PhilHealth Member Statistics
    $philhealthData = [
        'labels' => ['Yes', 'No'],
        'values' => [0, 0],
        'colors' => ['rgba(40,167,69,0.8)', 'rgba(220,53,69,0.8)']
    ];
    
    $sql = "SELECT 
                philhealth_member,
                COUNT(*) AS cnt
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE cp.philhealth_member IS NOT NULL AND cp.philhealth_member != ''";
    
    // Add date filter
    $sql .= getDateFilterSQL($range, $startDate, $endDate);
    
    if ($barangay !== 'all') { $sql .= " AND a.barangay = ?"; }
    $sql .= " GROUP BY philhealth_member";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare philhealth query: " . $conn->error);
    } else {
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        bindDateParams($stmt, $dateParams, $barangay);
        
        if (!$stmt->execute()) {
            error_log("Philhealth query error: " . $stmt->error);
        } else {
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $member = $r['philhealth_member'];
                $cnt = (int)($r['cnt'] ?? 0);
                
                if ($member === 'Yes') {
                    $philhealthData['values'][0] = $cnt;
                } else {
                    $philhealthData['values'][1] = $cnt;
                }
            }
        }
    }

        // FP Intention (Yes/No) by Gender
    $fpIntent = [
        'labels' => ['Yes', 'No'],
        'male' => [0, 0],
        'female' => [0, 0],
        'total' => [0, 0]
    ];
    
    $sql = "SELECT 
                cp.sex,
                SUM(CASE WHEN LOWER(TRIM(cp.intend_fp)) = 'yes' THEN 1 ELSE 0 END) AS yes_cnt,
                SUM(CASE WHEN LOWER(TRIM(cp.intend_fp)) = 'no' OR cp.intend_fp IS NULL OR cp.intend_fp = '' THEN 1 ELSE 0 END) AS no_cnt
            FROM couple_profile cp
            JOIN address a ON cp.address_id = a.address_id
            WHERE 1=1";
    
    // Add date filter
    $sql .= getDateFilterSQL($range, $startDate, $endDate);
    
    if ($barangay !== 'all') { $sql .= " AND a.barangay = ?"; }
    $sql .= " GROUP BY cp.sex";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare FP intent query: " . $conn->error);
    } else {
        $dateParams = getDateFilterParams($range, $startDate, $endDate);
        bindDateParams($stmt, $dateParams, $barangay);
        
        if (!$stmt->execute()) {
            error_log("FP intent query error: " . $stmt->error);
        } else {
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $sex = $r['sex'];
                $yesCnt = (int)($r['yes_cnt'] ?? 0);
                $noCnt = (int)($r['no_cnt'] ?? 0);
                
                if ($sex === 'Male') {
                    $fpIntent['male'][0] = $yesCnt;
                    $fpIntent['male'][1] = $noCnt;
                } else {
                    $fpIntent['female'][0] = $yesCnt;
                    $fpIntent['female'][1] = $noCnt;
                }
                
                // Add to totals
                $fpIntent['total'][0] += $yesCnt;
                $fpIntent['total'][1] += $noCnt;
            }
        }
    }

    // Ensure no output before JSON
    ob_clean();
    
    echo json_encode([
        'registration' => $registrationData,
        'population' => $populationData,
        'civil' => $civilData,
        'religion' => $religionData,
        'wedding' => $weddingData,
        'pregnancy' => [
            'status' => $pregnancyStatus
        ],
        'education' => $educationData,
        'employment' => $employmentData,
        'income' => $incomeData,
        'attendance' => $attendanceData,
        'top_barangays' => $topBarangays,
        'marriage_seasonality' => $marriageSeasonality,
        'sessions_monthly' => $sessionsMonthly,
        'sessions_weekday' => $sessionsWeekday,
        'fp_methods' => $fpMethods,
        'fp_intent' => $fpIntent,
        'philhealth' => $philhealthData
    ]);
    
    // End output buffering and send
    ob_end_flush();
    exit();
} catch (Exception $e) {
    // Clear any output before sending error
    ob_clean();
    error_log("Error in fetch_statistics.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch statistics data',
        'message' => $e->getMessage(),
        'range' => $range ?? 'unknown'
    ]);
    exit();
} catch (Error $e) {
    // Catch fatal errors (PHP 7+)
    ob_clean();
    error_log("Fatal error in fetch_statistics.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch statistics data',
        'message' => $e->getMessage()
    ]);
    exit();
}