<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\RentalArea;
use App\Models\User;
use App\Services\BrokerService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BrokerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_broker_phone_is_unique_per_department_and_similar_names_need_review(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(BrokerService::class);
        $rentalBroker = $service->create($manager, Department::Rental, [
            'name' => 'محمد أحمد',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $resaleBroker = $service->create($manager, Department::Resale, [
            'name' => 'محمد أحمد',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);

        $this->assertSame('+201012345678', $rentalBroker->phone_e164);
        $this->assertSame('rental_brokers', $rentalBroker->getTable());
        $this->assertSame('resale_brokers', $resaleBroker->getTable());

        try {
            $service->create($manager, Department::Rental, [
                'name' => 'محمد احمد',
                'phone' => '01011112222',
                'phone_country' => 'EG',
            ]);
            $this->fail('Similar broker names must be reviewed before creation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_brokers', 1);
        }

        $reviewed = $service->create($manager, Department::Rental, [
            'name' => 'محمد احمد',
            'phone' => '01011112222',
            'phone_country' => 'EG',
        ], reviewedSimilarNames: true);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'broker.created',
            'subject_id' => $reviewed->id,
            'department' => Department::Rental->value,
        ]);
    }

    public function test_broker_with_active_request_cannot_be_deleted(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $broker = app(BrokerService::class)->create($manager, Department::Rental, [
            'name' => 'وسيط نشط',
        ]);
        $request = app(RequestService::class)->create($manager, Department::Rental, [
            'broker_id' => $broker->id,
            'contact_party' => 'broker',
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);

        try {
            app(BrokerService::class)->delete($manager, Department::Rental, $broker->id, 'طلب حذف للاختبار');
            $this->fail('A broker with an active request must not be deleted.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('rental_brokers', ['id' => $broker->id]);
            $this->assertDatabaseHas('rental_requests', ['id' => $request->id, 'broker_id' => $broker->id]);
        }
    }
}
