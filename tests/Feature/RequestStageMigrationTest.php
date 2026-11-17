<?php

namespace Tests\Feature;

use App\Models\RentalArea;
use App\Models\ResaleArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequestStageMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_alignment_only_converts_the_two_retired_stages_and_changes_the_default(): void
    {
        $migrationPath = database_path('migrations/2026_11_16_020000_align_rental_and_resale_request_stages.php');
        $this->assertFileExists($migrationPath);

        $owner = User::factory()->create(['is_company_manager' => true]);
        $cases = [
            'intake' => 'unit_search',
            'qualification' => 'unit_search',
            'unit_search' => 'unit_search',
            'recommendation' => 'recommendation',
            'viewing' => 'viewing',
            'negotiation' => 'negotiation',
            'legacy_stage' => 'legacy_stage',
        ];

        foreach ([
            ['table' => 'rental_requests', 'area_id' => RentalArea::query()->firstOrFail()->id],
            ['table' => 'resale_requests', 'area_id' => ResaleArea::query()->firstOrFail()->id],
        ] as $department) {
            foreach ($cases as $stage => $expected) {
                $id = $this->insertRequest($department['table'], $department['area_id'], $owner->id, $stage);
                $ids[$department['table']][$stage] = $id;
            }
        }

        $migration = require $migrationPath;
        $migration->up();

        foreach ($cases as $stage => $expected) {
            foreach (['rental_requests', 'resale_requests'] as $table) {
                $this->assertSame($expected, DB::table($table)->where('id', $ids[$table][$stage])->value('stage'));
            }
        }

        foreach ([
            ['table' => 'rental_requests', 'area_id' => RentalArea::query()->firstOrFail()->id],
            ['table' => 'resale_requests', 'area_id' => ResaleArea::query()->firstOrFail()->id],
        ] as $department) {
            $id = $this->insertRequest($department['table'], $department['area_id'], $owner->id, null);
            $this->assertSame('unit_search', DB::table($department['table'])->where('id', $id)->value('stage'));
        }
    }

    private function insertRequest(string $table, int $areaId, int $ownerId, ?string $stage): int
    {
        $record = [
            'area_id' => $areaId,
            'owner_user_id' => $ownerId,
            'contact_party' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($stage !== null) {
            $record['stage'] = $stage;
        }

        return (int) DB::table($table)->insertGetId($record);
    }
}
