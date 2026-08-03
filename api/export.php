<?php
// api/export.php
// streams all reservation records as a downloadable CSV.
// Usage: GET /api/export.php  (while logged in)

require_once 'db.php';

// --- Authorization: administrators only -----------------------------------
// Note: db.php calls session_start(), so $_SESSION is available here.
if (!isset($_SESSION['user_id'])) {
    // Respond as JSON so the failure is readable if hit via fetch/XHR.
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to export your reservation history.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
// --- Query reservation data -----------------------------------------------
// No user input is interpolated into this query, but we use a prepared
// statement per the security requirement. The JOINs mirror the existing
// admin "reservations" endpoint so column semantics stay consistent.
try {
    $rows = get_reservation_history($pdo, $user_id);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Export query failed: ' . $e->getMessage()]);
    exit;
}

// --- Send CSV download headers --------------------------------------------
// A timestamped filename keeps repeated exports from overwriting each other.
$filename = 'reservations_export_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// --- Write CSV to the output stream ---------------------------------------
$output = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens accented characters (e.g. café) correctly.
fprintf($output, "\xEF\xBB\xBF");

// Human-readable column headers.
fputcsv($output, [
    'Reservation ID',
    'User Name',
    'Restaurant',
    'Date',
    'Time',
    'Guests',
    'Status',
]);

// Data rows.
foreach ($rows as $row) {
    fputcsv($output, [
        $row['reservation_id'],
        $row['user_name'],
        $row['restaurant_name'],
        $row['reservation_date'],
        $row['reservation_time'],
        $row['guests'],
        $row['status'],
    ]);
}

fclose($output);
exit;