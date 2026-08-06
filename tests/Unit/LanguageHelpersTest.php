<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LanguageHelpersTest extends TestCase
{
    // ── sanitizeLangCode ─────────────────────────────────────────────────

    public function testAllowsPlainAlphaCode(): void
    {
        $this->assertSame('en', sanitizeLangCode('en'));
    }

    public function testAllowsHyphenatedCode(): void
    {
        $this->assertSame('en-US', sanitizeLangCode('en-US'));
    }

    public function testStripsDigitsAndSymbols(): void
    {
        $this->assertSame('en', sanitizeLangCode('en123!@#'));
    }

    public function testTruncatesToTenCharsBeforeStripping(): void
    {
        // substr happens first, so anything past 10 raw chars is dropped
        // even if the extra characters would otherwise be valid.
        $this->assertSame('abcdefghij', sanitizeLangCode('abcdefghijKLMNOP'));
    }

    public function testEmptyStringStaysEmpty(): void
    {
        $this->assertSame('', sanitizeLangCode(''));
    }

    // ── sanitizePageKey ──────────────────────────────────────────────────

    public function testAllowsAlphanumericAndHyphen(): void
    {
        $this->assertSame('home-page2', sanitizePageKey('home-page2'));
    }

    public function testStripsInvalidSymbols(): void
    {
        $this->assertSame('homepage', sanitizePageKey('home page!'));
    }

    public function testTruncatesToFiftyChars(): void
    {
        $input = str_repeat('a', 60);
        $this->assertSame(str_repeat('a', 50), sanitizePageKey($input));
    }

    // ── buildTranslationMap ──────────────────────────────────────────────

    public function testBuildsMapFromRows(): void
    {
        $rows = [
            ['translation_key' => 'welcome', 'translation_value' => 'Welcome'],
            ['translation_key' => 'goodbye', 'translation_value' => 'Goodbye'],
        ];

        $this->assertSame(
            ['welcome' => 'Welcome', 'goodbye' => 'Goodbye'],
            buildTranslationMap($rows)
        );
    }

    public function testEmptyRowsProducesEmptyMap(): void
    {
        $this->assertSame([], buildTranslationMap([]));
    }

    public function testDuplicateKeyLaterRowWins(): void
    {
        $rows = [
            ['translation_key' => 'welcome', 'translation_value' => 'First'],
            ['translation_key' => 'welcome', 'translation_value' => 'Second'],
        ];

        $this->assertSame(['welcome' => 'Second'], buildTranslationMap($rows));
    }
}
