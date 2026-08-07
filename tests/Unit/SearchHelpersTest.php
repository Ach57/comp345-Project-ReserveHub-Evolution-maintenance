<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SearchHelpersTest extends TestCase
{
    public function testNoFiltersReturnsBaseQueryOnly(): void
    {
        $result = buildSearchQuery('', '', '', '');

        $this->assertStringContainsString("WHERE r.status = 'approved'", $result['sql']);
        $this->assertStringContainsString('GROUP BY r.restaurant_id', $result['sql']);
        $this->assertStringNotContainsString('LIKE', $result['sql']);
        $this->assertStringNotContainsString('is_halal', $result['sql']);
        $this->assertStringNotContainsString('opening_time', $result['sql']);
        $this->assertSame([], $result['params']);
    }

    public function testQueryFilterAddsNameAndCuisineLike(): void
    {
        $result = buildSearchQuery('sushi', '', '', '');

        $this->assertStringContainsString('r.name LIKE ? OR r.cuisine LIKE ?', $result['sql']);
        $this->assertSame(['%sushi%', '%sushi%'], $result['params']);
    }

    public function testLocationFilterAddsLocationLike(): void
    {
        $result = buildSearchQuery('', 'downtown', '', '');

        $this->assertStringContainsString('r.location LIKE ?', $result['sql']);
        $this->assertSame(['%downtown%'], $result['params']);
    }

    public function testTimeFilterAddsOpeningAndClosingChecks(): void
    {
        $result = buildSearchQuery('', '', '18:00', '');

        $this->assertStringContainsString('r.opening_time <= ?', $result['sql']);
        $this->assertStringContainsString('r.closing_time >= ?', $result['sql']);
        $this->assertSame(['18:00', '18:00'], $result['params']);
    }

    public function testHalalOneIsTreatedAsActiveFilter(): void
    {
        $result = buildSearchQuery('', '', '', '1');

        $this->assertStringContainsString('r.is_halal = ?', $result['sql']);
        $this->assertSame([1], $result['params']);
    }

    public function testHalalZeroIsTreatedAsActiveFilter(): void
    {
        // '0' !== '' so this branch runs, unlike empty()-based filters.
        $result = buildSearchQuery('', '', '', '0');

        $this->assertStringContainsString('r.is_halal = ?', $result['sql']);
        $this->assertSame([0], $result['params']);
    }

    public function testHalalEmptyStringSkipsFilter(): void
    {
        $result = buildSearchQuery('', '', '', '');

        $this->assertStringNotContainsString('is_halal', $result['sql']);
        $this->assertSame([], $result['params']);
    }

    public function testAllFiltersCombineInDeclaredOrder(): void
    {
        $result = buildSearchQuery('pizza', 'uptown', '20:00', '1');

        // Params must be in the same order the ?'s appear: query, location, halal, time.
        $this->assertSame(
            ['%pizza%', '%pizza%', '%uptown%', 1, '20:00', '20:00'],
            $result['params']
        );

        $this->assertStringContainsString('GROUP BY r.restaurant_id', $result['sql']);
    }
}
