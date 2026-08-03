<?php
session_start();

$loadEnvPath = __DIR__ . '/load_env.php';
if (file_exists($loadEnvPath)) {
    require_once $loadEnvPath;
}

$isLocal = in_array(
    $_SERVER['SERVER_NAME'] ?? '',
    ['localhost', '127.0.0.1', '::1', ''],
    true
) || ($_SERVER['SERVER_ADDR'] ?? '') === '127.0.0.1'
  || ($_SERVER['HTTP_HOST'] ?? '') === 'localhost';

$host = $_ENV['DB_HOST'] ?? null;
$db   = $_ENV['DB_NAME'] ?? null;
$user = $_ENV['DB_USER'] ?? null;
$pass = $_ENV['DB_PASS'] ?? null;

if (!$host || !$db || !$user) {
    if ($isLocal) {
        $host = 'localhost';
        $db   = 'reserve-hub';
        $user = 'root';
        $pass = '';
    } else {
        http_response_code(500);
        die(json_encode([
            'success' => false,
            'message' => 'Database environment variables are not configured.'
        ]));
    }
}

$charset = 'utf8mb4';
$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed.'
    ]));
}

function validateHistoryDate(?string $date, string $fieldName): ?string
{
    if ($date === null || $date === '') {
        return null;
    }

    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    $errors = DateTime::getLastErrors();

    if (
        $parsed === false
        || ($errors !== false
            && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $parsed->format('Y-m-d') !== $date
    ) {
        throw new InvalidArgumentException(
            "{$fieldName} must use the YYYY-MM-DD format."
        );
    }

    return $date;
}

function getActiveReservationHistory(
    PDO $pdo,
    int $userId,
    ?string $startDate = null,
    ?string $endDate = null
): array {
    $sql = "
        SELECT
            r.booking_id AS reservation_id,
            r.customer_id,
            r.restaurant_id,
            r.table_id,
            rest.name AS restaurant_name,
            t.table_number,
            r.date AS reservation_date,
            r.reservation_time,
            r.guest_count,
            r.special_requests,
            r.status,
            r.created_at AS reservation_created_at,
            NULL AS archived_at,
            'active' AS record_source
        FROM reservations r
        LEFT JOIN restaurants rest
            ON rest.id = r.restaurant_id
        LEFT JOIN tables t
            ON t.table_id = r.table_id
        WHERE r.customer_id = ?
    ";

    $params = [$userId];

    if ($startDate !== null) {
        $sql .= " AND r.date >= ?";
        $params[] = $startDate;
    }

    if ($endDate !== null) {
        $sql .= " AND r.date <= ?";
        $params[] = $endDate;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function getArchivedReservationHistory(
    PDO $pdo,
    int $userId,
    ?string $startDate = null,
    ?string $endDate = null
): array {
    $sql = "
        SELECT
            source_booking_id AS reservation_id,
            customer_id,
            restaurant_id,
            table_id,
            restaurant_name,
            table_number,
            reservation_date,
            reservation_time,
            guest_count,
            special_requests,
            status,
            reservation_created_at,
            archived_at,
            'archive' AS record_source
        FROM ReservationArchive
        WHERE customer_id = ?
    ";

    $params = [$userId];

    if ($startDate !== null) {
        $sql .= " AND reservation_date >= ?";
        $params[] = $startDate;
    }

    if ($endDate !== null) {
        $sql .= " AND reservation_date <= ?";
        $params[] = $endDate;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function getReservationHistory(
    PDO $pdo,
    int $userId,
    ?string $startDate = null,
    ?string $endDate = null
): array {
    if ($userId <= 0) {
        throw new InvalidArgumentException('A valid user ID is required.');
    }

    $startDate = validateHistoryDate($startDate, 'startDate');
    $endDate = validateHistoryDate($endDate, 'endDate');

    if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
        throw new InvalidArgumentException(
            'startDate cannot be later than endDate.'
        );
    }

    $active = getActiveReservationHistory(
        $pdo,
        $userId,
        $startDate,
        $endDate
    );

    $archived = getArchivedReservationHistory(
        $pdo,
        $userId,
        $startDate,
        $endDate
    );

    $historyByBookingId = [];

    foreach ($active as $record) {
        $historyByBookingId[(string) $record['reservation_id']] = $record;
    }

    foreach ($archived as $record) {
        $historyByBookingId[(string) $record['reservation_id']] = $record;
    }

    $history = array_values($historyByBookingId);

    usort($history, static function (array $left, array $right): int {
        $leftValue = sprintf(
            '%s %s',
            $left['reservation_date'] ?? '',
            $left['reservation_time'] ?? ''
        );
        $rightValue = sprintf(
            '%s %s',
            $right['reservation_date'] ?? '',
            $right['reservation_time'] ?? ''
        );

        return strcmp($rightValue, $leftValue);
    });

    return $history;
}

function getReservationHistoryByDateRange(
    PDO $pdo,
    int $userId,
    string $startDate,
    string $endDate
): array {
    return getReservationHistory(
        $pdo,
        $userId,
        $startDate,
        $endDate
    );
}
?>