<?php
// api/export.php
// streams all reservation records as a downloadable CSV.
// Usage: GET /api/export.php  (while logged in)
require_once __DIR__ . '/../src/Helpers/ExportHelpers.php';
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

// Admin will get everyone's data
// User will only get their own data
$user_id = (int) $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] ?? '') === 'admin';

// --- Query reservation data -----------------------------------------------
// No user input is interpolated into this query, but we use a prepared
// statement per the security requirement. The JOINs mirror the existing
// admin "reservations" endpoint so column semantics stay consistent.

// Once everything is implemented, remove this toggle
$USE_SAMPLE_DATA = true;
try {
    if ($USE_SAMPLE_DATA) {
        $rows = [
            [
                'reservation_id'   => 1001,
                'user_name'        => 'ME',
                'restaurant_name'  => 'FOOD',
                'reservation_date' => '2026-01-01',
                'reservation_time' => '19:30',
                'guests'           => 4,
                'status'           => 'Confirmed',
            ],
            [
                'reservation_id'   => 1002,
                'user_name'        => 'LEBRON JAMES',
                'restaurant_name'  => 'LEBRON JAMES RESTAURANT',
                'reservation_date' => '2026-08-12',
                'reservation_time' => '18:00',
                'guests'           => 2,
                'status'           => 'Cancelled',
            ],
        ];
    }elseif ($is_admin){
        $stmt = $pdo->prepare("
            SELECT
                res.booking_id       AS reservation_id,
                u.name               AS user_name,
                r.name               AS restaurant_name,
                res.date             AS reservation_date,
                res.reservation_time AS reservation_time,
                res.guest_count      AS guests,
                res.status           AS status
            FROM reservations res
            JOIN users u        ON res.customer_id   = u.user_id
            JOIN restaurants r  ON res.restaurant_id = r.restaurant_id
            ORDER BY res.date DESC, res.reservation_time DESC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }else{
        $stmt = $pdo->prepare("
            SELECT
                res.booking_id       AS reservation_id,
                u.name               AS user_name,
                r.name               AS restaurant_name,
                res.date             AS reservation_date,
                res.reservation_time AS reservation_time,
                res.guest_count      AS guests,
                res.status           AS status
            FROM reservations res
            JOIN users u        ON res.customer_id   = u.user_id
            JOIN restaurants r  ON res.restaurant_id = r.restaurant_id
            WHERE res.customer_id = :user_id
            ORDER BY res.date DESC, res.reservation_time DESC
        ");
        $stmt->execute(['user_id' => $user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Export query failed: ' . $e->getMessage()]);
    exit;
}

// --- Send CSV download headers --------------------------------------------
// A timestamped filename keeps repeated exports from overwriting each other.
$filename = generateExportFilename(time());

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
    fputcsv($output, mapReservationRowToCsvRow($row));
}

fclose($output);
exit;
