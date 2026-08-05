<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class IdHelpersTest extends TestCase
{
    public function testPadsToThreeDigitsByDefault(): void
    {
        $this->assertSame('c001', generateSequentialId('c', 1));
    }

    public function testDoesNotPadWhenNumberAlreadyLongEnough(): void
    {
        $this->assertSame('r123', generateSequentialId('r', 123));
    }

    public function testTruncatesNothingWhenNumberExceedsPadLength(): void
    {
        // str_pad never truncates — a longer number just stays as-is.
        $this->assertSame('t1000', generateSequentialId('t', 1000));
    }

    public function testDifferentPrefixesProduceDifferentIds(): void
    {
        $this->assertSame('b005', generateSequentialId('b', 5));
        $this->assertSame('m005', generateSequentialId('m', 5));
    }

    public function testCustomPadLength(): void
    {
        $this->assertSame('c0007', generateSequentialId('c', 7, 4));
    }

    public function testZeroIsPadded(): void
    {
        $this->assertSame('c000', generateSequentialId('c', 0));
    }
}
