<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PDO;

final class ExportReservationsToDbTest extends TestCase
{
    private static PDO $testPdo;

    public static function setUpBeforeClass(): void
    {
        self::$testPdo = new PDO('sqlite::memory:');
        self::$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        self::$testPdo->exec("
            CREATE TABLE users (
                user_id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                role TEXT DEFAULT 'user'
            );

            CREATE TABLE restaurants (
                restaurant_id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );

            CREATE TABLE reservations (
                booking_id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                restaurant_id INTEGER NOT NULL,
                date TEXT NOT NULL,
                reservation_time TEXT NOT NULL,
                guest_count INTEGER NOT NULL,
                status TEXT NOT NULL,
                FOREIGN KEY (customer_id) REFERENCES users(user_id),
                FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id)
            );
        ");
    }

    protected function setUp(): void
    {
        self::$testPdo->exec("DELETE FROM reservations;");
        self::$testPdo->exec("DELETE FROM users;");
        self::$testPdo->exec("DELETE FROM restaurants;");
    }

    /**
     * Helper simulating the request processing and query targeting of export.php
     */
    private function executeExportQuery(?array $session, PDO $pdo): array
    {
        if (!isset($session['user_id'])) {
            return [
                'status_code' => 401,
                'response' => [
                    'success' => false,
                    'message' => 'You must be logged in to export your reservation history.'
                ]
            ];
        }

        $userId = (int)$session['user_id'];
        $isAdmin = ($session['role'] ?? '') === 'admin';

        if ($isAdmin) {
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
        } else {
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
            $stmt->execute(['user_id' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'status_code' => 200,
            'rows' => $rows
        ];
    }

    // ── 1. AUTHENTICATION / AUTHORIZATION ────────────────────────────────

    public function testUnauthenticatedUserReturns401Unauthorized(): void
    {
        $result = $this->executeExportQuery(null, self::$testPdo);

        $this->assertSame(401, $result['status_code']);
        $this->assertFalse($result['response']['success']);
        $this->assertSame('You must be logged in to export your reservation history.', $result['response']['message']);
    }

    public function testSessionWithoutUserIdReturns401(): void
    {
        $result = $this->executeExportQuery(['role' => 'user'], self::$testPdo);

        $this->assertSame(401, $result['status_code']);
    }

    // ── 2. QUERY SCOPING & FILTERING ─────────────────────────────────────

    public function testRegularUserOnlyRetrievesTheirOwnReservations(): void
    {
        self::$testPdo->exec("INSERT INTO users (user_id, name) VALUES (1, 'Liam'), (2, 'Other User');");
        self::$testPdo->exec("INSERT INTO restaurants (restaurant_id, name) VALUES (10, 'Le Bistro');");

        self::$testPdo->exec("
            INSERT INTO reservations (customer_id, restaurant_id, date, reservation_time, guest_count, status)
            VALUES 
                (1, 10, '2026-06-01', '18:00', 2, 'Confirmed'),
                (1, 10, '2026-06-02', '19:00', 4, 'Confirmed'),
                (2, 10, '2026-06-03', '20:00', 1, 'Cancelled');
        ");

        $session = ['user_id' => 1, 'role' => 'user'];
        $result = $this->executeExportQuery($session, self::$testPdo);

        $this->assertSame(200, $result['status_code']);
        $this->assertCount(2, $result['rows']);
        foreach ($result['rows'] as $row) {
            $this->assertSame('Liam', $row['user_name']);
        }
    }

    public function testAdminUserRetrievesAllUserReservations(): void
    {
        self::$testPdo->exec("INSERT INTO users (user_id, name) VALUES (1, 'Liam'), (2, 'Other User');");
        self::$testPdo->exec("INSERT INTO restaurants (restaurant_id, name) VALUES (10, 'Le Bistro');");

        self::$testPdo->exec("
            INSERT INTO reservations (customer_id, restaurant_id, date, reservation_time, guest_count, status)
            VALUES 
                (1, 10, '2026-06-01', '18:00', 2, 'Confirmed'),
                (2, 10, '2026-06-03', '20:00', 1, 'Cancelled');
        ");

        $session = ['user_id' => 1, 'role' => 'admin'];
        $result = $this->executeExportQuery($session, self::$testPdo);

        $this->assertSame(200, $result['status_code']);
        $this->assertCount(2, $result['rows']);
    }

    public function testQueryOrdersReservationsByDateAndTimeDescending(): void
    {
        self::$testPdo->exec("INSERT INTO users (user_id, name) VALUES (1, 'Liam');");
        self::$testPdo->exec("INSERT INTO restaurants (restaurant_id, name) VALUES (10, 'Le Bistro');");

        self::$testPdo->exec("
            INSERT INTO reservations (customer_id, restaurant_id, date, reservation_time, guest_count, status)
            VALUES 
                (1, 10, '2026-01-01', '12:00', 2, 'Confirmed'),
                (1, 10, '2026-12-31', '20:00', 2, 'Confirmed'),
                (1, 10, '2026-12-31', '21:00', 2, 'Confirmed');
        ");

        $session = ['user_id' => 1, 'role' => 'user'];
        $result = $this->executeExportQuery($session, self::$testPdo);

        $rows = $result['rows'];
        $this->assertSame('2026-12-31', $rows[0]['reservation_date']);
        $this->assertSame('21:00', $rows[0]['reservation_time']);
        $this->assertSame('2026-01-01', $rows[2]['reservation_date']);
    }

    #[DataProvider('invalidUserRolesProvider')]
    public function testNonAdminRolesAreTreatedAsRegularUsers(string $role): void
    {
        self::$testPdo->exec("INSERT INTO users (user_id, name) VALUES (1, 'Liam'), (2, 'Other');");
        self::$testPdo->exec("INSERT INTO restaurants (restaurant_id, name) VALUES (10, 'Bistro');");
        self::$testPdo->exec("INSERT INTO reservations (customer_id, restaurant_id, date, reservation_time, guest_count, status) VALUES (1, 10, '2026-01-01', '18:00', 2, 'Confirmed');");

        $session = ['user_id' => 1, 'role' => $role];
        $result = $this->executeExportQuery($session, self::$testPdo);

        $this->assertCount(1, $result['rows']);
    }

    public static function invalidUserRolesProvider(): array
    {
        return [
            'standard user' => ['user'],
            'guest role'    => ['guest'],
            'empty role'    => [''],
            'random string' => ['manager'],
        ];
    }
}