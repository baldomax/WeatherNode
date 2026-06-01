<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Versioning;
use PHPUnit\Framework\TestCase;

class VersioningTest extends TestCase
{
    public function test_it_validates_year_based_versions(): void
    {
        $this->assertTrue(Versioning::isValidYearBased('v2026.03.0'));
        $this->assertTrue(Versioning::isValidYearBased('2026.12.5-dev'));
        $this->assertFalse(Versioning::isValidYearBased('v2026.13.0'));
        $this->assertFalse(Versioning::isValidYearBased('v26.03.0'));
    }

    public function test_it_bumps_patch_month_and_year_with_rollover(): void
    {
        $this->assertSame('v2026.03.1-dev', Versioning::bumpYearBased('v2026.03.0-dev', 'patch'));
        $this->assertSame('v2026.04.0-dev', Versioning::bumpYearBased('v2026.03.4-dev', 'month'));
        $this->assertSame('v2027.01.0-dev', Versioning::bumpYearBased('v2026.12.4-dev', 'month'));
        $this->assertSame('v2027.01.0', Versioning::bumpYearBased('v2026.11.9', 'year'));
    }

    public function test_it_compares_versions_correctly_for_update_checks(): void
    {
        $this->assertSame(-1, Versioning::compare('v2026.03.0', 'v2026.03.1'));
        $this->assertSame(-1, Versioning::compare('v2026.03.0-dev', 'v2026.03.0'));
        $this->assertSame(1, Versioning::compare('v2026.04.0', 'v2026.03.9'));
        $this->assertSame(-1, Versioning::compare('v0.1.0', 'v2026.03.0'));
    }
}

