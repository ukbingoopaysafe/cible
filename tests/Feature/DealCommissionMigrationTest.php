<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\RentalArea;
use App\Models\ResaleArea;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\DealService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DealCommissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_can_roll_back_and_reapply_without_changing_existing_values(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);

        foreach ([Department::Rental, Department::Resale] as $department) {
            $deal = $this->createDeal($manager, $department);
            $table = $department->value.'_deal_commissions';

            $this->insertCommission($table, $deal->id, 125.50);
        }

        $migration = require database_path('migrations/2026_10_26_000000_make_deal_commission_agreed_values_nullable.php');
        $migration->down();

        foreach (['rental', 'resale'] as $section) {
            $this->assertFalse($this->agreedValueIsNullable("{$section}_deal_commissions"));
        }

        $migration->up();

        foreach (['rental', 'resale'] as $section) {
            $table = "{$section}_deal_commissions";

            $this->assertTrue($this->agreedValueIsNullable($table));
            $this->assertDatabaseHas($table, ['agreed_value' => 125.50]);
        }
    }

    public function test_migration_refuses_rollback_without_modifying_values_when_null_commissions_exist(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $rentalDeal = $this->createDeal($manager, Department::Rental);
        $resaleDeal = $this->createDeal($manager, Department::Resale);
        $this->insertCommission('rental_deal_commissions', $rentalDeal->id, 50);
        $this->insertCommission('resale_deal_commissions', $resaleDeal->id, null);

        $migration = require database_path('migrations/2026_10_26_000000_make_deal_commission_agreed_values_nullable.php');

        try {
            $migration->down();
            $this->fail('Rollback must refuse to discard nullable commission values.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('resale_deal_commissions: 1', $exception->getMessage());
        }

        foreach (['rental', 'resale'] as $section) {
            $this->assertTrue($this->agreedValueIsNullable("{$section}_deal_commissions"));
        }

        $this->assertDatabaseHas('rental_deal_commissions', ['agreed_value' => 50]);
        $this->assertDatabaseHas('resale_deal_commissions', ['agreed_value' => null]);
    }

    private function createDeal(User $manager, Department $department): object
    {
        $customer = app(CustomerService::class)->create($manager, $department, [
            'name' => 'عميل اختبار الترحيل',
            'phone' => $department === Department::Rental ? '01012345671' : '01012345672',
            'phone_country' => 'EG',
        ]);
        $areaId = $department === Department::Rental
            ? RentalArea::query()->firstOrFail()->id
            : ResaleArea::query()->firstOrFail()->id;
        $request = app(RequestService::class)->create($manager, $department, [
            'customer_id' => $customer->id,
            'area_id' => $areaId,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);

        app(RequestService::class)->close($manager, $department, $request->id, ['outcome' => 'successful']);

        $attributes = [
            'deal_date' => now()->toDateString(),
            'agreed_total_value' => 120000,
            'currency' => 'EGP',
        ];

        if ($department === Department::Rental) {
            $attributes += [
                'rental_term' => 'monthly',
                'rental_term_count' => 1,
                'payment_schedule' => 'monthly',
            ];
        }

        return app(DealService::class)->createFromRequest($manager, $department, $request->id, $attributes);
    }

    private function insertCommission(string $table, int $dealId, ?float $agreedValue): void
    {
        DB::table($table)->insert([
            'deal_id' => $dealId,
            'payer' => 'الشركة',
            'recipient' => 'الوسيط',
            'calculation_type' => 'fixed',
            'agreed_value' => $agreedValue,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function agreedValueIsNullable(string $table): bool
    {
        foreach (Schema::getColumns($table) as $column) {
            if ($column['name'] === 'agreed_value') {
                return $column['nullable'];
            }
        }

        $this->fail("The agreed_value column is missing from {$table}.");
    }
}
