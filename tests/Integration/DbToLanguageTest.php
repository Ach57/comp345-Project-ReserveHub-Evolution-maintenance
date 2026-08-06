<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;

final class DbToLanguageTest extends TestCase
{
    private static PDO $testPdo;

    public static function setUpBeforeClass(): void
    {
        self::$testPdo = new PDO('sqlite::memory:');
        self::$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        self::$testPdo->exec("
            CREATE TABLE languages (
                code TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                is_active INTEGER DEFAULT 1
            );

            CREATE TABLE translations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                language_code TEXT NOT NULL,
                page_key TEXT NOT NULL,
                translation_key TEXT NOT NULL,
                translation_value TEXT NOT NULL,
                FOREIGN KEY (language_code) REFERENCES languages(code)
            );
        ");
    }

    protected function setUp(): void
    {
        self::$testPdo->exec("DELETE FROM translations; DELETE FROM languages;");
    }

    // ── 1. UNEXPECTED DATA VALUES & DUPLICATES ────────────────────────────

    public function testDatabaseNullOrEmptyValuesMapToEmptyStrings(): void
    {
        self::$testPdo->exec("
            INSERT INTO languages (code, name, is_active) VALUES ('en', 'English', 1);
            INSERT INTO translations (language_code, page_key, translation_key, translation_value) 
            VALUES ('en', 'profile', 'header_subtext', '');
        ");

        $stmt = self::$testPdo->prepare("SELECT translation_key, translation_value FROM translations WHERE page_key = ?");
        $stmt->execute(['profile']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['translation_key']] = $row['translation_value'];
        }

        $this->assertArrayHasKey('header_subtext', $map);
        $this->assertSame('', $map['header_subtext']);
    }

    public function testDatabaseDuplicateTranslationKeysLastEntryWins(): void
    {
        self::$testPdo->exec("
            INSERT INTO languages (code, name, is_active) VALUES ('en', 'English', 1);
            INSERT INTO translations (language_code, page_key, translation_key, translation_value) VALUES 
            ('en', 'profile', 'welcome_title', 'Old Welcome'),
            ('en', 'profile', 'welcome_title', 'New Welcome');
        ");

        $stmt = self::$testPdo->prepare("SELECT translation_key, translation_value FROM translations WHERE page_key = ?");
        $stmt->execute(['profile']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['translation_key']] = $row['translation_value'];
        }

        $this->assertSame('New Welcome', $map['welcome_title']);
    }

    // ── 2. ENCODING, SPECIAL CHARACTERS & HTML CONTENT ────────────────────

    public function testDatabaseUtf8SpecialCharactersPreservedInMap(): void
    {
        self::$testPdo->exec("
            INSERT INTO languages (code, name, is_active) VALUES ('fr', 'French', 1);
            INSERT INTO translations (language_code, page_key, translation_key, translation_value) VALUES 
            ('fr', 'profile', 'btn_save', 'Enregistrer les modifications'),
            ('fr', 'profile', 'header_title', 'Profil d''utilisateur & Paramètres');
        ");

        $stmt = self::$testPdo->prepare("SELECT translation_key, translation_value FROM translations WHERE language_code = ?");
        $stmt->execute(['fr']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['translation_key']] = $row['translation_value'];
        }

        $this->assertSame('Enregistrer les modifications', $map['btn_save']);
        $this->assertSame("Profil d'utilisateur & Paramètres", $map['header_title']);
    }

    public function testDatabaseHtmlAndScriptStringsPassedUnescapedToMap(): void
    {
        // Translations often contain bold tags or formatting spans
        $htmlSnippet = '<span>Welcome <strong>Liam</strong></span>';

        self::$testPdo->exec("
            INSERT INTO languages (code, name, is_active) VALUES ('en', 'English', 1);
            INSERT INTO translations (language_code, page_key, translation_key, translation_value) VALUES 
            ('en', 'profile', 'welcome_html', '{$htmlSnippet}');
        ");

        $stmt = self::$testPdo->prepare("SELECT translation_key, translation_value FROM translations WHERE page_key = ?");
        $stmt->execute(['profile']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['translation_key']] = $row['translation_value'];
        }

        $this->assertSame($htmlSnippet, $map['welcome_html']);
    }

    // ── 3. STATE CHANGES & DATABASE CONSTRAINTS ──────────────────────────

    public function testDeactivatingLanguageInDbInstantlyExcludesItsTranslations(): void
    {
        self::$testPdo->exec("
            INSERT INTO languages (code, name, is_active) VALUES ('fr', 'French', 1);
            INSERT INTO translations (language_code, page_key, translation_key, translation_value) VALUES 
            ('fr', 'profile', 'title', 'Profil');
        ");

        // Toggle is_active to 0
        self::$testPdo->exec("UPDATE languages SET is_active = 0 WHERE code = 'fr';");

        $stmt = self::$testPdo->prepare("
            SELECT t.translation_key, t.translation_value
            FROM translations t
            JOIN languages l ON t.language_code = l.code
            WHERE t.language_code = ? AND l.is_active = 1
        ");
        $stmt->execute(['fr']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEmpty($rows);
    }

    public function testNumericValuesInDatabaseAreFetchedAsStringValues(): void
    {
        self::$testPdo->exec("
            INSERT INTO languages (code, name, is_active) VALUES ('en', 'English', 1);
            INSERT INTO translations (language_code, page_key, translation_key, translation_value) VALUES 
            ('en', 'profile', 'max_limit', '100');
        ");

        $stmt = self::$testPdo->prepare("SELECT translation_key, translation_value FROM translations WHERE page_key = ?");
        $stmt->execute(['profile']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertIsString($rows[0]['translation_value']);
        $this->assertSame('100', $rows[0]['translation_value']);
    }

    // ── 4. QUERY DISRUPTION & SCHEMA ERRORS ───────────────────────────────

    public function testDbQueryFailureTriggersCatchBlock(): void
    {
        $caughtException = false;
        try {
            self::$testPdo->query("SELECT non_existent_column FROM languages");
        } catch (\PDOException $e) {
            $caughtException = true;
        }

        $this->assertTrue($caughtException);
    }

    public function testQueryingEmptyTableReturnsEmptyArrayWithoutErrors(): void
    {
        $stmt = self::$testPdo->prepare("SELECT * FROM translations WHERE language_code = ?");
        $stmt->execute(['en']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertIsArray($rows);
        $this->assertEmpty($rows);
    }

    public function testCaseSensitivityOfLanguageCodesInDatabaseQueries(): void
    {
        self::$testPdo->exec("
            INSERT INTO languages (code, name, is_active) VALUES ('en', 'English', 1);
            INSERT INTO translations (language_code, page_key, translation_key, translation_value) VALUES 
            ('en', 'profile', 'title', 'Profile');
        ");

        // Querying uppercase 'EN'
        $stmt = self::$testPdo->prepare("SELECT translation_key FROM translations WHERE language_code = ?");
        $stmt->execute(['EN']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // SQLite comparison is case-sensitive by default for TEXT unless explicitly set otherwise
        $this->assertEmpty($rows);
    }
}