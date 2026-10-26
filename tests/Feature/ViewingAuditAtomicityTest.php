<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\RentalArea;
use App\Models\RentalRequest;
use App\Models\RentalViewing;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ViewingAuditAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_result_update_rolls_back_when_audit_write_fails(): void
    {
        $employee = User::factory()->create(['is_company_manager' => false]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);
        $area = RentalArea::query()->firstOrFail();
        $request = RentalRequest::query()->create([
            'area_id' => $area->getKey(),
            'owner_user_id' => $employee->getKey(),
            'contact_party' => 'broker',
            'status' => 'active',
            'stage' => 'intake',
            'next_follow_up_at' => now()->addDay(),
        ]);
        $viewing = RentalViewing::query()->create([
            'request_id' => $request->getKey(),
            'scheduled_at' => now()->addDays(2),
            'recorded_by' => $employee->getKey(),
        ]);

        DB::listen(function (QueryExecuted $query): void {
            if (str_contains(strtolower($query->sql), 'audit_events')
                && str_starts_with(strtolower(ltrim($query->sql)), 'insert')) {
                throw new QueryException(
                    $query->connectionName,
                    $query->sql,
                    $query->bindings,
                    new \RuntimeException('Simulated audit insert failure.'),
                );
            }
        });

        try {
            app(RecommendationService::class)->recordViewingResult($employee, Department::Rental, (int) $viewing->getKey(), [
                'result' => 'interested',
            ]);
            $this->fail('The audit insert should fail after the audit table is removed.');
        } catch (QueryException) {
            $this->assertDatabaseHas('rental_viewings', [
                'id' => $viewing->getKey(),
                'result' => null,
            ]);
        }
    }
}
