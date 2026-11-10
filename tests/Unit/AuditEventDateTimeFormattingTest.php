<?php

namespace Tests\Unit;

use App\Filament\Support\AuditEventResource;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class AuditEventDateTimeFormattingTest extends TestCase
{
    public function test_allowlisted_audit_timestamps_accept_the_existing_iso8601_snapshot_shape(): void
    {
        app()->instance('request', Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
        ]));
        app()->forgetScopedInstances();

        $formatted = $this->formatAuditValue('next_follow_up_at', '2026-11-04T17:30:00+02:00');

        $this->assertSame("\u{2068}Nov 4, 2026 · 05:30 PM\u{2069}", $formatted);
    }

    public function test_audit_date_only_values_are_formatted_without_timezone_conversion(): void
    {
        app()->instance('request', Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'ar-EG',
        ]));
        app()->forgetScopedInstances();

        $formatted = $this->formatAuditValue('deal_date', '2026-11-04');

        $this->assertSame("\u{2068}4 نوفمبر 2026\u{2069}", $formatted);
    }

    public function test_unrecognized_legacy_audit_values_remain_visible_without_being_normalized(): void
    {
        app()->instance('request', Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
        ]));
        app()->forgetScopedInstances();

        $this->assertSame('legacy-time', $this->formatAuditValue('next_follow_up_at', 'legacy-time'));
    }

    private function formatAuditValue(string $key, string $value): string
    {
        $method = new ReflectionMethod(AuditEventResource::class, 'formatValue');
        $method->setAccessible(true);

        return $method->invoke(null, $key, $value);
    }
}
