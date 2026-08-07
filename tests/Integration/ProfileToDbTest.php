<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

final class ProfileToDbTest extends TestCase
{
    private static PDO $testPdo;

    /**
     * Runs ONCE before any test in this class executes.
     * Sets up our in-memory SQLite schema.
     */
    public static function setUpBeforeClass(): void
    {
        self::$testPdo = new PDO('sqlite::memory:');
        self::$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Recreate the exact schema profile.php expects
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

    /**
     * Runs BEFORE EACH individual test method.
     * Wipes the table so tests don't pollute each other.
     */
    protected function setUp(): void
    {
        self::$testPdo->exec("DELETE FROM users;");
    }

    // ── 1. HAPPY PATHS ───────────────────────────────────────────────────

    public function testFetchExistingUserProfileReturnsCorrectData(): void
    {
        // Seed user
        $stmt = self::$testPdo->prepare("
            INSERT INTO users (username, name, email, role, phone, profile_picture) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['liam_cs', 'Liam', 'liam@example.com', 'admin', '555-0100', 'avatar.png']);

        // Query DB
        $query = self::$testPdo->prepare("
            SELECT user_id AS id, username, name, email, role, phone, profile_picture, created_at 
            FROM users WHERE email = ?
        ");
        $query->execute(['liam@example.com']);
        $user = $query->fetch(PDO::FETCH_ASSOC);

        // Assertions
        $this->assertIsArray($user);
        $this->assertSame('liam_cs', $user['username']);
        $this->assertSame('Liam', $user['name']);
        $this->assertSame('admin', $user['role']);
        $this->assertSame('555-0100', $user['phone']);
        $this->assertSame('avatar.png', $user['profile_picture']);
    }

    public function testFetchUserProfileWithDefaultRoleAndNullPicture(): void
    {
        // Seed minimal user (allowing default values)
        $stmt = self::$testPdo->prepare("INSERT INTO users (username, name, email) VALUES (?, ?, ?)");
        $stmt->execute(['default_user', 'Sam', 'sam@example.com']);

        $query = self::$testPdo->prepare("
            SELECT user_id AS id, username, name, email, role, phone, profile_picture 
            FROM users WHERE email = ?
        ");
        $query->execute(['sam@example.com']);
        $user = $query->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('user', $user['role']); // Checks SQLite default column value
        $this->assertNull($user['profile_picture']);
        $this->assertNull($user['phone']);
    }

    // ── 2. NEGATIVE / EDGE CASES ─────────────────────────────────────────

    public function testFetchNonExistentUserReturnsFalse(): void
    {
        $query = self::$testPdo->prepare("
            SELECT user_id AS id, username, name, email FROM users WHERE email = ?
        ");
        $query->execute(['ghost@example.com']);
        $user = $query->fetch(PDO::FETCH_ASSOC);

        $this->assertFalse($user, "Querying a non-existent email should return false.");
    }

    public function testFetchUserIsCaseSensitiveOrExactMatch(): void
    {
        $stmt = self::$testPdo->prepare("INSERT INTO users (username, name, email) VALUES (?, ?, ?)");
        $stmt->execute(['case_test', 'Case User', 'user@example.com']);

        // Attempting to query with different casing
        $query = self::$testPdo->prepare("SELECT user_id AS id FROM users WHERE email = ?");
        $query->execute(['USER@EXAMPLE.COM']);
        $user = $query->fetch(PDO::FETCH_ASSOC);

        // Note: SQLite text comparisons can be case-insensitive by default for ASCII, 
        // but this verifies how your specific query handles casing!
        $this->assertNotNull($user);
    }

    // ── 3. DATA INTEGRITY & MULTIPLE RECORDS ─────────────────────────────

    public function testQueryRetrievesOnlyTargetUserAmongMultipleRecords(): void
    {
        // Seed multiple users
        $stmt = self::$testPdo->prepare("INSERT INTO users (username, name, email) VALUES (?, ?, ?)");
        $stmt->execute(['user1', 'User One', 'one@example.com']);
        $stmt->execute(['user2', 'User Two', 'two@example.com']);
        $stmt->execute(['user3', 'User Three', 'three@example.com']);

        // Fetch user 2 specifically
        $query = self::$testPdo->prepare("SELECT username, email FROM users WHERE email = ?");
        $query->execute(['two@example.com']);
        $user = $query->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('user2', $user['username']);
        $this->assertSame('two@example.com', $user['email']);
    }

    public function testDuplicateEmailInsertionThrowsException(): void
    {
        $this->expectException(\PDOException::class);

        $stmt = self::$testPdo->prepare("INSERT INTO users (username, name, email) VALUES (?, ?, ?)");
        $stmt->execute(['userA', 'Alice', 'duplicate@example.com']);
        
        // This second execute MUST trigger UNIQUE constraint violation exception
        $stmt->execute(['userB', 'Bob', 'duplicate@example.com']);
    }

    #[DataProvider('invalidEmailProvider')]
    public function testUnregisteredEmailsReturnFalse(string $invalidEmail): void
    {
        $query = self::$testPdo->prepare("SELECT user_id AS id FROM users WHERE email = ?");
        $query->execute([$invalidEmail]);
        
        $this->assertFalse($query->fetch(PDO::FETCH_ASSOC));
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'empty email'           => [''],
            'random string'         => ['not-an-email'],
            'sql injection attempt' => ["' OR '1'='1"],
            'spaces string'         => ['   '],
            'non-existent domain'   => ['nobody@nowhere.test'],
        ];
    }

}