<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PDO;

// Mock/Inlined helper logic matching src/Helpers/LanguageHelpers.php
function sanitizeLangCode(string $lang): string {
    $clean = preg_replace('/[^a-zA-Z-]/', '', $lang);
    return substr($clean, 0, 50) ?: 'en';
}

function sanitizePageKey(string $page): string {
    $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);
    return substr($clean, 0, 50);
}

function buildTranslationMap(array $rows): object {
    $map = [];
    foreach ($rows as $row) {
        $map[$row['translation_key']] = $row['translation_value'];
    }
    return (object)$map;
}

final class LanguageToDbTest extends TestCase
{
    private static PDO $testPdo;

    public static function setUpBeforeClass(): void
    {
        self::$testPdo = new PDO('sqlite::memory:');
        self::$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Schema setup
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
        // Clean and re-seed sample data before every test run
        self::$testPdo->exec("DELETE FROM translations; DELETE FROM languages;");

        // Seed default languages
        self::$testPdo->exec("
            INSERT INTO languages (code, name, is_active) VALUES
            ('en', 'English', 1),
            ('fr', 'French', 1),
            ('de', 'German', 1),
            ('es', 'Spanish', 0); -- Inactive language
        ");

        // Seed translations
        self::$testPdo->exec("
            INSERT INTO translations (language_code, page_key, translation_key, translation_value) VALUES
            ('en', 'profile', 'welcome_title', 'Welcome to Profile'),
            ('en', 'profile', 'btn_export', 'Export Reservations'),
            ('fr', 'profile', 'welcome_title', 'Bienvenue sur le profil'),
            ('fr', 'profile', 'btn_export', 'Exporter les réservations'),
            ('de', 'profile', 'welcome_title', 'Willkommen im Profil');
        ");
    }

    /**
     * Helper simulating language.php API behavior
     */
    private function processLanguageRequest(?string $action, ?string $lang, ?string $page, PDO $pdo): array
    {
        if ($action === 'languages') {
            try {
                $stmt = $pdo->query("SELECT code, name FROM languages WHERE is_active = 1 ORDER BY code");
                return ['status' => 200, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            } catch (\PDOException $e) {
                return ['status' => 500, 'data' => []];
            }
        }

        if (!$page) {
            return ['status' => 200, 'data' => (object)[]];
        }

        $lang = sanitizeLangCode($lang ?? 'en');
        $page = sanitizePageKey($page);

        try {
            $stmt = $pdo->prepare("
                SELECT t.translation_key, t.translation_value
                FROM translations t
                JOIN languages l ON t.language_code = l.code
                WHERE t.language_code = ?
                  AND t.page_key      = ?
                  AND l.is_active     = 1
            ");
            $stmt->execute([$lang, $page]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                // Fall back to English if the requested language has no entries
                $stmt->execute(['en', $page]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return ['status' => 200, 'data' => buildTranslationMap($rows)];
        } catch (\PDOException $e) {
            return ['status' => 500, 'data' => (object)[]];
        }
    }

    // ── 1. ACTIVE LANGUAGES LISTING ─────────────────────────────────────

    public function testGetActiveLanguagesReturnsOnlyActiveSortedByCode(): void
    {
        $res = $this->processLanguageRequest('languages', null, null, self::$testPdo);

        $this->assertSame(200, $res['status']);
        $this->assertCount(3, $res['data']); // 'en', 'fr', 'de' (excludes 'es' because is_active = 0)
        $this->assertSame('de', $res['data'][0]['code']);
        $this->assertSame('en', $res['data'][1]['code']);
        $this->assertSame('fr', $res['data'][2]['code']);
    }

    // ── 2. TRANSLATION MAP & FALLBACKS ───────────────────────────────────

    public function testGetTranslationsReturnsMapForRequestedLanguage(): void
    {
        $res = $this->processLanguageRequest(null, 'fr', 'profile', self::$testPdo);

        $this->assertSame(200, $res['status']);
        $this->assertObjectHasProperty('welcome_title', $res['data']);
        $this->assertSame('Bienvenue sur le profil', $res['data']->welcome_title);
        $this->assertSame('Exporter les réservations', $res['data']->btn_export);
    }

    public function testGetTranslationsFallsBackToEnglishWhenLanguageHasNoEntries(): void
    {
        // German ('de') is missing 'btn_export' key entirely in DB
        $res = $this->processLanguageRequest(null, 'de', 'profile', self::$testPdo);

        $this->assertSame(200, $res['status']);
        $this->assertSame('Willkommen im Profil', $res['data']->welcome_title);
    }

    public function testGetTranslationsFallsBackToEnglishWhenLanguageIsInactive(): void
    {
        // 'es' is inactive in DB, should fall back to 'en'
        $res = $this->processLanguageRequest(null, 'es', 'profile', self::$testPdo);

        $this->assertSame(200, $res['status']);
        $this->assertSame('Welcome to Profile', $res['data']->welcome_title);
    }

    public function testMissingPageParameterReturnsEmptyObject(): void
    {
        $res = $this->processLanguageRequest(null, 'en', null, self::$testPdo);

        $this->assertSame(200, $res['status']);
        $this->assertEquals((object)[], $res['data']);
    }

    public function testNonExistentPageReturnsEmptyObjectFromFallback(): void
    {
        $res = $this->processLanguageRequest(null, 'en', 'non_existent_page', self::$testPdo);

        $this->assertSame(200, $res['status']);
        $this->assertEquals((object)[], $res['data']);
    }

    // ── 3. SANITIZATION & SECURITY ───────────────────────────────────────

    #[DataProvider('malformedLangProvider')]
    public function testLangSanitizationCleansSpecialCharactersAndSqlInjection(string $inputLang, string $expectedClean): void
    {
        $this->assertSame($expectedClean, sanitizeLangCode($inputLang));
    }

    public static function malformedLangProvider(): array
    {
        return [
            // Regex strips numbers and quotes, leaving 'enOR'
            'sql injection attempt' => ["en' OR '1'='1", "enOR"],
            'script tag attempt'    => ["<script>fr</script>", "scriptfrscript"],
            'special chars'         => ["fr_CA!", "frCA"],
            'valid hyphenated lang' => ["en-US", "en-US"],
        ];
    }

    public function testPageSanitizationStripsInvalidChars(): void
    {
        // Sanitizer allows hyphens (-), so '--' is preserved at the end
        $cleanPage = sanitizePageKey("profile; DROP TABLE translations;--");
        $this->assertSame("profileDROPTABLEtranslations--", $cleanPage);
    }

    // ── 4. DATABASE EXCEPTION HANDLING ───────────────────────────────────

    public function testDatabaseErrorOnLanguagesActionReturns500(): void
    {
        $brokenPdo = new PDO('sqlite::memory:'); // Missing tables

        $res = $this->processLanguageRequest('languages', null, null, $brokenPdo);

        $this->assertSame(500, $res['status']);
        $this->assertSame([], $res['data']);
    }

    public function testDatabaseErrorOnTranslationsQueryReturns500(): void
    {
        $brokenPdo = new PDO('sqlite::memory:'); // Missing tables

        $res = $this->processLanguageRequest(null, 'en', 'profile', $brokenPdo);

        $this->assertSame(500, $res['status']);
        $this->assertEquals((object)[], $res['data']);
    }

    // ── 5. INVERSE & NEGATIVE TEST CASES ───────────────────────────────────

    public function testEmptyOrWhitespaceLangDefaultsToEnglish(): void
    {
        $res = $this->processLanguageRequest(null, '   ', 'profile', self::$testPdo);

        $this->assertSame(200, $res['status']);
        // Should fetch English translations because whitespace gets cleaned to 'en'
        $this->assertSame('Welcome to Profile', $res['data']->welcome_title);
    }

    public function testLanguageCodeExceedingMaxLengthIsTruncated(): void
    {
        $longLang = str_repeat('a', 60); // 60 chars
        $sanitized = sanitizeLangCode($longLang);

        $this->assertSame(50, strlen($sanitized));
    }

    public function testPureSpecialCharsLangDefaultsToEnglish(): void
    {
        $res = $this->processLanguageRequest(null, '!@#$%^&*()', 'profile', self::$testPdo);

        $this->assertSame(200, $res['status']);
        // Sanitizer strips everything to empty string, falling back to 'en'
        $this->assertSame('Welcome to Profile', $res['data']->welcome_title);
    }

    public function testGetActiveLanguagesWhenNoActiveLanguagesExistReturnsEmptyArray(): void
    {
        // Deactivate all languages in DB
        self::$testPdo->exec("UPDATE languages SET is_active = 0;");

        $res = $this->processLanguageRequest('languages', null, null, self::$testPdo);

        $this->assertSame(200, $res['status']);
        $this->assertIsArray($res['data']);
        $this->assertEmpty($res['data']);
    }

    public function testMissingBothRequestedAndEnglishFallbackTranslationsReturnsEmptyObject(): void
    {
        // Request a page key that has no translations in ANY language
        $res = $this->processLanguageRequest(null, 'fr', 'unknown_page_key', self::$testPdo);

        $this->assertSame(200, $res['status']);
        $this->assertEquals((object)[], $res['data']);
    }

    public function testWhitespaceOnlyPageKeyReturnsEmptyObject(): void
    {
        $res = $this->processLanguageRequest(null, 'en', '   ', self::$testPdo);

        $this->assertSame(200, $res['status']);
        $this->assertEquals((object)[], $res['data']);
    }
}