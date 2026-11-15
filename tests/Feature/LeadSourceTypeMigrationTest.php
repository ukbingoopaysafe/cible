<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadSourceTypeMigrationTest extends TestCase
{
    private const CONNECTION = 'lead_source_migration_fixture';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge(self::CONNECTION);

        foreach (['rental', 'resale'] as $department) {
            $sourceTable = "{$department}_lead_sources";
            $requestTable = "{$department}_requests";

            Schema::connection(self::CONNECTION)->create($sourceTable, function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100)->unique();
                $table->boolean('is_other')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
            Schema::connection(self::CONNECTION)->create($requestTable, function (Blueprint $table) use ($sourceTable): void {
                $table->id();
                $table->foreignId('lead_source_id')->nullable()->constrained($sourceTable)->restrictOnDelete();
                $table->timestamps();
            });

            $rows = [
                [1, 'أخرى', true, false],
                [2, 'مكالمة هاتفية', false, true],
                [3, 'زيارة مباشرة', false, false],
                [4, 'إحالة من موظف', false, false],
                [5, 'إحالة من عميل أو معرفة', false, true],
                [6, 'وسائل التواصل الاجتماعي', false, false],
                [7, 'إعلان إلكتروني', false, true],
                [8, 'الموقع الإلكتروني', false, true],
                [9, 'واتساب', false, true],
                [10, 'مصدر أضافه المدير', false, true],
            ];

            if ($department === 'rental') {
                $rows[] = [11, 'الإدارة العليا', false, false];
            }

            foreach ($rows as [$id, $name, $isOther, $isActive]) {
                DB::connection(self::CONNECTION)->table($sourceTable)->insert([
                    'id' => $id,
                    'name' => $name,
                    'is_other' => $isOther,
                    'is_active' => $isActive,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::connection(self::CONNECTION)->table($requestTable)->insert([
                'id' => 25,
                'lead_source_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        parent::tearDown();
    }

    public function test_migration_types_sources_without_changing_ids_or_existing_request_links(): void
    {
        $migration = $this->migration();
        $migration->up();

        $expectedTypes = [
            1 => 'other',
            2 => 'legacy_contact_method',
            3 => 'legacy_contact_method',
            4 => 'person_referral',
            5 => 'person_referral',
            6 => 'awareness',
            7 => 'awareness',
            8 => 'awareness',
            9 => 'legacy_contact_method',
            10 => 'unclassified_legacy',
        ];

        foreach (['rental', 'resale'] as $department) {
            $sourceTable = "{$department}_lead_sources";
            $requestTable = "{$department}_requests";
            $sources = DB::connection(self::CONNECTION)->table($sourceTable)->orderBy('id')->get()->keyBy('id');

            foreach ($expectedTypes as $id => $type) {
                $this->assertSame($type, $sources[$id]->type, "Unexpected source type for {$sourceTable} #{$id}.");
            }

            $this->assertTrue((bool) $sources[1]->is_other, 'The migration must not rewrite is_other.');
            $this->assertTrue((bool) $sources[1]->is_active, 'The built-in «أخرى» source must remain available.');
            $this->assertFalse((bool) $sources[2]->is_active);
            $this->assertFalse((bool) $sources[3]->is_active);
            $this->assertFalse((bool) $sources[9]->is_active);
            $this->assertFalse((bool) $sources[10]->is_active);
            $this->assertFalse((bool) $sources[4]->is_active, 'Known rows retain their prior activation state.');
            $this->assertTrue((bool) $sources[5]->is_active);
            $this->assertSame(2, (int) DB::connection(self::CONNECTION)->table($requestTable)->where('id', 25)->value('lead_source_id'));
            $this->assertNull(DB::connection(self::CONNECTION)->table($requestTable)->where('id', 25)->value('lead_source_referrer_name'));
            $this->assertNull(DB::connection(self::CONNECTION)->table($requestTable)->where('id', 25)->value('lead_source_referrer_unknown'));

            $management = DB::connection(self::CONNECTION)->table($sourceTable)->where('name', 'الإدارة العليا')->get();
            $this->assertCount(1, $management);
            $this->assertSame('person_referral', $management->first()->type);
            $this->assertTrue((bool) $management->first()->is_active);
        }

        $this->assertSame(11, (int) DB::connection(self::CONNECTION)->table('rental_lead_sources')->where('name', 'الإدارة العليا')->value('id'));
    }

    public function test_migration_refuses_rollback_to_preserve_classification_and_referral_history(): void
    {
        $migration = $this->migration();
        $migration->up();

        try {
            $migration->down();
            $this->fail('The typed source and referral snapshot migration must be forward-only.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Refusing to remove lead source classifications or referral snapshots', $exception->getMessage());
        }
    }

    private function migration(): object
    {
        $migration = require database_path('migrations/2026_11_12_000000_add_typed_lead_sources_and_referrers.php');
        (new \ReflectionProperty($migration, 'connection'))->setValue($migration, self::CONNECTION);

        return $migration;
    }
}
