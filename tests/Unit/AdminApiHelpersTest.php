<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminApiHelpersTest extends TestCase
{
    // ── hasRequiredAdminUserFields ───────────────────────────────────────

    public function testAllFieldsPresentReturnsTrue(): void
    {
        $this->assertTrue(hasRequiredAdminUserFields('bob123', 'Bob', 'bob@example.com', 'secret123'));
    }

    public function testMissingUsernameReturnsFalse(): void
    {
        $this->assertFalse(hasRequiredAdminUserFields('', 'Bob', 'bob@example.com', 'secret123'));
    }

    public function testMissingPasswordReturnsFalse(): void
    {
        $this->assertFalse(hasRequiredAdminUserFields('bob123', 'Bob', 'bob@example.com', ''));
    }

    // ── normalizeAdminRole ───────────────────────────────────────────────

    public function testAdminRoleIsUnchanged(): void
    {
        $this->assertSame('admin', normalizeAdminRole('admin'));
    }

    public function testCustomerRoleIsUnchanged(): void
    {
        $this->assertSame('customer', normalizeAdminRole('customer'));
    }

    public function testUnknownRoleFallsBackToVendor(): void
    {
        // Different default than SignupHelpers' normalizeRole() (which falls back to 'customer').
        $this->assertSame('vendor', normalizeAdminRole('superadmin'));
    }

    public function testEmptyRoleFallsBackToVendor(): void
    {
        $this->assertSame('vendor', normalizeAdminRole(''));
    }

    // ── hasValidRoleStrict ───────────────────────────────────────────────

    public function testValidRoleReturnsTrue(): void
    {
        $this->assertTrue(hasValidRoleStrict('vendor'));
    }

    public function testEmptyRoleReturnsFalse(): void
    {
        $this->assertFalse(hasValidRoleStrict(''));
    }

    public function testUnknownRoleReturnsFalse(): void
    {
        // Unlike normalizeAdminRole(), this rejects rather than defaulting.
        $this->assertFalse(hasValidRoleStrict('superadmin'));
    }

    // ── hasRequiredNameAndEmail ──────────────────────────────────────────

    public function testNameAndEmailPresentReturnsTrue(): void
    {
        $this->assertTrue(hasRequiredNameAndEmail('Bob', 'bob@example.com'));
    }

    public function testMissingNameReturnsFalse(): void
    {
        $this->assertFalse(hasRequiredNameAndEmail('', 'bob@example.com'));
    }

    public function testMissingEmailReturnsFalse(): void
    {
        $this->assertFalse(hasRequiredNameAndEmail('Bob', ''));
    }

    // ── getFileExtension ─────────────────────────────────────────────────

    public function testExtractsLowercaseExtension(): void
    {
        $this->assertSame('jpg', getFileExtension('photo.JPG'));
    }

    public function testExtractsExtensionFromMultiDotFilename(): void
    {
        $this->assertSame('png', getFileExtension('my.restaurant.logo.png'));
    }

    public function testNoExtensionReturnsEmptyString(): void
    {
        $this->assertSame('', getFileExtension('noextension'));
    }

    // ── resolveVendorId ──────────────────────────────────────────────────

    public function testNonEmptyVendorIdIsReturnedAsIs(): void
    {
        $this->assertSame('v001', resolveVendorId('v001'));
    }

    public function testEmptyStringVendorIdBecomesNull(): void
    {
        $this->assertNull(resolveVendorId(''));
    }

    public function testNullVendorIdStaysNull(): void
    {
        $this->assertNull(resolveVendorId(null));
    }

    // ── resolveSeedRating ────────────────────────────────────────────────

    public function testUsesSeedRatingWhenPresent(): void
    {
        $this->assertSame(4.5, resolveSeedRating(['seed_rating' => '4.5', 'rating' => '3.0']));
    }

    public function testFallsBackToRatingWhenSeedRatingMissing(): void
    {
        $this->assertSame(3.0, resolveSeedRating(['rating' => '3.0']));
    }

    public function testFallsBackToRatingWhenSeedRatingIsEmptyString(): void
    {
        $this->assertSame(3.0, resolveSeedRating(['seed_rating' => '', 'rating' => '3.0']));
    }

    public function testReturnsNullWhenNeitherPresent(): void
    {
        $this->assertNull(resolveSeedRating([]));
    }

    public function testReturnsNullWhenBothAreEmptyStrings(): void
    {
        $this->assertNull(resolveSeedRating(['seed_rating' => '', 'rating' => '']));
    }

    // ── isValidApprovalDecision ──────────────────────────────────────────

    public function testApprovedDecisionIsValid(): void
    {
        $this->assertTrue(isValidApprovalDecision('r001', 'approved'));
    }

    public function testRejectedDecisionIsValid(): void
    {
        $this->assertTrue(isValidApprovalDecision('r001', 'rejected'));
    }

    public function testMissingIdIsInvalid(): void
    {
        $this->assertFalse(isValidApprovalDecision(null, 'approved'));
    }

    public function testUnknownStatusIsInvalid(): void
    {
        $this->assertFalse(isValidApprovalDecision('r001', 'pending'));
    }

    // ── hasRequiredMessageFields ─────────────────────────────────────────

    public function testAllMessageFieldsPresentReturnsTrue(): void
    {
        $this->assertTrue(hasRequiredMessageFields('Bob', 'bob@example.com', 'Question', 'Hello there'));
    }

    public function testMissingSubjectReturnsFalse(): void
    {
        $this->assertFalse(hasRequiredMessageFields('Bob', 'bob@example.com', '', 'Hello there'));
    }

    public function testMissingMessageReturnsFalse(): void
    {
        $this->assertFalse(hasRequiredMessageFields('Bob', 'bob@example.com', 'Question', ''));
    }
}
