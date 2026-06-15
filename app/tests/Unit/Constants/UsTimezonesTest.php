<?php

declare(strict_types=1);

namespace Tests\Unit\Constants;

use App\Constants\UsTimezones;
use PHPUnit\Framework\TestCase;

final class UsTimezonesTest extends TestCase
{
    public function test_resolve_from_input_accepts_timezone_key(): void
    {
        $this->assertSame('America/New_York', UsTimezones::resolveFromInput('America/New_York'));
        $this->assertSame('America/Chicago', UsTimezones::resolveFromInput('America/Chicago'));
        $this->assertSame('Pacific/Honolulu', UsTimezones::resolveFromInput('Pacific/Honolulu'));
    }

    public function test_resolve_from_input_accepts_display_label(): void
    {
        $this->assertSame('America/New_York', UsTimezones::resolveFromInput('Eastern Time (ET)'));
        $this->assertSame('America/Chicago', UsTimezones::resolveFromInput('Central Time (CT)'));
        $this->assertSame('America/Los_Angeles', UsTimezones::resolveFromInput('Pacific Time (PT)'));
    }

    public function test_resolve_from_input_returns_null_for_empty(): void
    {
        $this->assertNull(UsTimezones::resolveFromInput(null));
        $this->assertNull(UsTimezones::resolveFromInput(''));
        $this->assertNull(UsTimezones::resolveFromInput('   '));
    }

    public function test_resolve_from_input_returns_null_for_invalid(): void
    {
        $this->assertNull(UsTimezones::resolveFromInput('Invalid/Timezone'));
        $this->assertNull(UsTimezones::resolveFromInput('Some Random Label'));
    }

    public function test_resolve_from_input_trims_whitespace(): void
    {
        $this->assertSame('America/New_York', UsTimezones::resolveFromInput('  Eastern Time (ET)  '));
    }
}
