<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PDO;

final class DbToProfileTest extends TestCase
{
    private static PDO $testPdo;

    public static function setUpBeforeClass(): void
    {
        self::$testPdo = new PDO('sqlite::memory:');
        self::$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        self::$testPdo->exec("
            CREATE TABLE users (
                user_id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                role TEXT DEFAULT 'user',
                phone TEXT,
                profile_picture TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    protected function setUp(): void
    {
        self::$testPdo->exec("DELETE FROM users;");
    }

    /**
     * Helper function simulating the logic in profile.php
     * given an input payload array and a live PDO connection.
     */
    private function processProfileRequest(?array $inputData, PDO $pdo): array
    {
        // 1. Validation check (matching profile.php logic)
        if (empty($inputData['email'])) {
            return [
                'success' => false,
                'message' => 'Email is required to fetch profile.'
            ];
        }

        $email = $inputData['email'];

        try {
            $stmt = $pdo->prepare("
                SELECT user_id AS id, username, name, email, role, phone, profile_picture, created_at 
                FROM users WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                return [
                    'success' => true,
                    'user'    => $user
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'User not found.'
                ];
            }
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    // ── 1. SUCCESS RESPONSES ─────────────────────────────────────────────

    public function testDatabaseRecordFormattedIntoSuccessJsonResponse(): void
    {
        // Seed database
        $stmt = self::$testPdo->prepare("
            INSERT INTO users (username, name, email, role, phone, profile_picture) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['liam_cs', 'Liam', 'liam@example.com', 'admin', '555-0199', 'pic.jpg']);

        // Execute request
        $response = $this->processProfileRequest(['email' => 'liam@example.com'], self::$testPdo);

        // Assert JSON structure
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('user', $response);
        
        $user = $response['user'];
        $this->assertSame('liam_cs', $user['username']);
        $this->assertSame('Liam', $user['name']);
        $this->assertSame('liam@example.com', $user['email']);
        $this->assertSame('admin', $user['role']);
        $this->assertSame('555-0199', $user['phone']);
        $this->assertSame('pic.jpg', $user['profile_picture']);
        $this->assertArrayHasKey('created_at', $user);
    }

    // ── 2. ERROR & VALIDATION RESPONSES ──────────────────────────────────

    public function testNonExistentUserReturnsUserNotFoundMessage(): void
    {
        $response = $this->processProfileRequest(['email' => 'missing@example.com'], self::$testPdo);

        $this->assertFalse($response['success']);
        $this->assertSame('User not found.', $response['message']);
        $this->assertArrayNotHasKey('user', $response);
    }

    #[DataProvider('invalidPayloadProvider')]
    public function testInvalidPayloadsReturnValidationMessage(?array $payload): void
    {
        $response = $this->processProfileRequest($payload, self::$testPdo);

        $this->assertFalse($response['success']);
        $this->assertSame('Email is required to fetch profile.', $response['message']);
    }

    public static function invalidPayloadProvider(): array
    {
        return [
            'null payload'                => [null],
            'empty array'                 => [[]],
            'missing email key'           => [['username' => 'liam']],
            'empty string email'          => [['email' => '']],
            'numeric email payload'       => [['email' => 0]],
            'boolean false email payload' => [['email' => false]],
        ];
    }
    

    // ── 3. DATABASE EXCEPTION HANDLING ───────────────────────────────────

    public function testDatabaseExceptionIsCaughtAndFormated(): void
    {
        // Create a broken PDO connection targeting a non-existent table
        $brokenPdo = new PDO('sqlite::memory:');
        
        $response = $this->processProfileRequest(['email' => 'test@example.com'], $brokenPdo);

        $this->assertFalse($response['success']);
        $this->assertStringStartsWith('Database error:', $response['message']);
    }

    public function testProfileResponseIsCleanlyJsonEncodable(): void
    {
        $stmt = self::$testPdo->prepare("INSERT INTO users (username, name, email) VALUES (?, ?, ?)");
        $stmt->execute(['json_user', 'JSON Test', 'json@example.com']);

        $response = $this->processProfileRequest(['email' => 'json@example.com'], self::$testPdo);
    
        $jsonOutput = json_encode($response);
    
        $this->assertJson($jsonOutput);
        $this->assertStringContainsString('json_user', $jsonOutput);
    }

    public function testRequestWithUntrimmedEmailDoesNotMatch(): void
    {
        $stmt = self::$testPdo->prepare("INSERT INTO users (username, name, email) VALUES (?, ?, ?)");
        $stmt->execute(['space_user', 'Space User', 'space@example.com']);

        // Attempting to query with padded whitespace
        $response = $this->processProfileRequest(['email' => '  space@example.com  '], self::$testPdo);

        $this->assertFalse($response['success']);
        $this->assertSame('User not found.', $response['message']);
    }

    public function testProfileWithSpecialCharactersInFields(): void
    {
        $specialName = 'François-Noël O\'Connor';
        $stmt = self::$testPdo->prepare("INSERT INTO users (username, name, email) VALUES (?, ?, ?)");
        $stmt->execute(['special_char_user', $specialName, 'special@example.com']);

        $response = $this->processProfileRequest(['email' => 'special@example.com'], self::$testPdo);

        $this->assertTrue($response['success']);
        $this->assertSame($specialName, $response['user']['name']);
    }
    
}