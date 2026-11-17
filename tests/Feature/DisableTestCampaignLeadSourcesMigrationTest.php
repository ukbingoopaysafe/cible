<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DisableTestCampaignLeadSourcesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_disables_only_exact_campaign_names_in_their_own_department_and_is_repeatable(): void
    {
        $campaigns = [
            'rental_lead_sources' => [
                ['حملة اختبار الإيجار', true],
                ['حملة اختبار إعادة البيع', true],
            ],
            'resale_lead_sources' => [
                ['حملة اختبار إعادة البيع', true],
                ['حملة اختبار الإيجار', true],
            ],
        ];

        foreach ($campaigns as $table => $rows) {
            foreach ($rows as [$name, $isActive]) {
                DB::table($table)->insert([
                    'name' => $name,
                    'type' => 'awareness',
                    'is_other' => false,
                    'is_active' => $isActive,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $migrationPath = database_path('migrations/2026_11_16_070000_disable_test_campaign_lead_sources.php');
        $this->assertFileExists($migrationPath);
        $migration = require $migrationPath;
        $migration->up();
        $migration->up();

        $this->assertSame(0, DB::table('rental_lead_sources')->where('name', 'حملة اختبار الإيجار')->where('is_active', true)->count());
        $this->assertSame(0, DB::table('resale_lead_sources')->where('name', 'حملة اختبار إعادة البيع')->where('is_active', true)->count());
        $this->assertSame(1, DB::table('rental_lead_sources')->where('name', 'حملة اختبار إعادة البيع')->where('is_active', true)->count());
        $this->assertSame(1, DB::table('resale_lead_sources')->where('name', 'حملة اختبار الإيجار')->where('is_active', true)->count());
    }
}
