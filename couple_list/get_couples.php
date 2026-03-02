<?php
/**
 * Get couples list for MEAI analysis
 * Supports pagination via page and per_page parameters
 */

header('Content-Type: application/json');
require_once '../includes/conn.php';

try {
    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 50; // Max 100 per page
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countQuery = "
        SELECT COUNT(DISTINCT ca.access_id) as total
        FROM couple_access ca
        LEFT JOIN couple_profile cp1 ON ca.access_id = cp1.access_id AND UPPER(cp1.sex) = 'MALE'
        LEFT JOIN couple_profile cp2 ON ca.access_id = cp2.access_id AND UPPER(cp2.sex) = 'FEMALE'
        WHERE cp1.first_name IS NOT NULL AND cp2.first_name IS NOT NULL
    ";
    $countResult = $conn->query($countQuery);
    $totalRecords = $countResult ? $countResult->fetch_assoc()['total'] : 0;
    $totalPages = ceil($totalRecords / $perPage);
    
    // Get paginated couples
    $query = "
        SELECT DISTINCT 
            ca.access_id,
            ca.access_code,
            CONCAT(cp1.first_name, ' ', cp1.last_name, ' & ', cp2.first_name, ' ', cp2.last_name) as couple_names
        FROM couple_access ca
        LEFT JOIN couple_profile cp1 ON ca.access_id = cp1.access_id AND UPPER(cp1.sex) = 'MALE'
        LEFT JOIN couple_profile cp2 ON ca.access_id = cp2.access_id AND UPPER(cp2.sex) = 'FEMALE'
        WHERE cp1.first_name IS NOT NULL AND cp2.first_name IS NOT NULL
        ORDER BY ca.access_id
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $couples = [];
    while ($row = $result->fetch_assoc()) {
        $couples[] = [
            'access_id' => intval($row['access_id']),
            'access_code' => $row['access_code'],
            'couple_names' => $row['couple_names']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $couples,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total_records' => (int)$totalRecords,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ]);
    
} catch (Exception $e) {
    // Log the actual error for debugging
    error_log("Error in get_couples.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to fetch couples list. Please try again.'
    ]);
}
?>
