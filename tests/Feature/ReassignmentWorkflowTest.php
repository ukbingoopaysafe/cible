<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\RentalArea;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReassignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_approval_changes_owner_and_preserves_requested_history(): void
    {
        $requester = User::factory()->create(['is_company_manager' => false]);
        $requester->departmentMemberships()->create(['department' => Department::Rental]);
        $target = User::factory()->create(['is_company_manager' => false]);
        $target->departmentMemberships()->create(['department' => Department::Rental]);
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($requester, Department::Rental, [
            'name' => 'عميل إعادة تعيين',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $request = app(RequestService::class)->create($requester, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $service = app(RequestService::class);

        $pending = $service->requestReassignment($requester, Department::Rental, $request->id, $target->id, 'توزيع العمل');
        $this->assertSame('pending', $pending->status);
        $this->assertSame($requester->id, $request->fresh()->owner_user_id);

        $service->decideReassignment($manager, Department::Rental, $request->id, true);

        $this->assertSame($target->id, $request->fresh()->owner_user_id);
        $this->assertDatabaseHas('rental_request_reassignments', [
            'id' => $pending->id,
            'status' => 'approved',
            'requested_by' => $requester->id,
            'from_user_id' => $requester->id,
            'to_user_id' => $target->id,
            'decided_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'request.reassignment.approved',
            'actor_user_id' => $manager->id,
            'department' => Department::Rental->value,
            'subject_id' => $request->id,
        ]);

        $secondRequest = app(RequestService::class)->create($requester, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $rejected = $service->requestReassignment($requester, Department::Rental, $secondRequest->id, $target->id, 'طلب آخر');
        $service->decideReassignment($manager, Department::Rental, $secondRequest->id, false);

        $this->assertSame($requester->id, $secondRequest->fresh()->owner_user_id);
        $this->assertDatabaseHas('rental_request_reassignments', [
            'id' => $rejected->id,
            'status' => 'rejected',
            'decided_by' => $manager->id,
        ]);
    }
}
