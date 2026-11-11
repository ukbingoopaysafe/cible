<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\RentalArea;
use App\Models\RentalBroker;
use App\Models\RentalFieldInspection;
use App\Models\RentalRecommendation;
use App\Models\ResaleArea;
use App\Models\ResaleBroker;
use App\Models\ResaleFieldInspection;
use App\Models\ResaleRecommendation;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InspectionSnapshotMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rental_and_resale_inspection_tables_have_nullable_contact_snapshot_columns(): void
    {
        foreach (['rental', 'resale'] as $department) {
            $table = $department.'_field_inspections';
            $columns = collect(Schema::getColumns($table))->keyBy('name');

            foreach ([
                'contact_role',
                'contact_name',
                'contact_phone_e164',
                'contact_phone_country',
                'linked_broker_id',
            ] as $columnName) {
                $this->assertArrayHasKey($columnName, $columns->all(), "{$table}.{$columnName} must exist.");
                $this->assertTrue($columns[$columnName]['nullable'], "{$table}.{$columnName} must remain nullable for historical rows.");
            }

            $this->assertArrayHasKey('observed_defects', $columns->all(), "{$table}.observed_defects must remain available for history.");
        }
    }

    public function test_each_inspection_model_links_to_its_own_department_broker(): void
    {
        $rentalInspection = new RentalFieldInspection;
        $resaleInspection = new ResaleFieldInspection;

        $this->assertTrue(method_exists($rentalInspection, 'linkedBroker'));
        $this->assertTrue(method_exists($resaleInspection, 'linkedBroker'));

        $rentalBroker = $rentalInspection->linkedBroker();
        $resaleBroker = $resaleInspection->linkedBroker();

        $this->assertInstanceOf(BelongsTo::class, $rentalBroker);
        $this->assertInstanceOf(BelongsTo::class, $resaleBroker);
        $this->assertInstanceOf(RentalBroker::class, $rentalBroker->getRelated());
        $this->assertInstanceOf(ResaleBroker::class, $resaleBroker->getRelated());
        $this->assertSame('linked_broker_id', $rentalBroker->getForeignKeyName());
        $this->assertSame('linked_broker_id', $resaleBroker->getForeignKeyName());
    }

    public function test_migration_rollback_refuses_to_drop_saved_contacts_or_legacy_defects(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $rentalCustomer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل الإيجار',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $rentalRequest = app(RequestService::class)->create($manager, Department::Rental, [
            'customer_id' => $rentalCustomer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $rentalRecommendation = RentalRecommendation::query()->create([
            'request_id' => $rentalRequest->id,
            'unit_code' => 'RENTAL-ROLLBACK-1',
            'currency' => 'EGP',
            'status' => 'shared',
            'created_by' => $manager->id,
        ]);
        $rentalInspection = RentalFieldInspection::query()->create([
            'recommendation_id' => $rentalRecommendation->id,
            'field_representative_name' => 'مندوب الإيجار',
            'inspected_at' => now()->subMinute(),
            'photo_match' => 'matches',
            'observed_defects' => 'عيب إيجار قديم',
            'contact_role' => 'owner',
            'contact_name' => 'مالك الإيجار',
            'contact_phone_e164' => '+201012345678',
            'contact_phone_country' => 'EG',
            'recorded_by' => $manager->id,
        ]);

        $resaleCustomer = app(CustomerService::class)->create($manager, Department::Resale, [
            'name' => 'عميل إعادة البيع',
            'phone' => '01012345679',
            'phone_country' => 'EG',
        ]);
        $resaleRequest = app(RequestService::class)->create($manager, Department::Resale, [
            'customer_id' => $resaleCustomer->id,
            'area_id' => ResaleArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $resaleRecommendation = ResaleRecommendation::query()->create([
            'request_id' => $resaleRequest->id,
            'unit_code' => 'RESALE-ROLLBACK-1',
            'currency' => 'EGP',
            'status' => 'shared',
            'created_by' => $manager->id,
        ]);
        $resaleInspection = ResaleFieldInspection::query()->create([
            'recommendation_id' => $resaleRecommendation->id,
            'field_representative_name' => 'مندوب إعادة البيع',
            'inspected_at' => now()->subMinute(),
            'photo_match' => 'matches',
            'observed_defects' => 'عيب إعادة بيع قديم',
            'contact_role' => 'broker',
            'contact_name' => 'وسيط إعادة البيع',
            'contact_phone_e164' => '+201012345679',
            'contact_phone_country' => 'EG',
            'recorded_by' => $manager->id,
        ]);

        $migration = require database_path('migrations/2026_11_10_000000_add_contact_snapshots_to_section_field_inspections.php');

        $rollbackException = null;

        try {
            $migration->down();
        } catch (\RuntimeException $exception) {
            $rollbackException = $exception;
        }

        $this->assertNotNull($rollbackException, 'Rollback must refuse to drop contact snapshot fields.');
        $this->assertStringContainsString('Refusing to remove apartment inspection contact snapshots', $rollbackException->getMessage());

        $this->assertDatabaseHas('rental_field_inspections', [
            'id' => $rentalInspection->id,
            'contact_name' => 'مالك الإيجار',
            'contact_phone_e164' => '+201012345678',
            'observed_defects' => 'عيب إيجار قديم',
        ]);
        $this->assertDatabaseHas('resale_field_inspections', [
            'id' => $resaleInspection->id,
            'contact_name' => 'وسيط إعادة البيع',
            'contact_phone_e164' => '+201012345679',
            'observed_defects' => 'عيب إعادة بيع قديم',
        ]);
    }
}
