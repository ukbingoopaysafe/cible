<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\FinishingLevel;
use App\Enums\RequestUnitType;
use App\Filament\Resources\RentalAreas\Pages\ListRentalAreas;
use App\Filament\Resources\RentalBrokers\Pages\ListRentalBrokers;
use App\Filament\Resources\RentalCloseReasons\Pages\ListRentalCloseReasons;
use App\Filament\Resources\RentalCustomers\Pages\ListRentalCustomers;
use App\Filament\Resources\RentalLeadSources\Pages\ListRentalLeadSources;
use App\Filament\Resources\RentalRequests\Pages\CreateRentalRequest;
use App\Filament\Resources\RentalRequests\Pages\EditRentalRequest;
use App\Filament\Resources\RentalRequests\Pages\ListRentalRequests;
use App\Filament\Resources\ResaleRequests\Pages\CreateResaleRequest;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Models\RentalAftercare;
use App\Models\RentalArea;
use App\Models\RentalCustomer;
use App\Models\RentalRecommendation;
use App\Models\RentalRequest;
use App\Models\RentalViewing;
use App\Models\ResaleArea;
use App\Models\User;
use App\Services\BrokerService;
use App\Services\CustomerService;
use App\Services\DealService;
use App\Services\RequestService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentUiAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_manager_can_render_every_primary_operational_page(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $rentalRequest = $this->createRentalRequest($manager);
        $resaleRequest = $this->createResaleRequest($manager);

        $paths = [
            '/admin',
            '/admin/staff',
            '/admin/rental-customers',
            '/admin/rental-brokers',
            '/admin/rental-lead-sources',
            '/admin/rental-close-reasons',
            '/admin/rental-audit-events',
            '/admin/rental-requests',
            '/admin/rental-requests/create',
            "/admin/rental-requests/{$rentalRequest->id}",
            "/admin/rental-requests/{$rentalRequest->id}/edit",
            '/admin/resale-customers',
            '/admin/resale-brokers',
            '/admin/resale-areas',
            '/admin/resale-lead-sources',
            '/admin/resale-close-reasons',
            '/admin/resale-audit-events',
            '/admin/resale-requests',
            '/admin/resale-requests/create',
            "/admin/resale-requests/{$resaleRequest->id}",
            "/admin/resale-requests/{$resaleRequest->id}/edit",
        ];

        foreach ($paths as $path) {
            $this->actingAs($manager)->get($path)->assertOk();
        }
    }

    public function test_rental_employee_can_render_rental_pages_and_cannot_open_resale_or_staff_pages(): void
    {
        $employee = User::factory()->create(['is_company_manager' => false]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);
        $request = $this->createRentalRequest($employee);
        $manager = User::factory()->create(['is_company_manager' => true]);
        $resaleRequest = $this->createResaleRequest($manager);

        foreach ([
            '/admin',
            '/admin/rental-customers',
            '/admin/rental-brokers',
            '/admin/rental-audit-events',
            '/admin/rental-requests',
            '/admin/rental-requests/create',
            "/admin/rental-requests/{$request->id}",
            "/admin/rental-requests/{$request->id}/edit",
        ] as $path) {
            $this->actingAs($employee)->get($path)->assertOk();
        }

        foreach ([
            '/admin/staff',
            '/admin/rental-areas',
            '/admin/rental-lead-sources',
            '/admin/rental-close-reasons',
            '/admin/resale-customers',
            '/admin/resale-brokers',
            '/admin/resale-areas',
            '/admin/resale-lead-sources',
            '/admin/resale-close-reasons',
            '/admin/resale-audit-events',
            '/admin/resale-requests',
            '/admin/resale-requests/create',
        ] as $path) {
            $this->actingAs($employee)->get($path)->assertForbidden();
        }

        foreach ([
            "/admin/resale-requests/{$resaleRequest->id}",
            "/admin/resale-requests/{$resaleRequest->id}/edit",
        ] as $path) {
            $this->assertContains($this->actingAs($employee)->get($path)->status(), [403, 404]);
        }
    }

    public function test_manager_can_create_and_edit_a_rental_request_through_the_filament_forms(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل نموذج الواجهة',
            'phone' => '01012345553',
            'phone_country' => 'EG',
        ]);
        $area = RentalArea::query()->firstOrFail();

        Livewire::actingAs($manager)
            ->test(CreateRentalRequest::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'area_id' => $area->id,
                'owner_user_id' => $manager->id,
                'next_follow_up_at' => now()->addDay()->toDateTimeString(),
                'unit_type' => RequestUnitType::Other->value,
                'unit_type_other' => 'دوبلكس',
                'finishing' => FinishingLevel::Other->value,
                'finishing_other' => 'تشطيب خاص',
                'budget_declared' => 22000,
                'budget_currency' => 'EGP',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $request = RentalRequest::query()->sole();

        Livewire::actingAs($manager)
            ->test(EditRentalRequest::class, ['record' => $request->id])
            ->fillForm([
                'other_requirements' => 'مصعد وموقف سيارة',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('rental_requests', [
            'id' => $request->id,
            'unit_type_other' => 'دوبلكس',
            'finishing_other' => 'تشطيب خاص',
            'other_requirements' => 'مصعد وموقف سيارة',
        ]);
    }

    public function test_manager_can_create_a_resale_request_through_the_filament_form(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $customer = app(CustomerService::class)->create($manager, Department::Resale, [
            'name' => 'مشتري نموذج الواجهة',
            'phone' => '01012345554',
            'phone_country' => 'EG',
        ]);

        Livewire::actingAs($manager)
            ->test(CreateResaleRequest::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'area_id' => ResaleArea::query()->firstOrFail()->id,
                'owner_user_id' => $manager->id,
                'next_follow_up_at' => now()->addDay()->toDateTimeString(),
                'budget_declared' => 3500000,
                'budget_targeted' => 3200000,
                'budget_currency' => 'EGP',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('resale_requests', [
            'customer_id' => $customer->id,
            'budget_declared' => 3500000,
            'budget_targeted' => 3200000,
            'budget_currency' => 'EGP',
        ]);
    }

    public function test_manager_can_run_the_rental_request_lifecycle_through_table_actions(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $request = $this->createRentalRequest($manager);
        $page = Livewire::actingAs($manager)->test(ListRentalRequests::class);

        $page->callAction(TestAction::make('changeStage')->table($request), [
            'stage' => 'qualification',
            'notes' => 'تمت مراجعة الاحتياج',
        ])->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertSame('qualification', $request->stage->value);

        $page->callAction(TestAction::make('recordContact')->table($request), [
            'channel' => 'phone',
            'occurred_at' => now()->subMinute()->toDateTimeString(),
            'result' => 'تم التواصل مع العميل',
            'next_action' => 'إرسال الترشيحات',
        ])->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('rental_activities', [
            'rental_request_id' => $request->id,
            'channel' => 'phone',
            'result' => 'تم التواصل مع العميل',
        ]);

        $page->callAction(TestAction::make('pause')->table($request))
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertSame('paused', $request->status->value);

        $page->callAction(TestAction::make('resume')->table($request), [
            'next_follow_up_at' => now()->addDays(2)->toDateTimeString(),
            'notes' => 'عاد العميل للمتابعة',
        ])->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertSame('active', $request->status->value);

        $page->callAction(TestAction::make('close')->table($request), [
            'outcome' => 'unsuccessful',
            'outcome_reason_text' => 'تأجلت خطة الاستئجار',
        ])->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('rental_requests', [
            'id' => $request->id,
            'status' => 'closed',
            'outcome' => 'unsuccessful',
            'outcome_reason_text' => 'تأجلت خطة الاستئجار',
        ]);
    }

    public function test_manager_can_create_a_rental_deal_and_aftercare_through_the_request_action(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $request = $this->createRentalRequest($manager);
        $page = Livewire::actingAs($manager)->test(ListRentalRequests::class);

        $page->callAction(TestAction::make('close')->table($request), [
            'outcome' => 'successful',
        ])->assertHasNoTableActionErrors();

        $request->refresh();
        $page->callAction(TestAction::make('createDeal')->table($request), [
            'deal_date' => now()->toDateString(),
            'agreed_total_value' => 180000,
            'currency' => 'EGP',
            'rental_term' => 'yearly',
            'rental_term_count' => 1,
            'payment_schedule' => 'monthly',
            'commissions' => [[
                'payer' => 'الشركة',
                'recipient' => 'الوسيط',
                'calculation_type' => 'percentage',
                'agreed_value' => 120,
            ]],
        ])->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('rental_deals', [
            'request_id' => $request->id,
            'agreed_total_value' => 180000,
            'rental_term' => 'yearly',
            'payment_schedule' => 'monthly',
        ]);
        $this->assertDatabaseHas('rental_deal_commissions', [
            'calculation_type' => 'percentage',
            'agreed_value' => 120,
        ]);
        $this->assertDatabaseHas('rental_aftercare', [
            'customer_id' => $request->customer_id,
            'owner_user_id' => $manager->id,
        ]);
    }

    public function test_manager_can_record_an_aftercare_activity_through_the_customer_action(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل ما بعد الصفقة',
            'phone' => '01012345556',
            'phone_country' => 'EG',
        ]);
        $request = app(RequestService::class)->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        app(RequestService::class)->close($manager, Department::Rental, $request->id, ['outcome' => 'successful']);
        app(DealService::class)->createFromRequest($manager, Department::Rental, $request->id, [
            'deal_date' => now()->toDateString(),
            'agreed_total_value' => 180000,
            'currency' => 'EGP',
            'rental_term' => 'monthly',
            'rental_term_count' => 1,
            'payment_schedule' => 'monthly',
        ]);

        Livewire::actingAs($manager)
            ->test(ListRentalCustomers::class)
            ->callAction(TestAction::make('recordAftercareActivity')->table($customer), [
                'channel' => 'whatsapp',
                'occurred_at' => now()->subMinute()->toDateTimeString(),
                'result' => 'تم الاطمئنان على العميل',
                'next_follow_up_at' => now()->addMonth()->toDateTimeString(),
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('rental_aftercare_activities', [
            'aftercare_id' => RentalAftercare::query()->where('customer_id', $customer->id)->sole()->id,
            'channel' => 'whatsapp',
            'result' => 'تم الاطمئنان على العميل',
        ]);
    }

    public function test_manager_can_complete_the_recommendation_and_viewing_workflow_through_request_actions(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $request = $this->createRentalRequest($manager);
        $page = Livewire::actingAs($manager)->test(ListRentalRequests::class);

        $page->callAction(TestAction::make('createRecommendation')->table($request), [
            'unit_code' => 'UNIT-UI-101',
            'unit_type' => 'شقة',
            'unit_area' => 120,
            'location_description' => 'التجمع الخامس',
            'asking_price' => 25000,
            'currency' => 'EGP',
        ])->assertHasNoTableActionErrors();

        $recommendation = RentalRecommendation::query()->sole();
        $page->callAction(TestAction::make('changeRecommendationStatus')->table($request), [
            'recommendation_id' => $recommendation->id,
            'status' => 'shared',
        ])->assertHasNoTableActionErrors();

        $page->callAction(TestAction::make('recordFieldInspection')->table($request), [
            'recommendation_id' => $recommendation->id,
            'field_representative_name' => 'مندوب الفحص',
            'inspected_at' => now()->subMinute()->toDateTimeString(),
            'photo_match' => 'matches',
            'observed_defects' => 'لا توجد عيوب ظاهرة',
        ])->assertHasNoTableActionErrors();

        $page->callAction(TestAction::make('scheduleViewing')->table($request), [
            'recommendation_id' => $recommendation->id,
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
            'field_representative_name' => 'مندوب المعاينة',
        ])->assertHasNoTableActionErrors();

        $viewing = RentalViewing::query()->sole();
        $page->callAction(TestAction::make('recordViewingResult')->table($request), [
            'viewing_id' => $viewing->id,
            'result' => 'interested',
            'customer_feedback' => 'الوحدة مناسبة للعميل',
        ])->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('rental_recommendations', [
            'id' => $recommendation->id,
            'status' => 'shared',
        ]);
        $this->assertDatabaseHas('rental_viewings', [
            'id' => $viewing->id,
            'result' => 'interested',
        ]);
    }

    public function test_employee_can_request_and_manager_can_approve_reassignment_through_request_actions(): void
    {
        $employee = User::factory()->create(['is_company_manager' => false]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);
        $target = User::factory()->create(['is_company_manager' => false]);
        $target->departmentMemberships()->create(['department' => Department::Rental]);
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $request = $this->createRentalRequest($employee);

        Livewire::actingAs($employee)
            ->test(ListRentalRequests::class)
            ->callAction(TestAction::make('requestReassignment')->table($request), [
                'target_user_id' => $target->id,
                'reason' => 'العميل يفضل متابعة الموظف الآخر',
            ])
            ->assertHasNoTableActionErrors();

        Livewire::actingAs($manager)
            ->test(ListRentalRequests::class)
            ->callAction(TestAction::make('approveReassignment')->table($request))
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('rental_requests', [
            'id' => $request->id,
            'owner_user_id' => $target->id,
        ]);
        $this->assertDatabaseHas('rental_request_reassignments', [
            'request_id' => $request->id,
            'requested_by' => $employee->id,
            'to_user_id' => $target->id,
            'status' => 'approved',
        ]);
    }

    public function test_manager_can_create_staff_customer_and_broker_through_their_list_actions(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        Livewire::actingAs($manager)
            ->test(ListStaff::class)
            ->callAction(TestAction::make('createEmployee'), [
                'name' => 'موظف واجهة',
                'email' => 'ui-staff@example.test',
                'password' => 'StrongPass123!',
                'departments' => [Department::Rental->value],
                'can_add_rental_areas' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'موظف واجهة',
            'email' => 'ui-staff@example.test',
            'can_add_rental_areas' => true,
        ]);

        Livewire::actingAs($manager)
            ->test(ListRentalCustomers::class)
            ->callAction(TestAction::make('createCustomer'), [
                'name' => 'عميل قائمة الواجهة',
                'phone' => '01012345557',
                'phone_country' => 'EG',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('rental_customers', ['name' => 'عميل قائمة الواجهة']);

        Livewire::actingAs($manager)
            ->test(ListRentalBrokers::class)
            ->callAction(TestAction::make('createBroker'), [
                'name' => 'وسيط قائمة الواجهة',
                'phone' => '01012345558',
                'phone_country' => 'EG',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('rental_brokers', ['name' => 'وسيط قائمة الواجهة']);
    }

    public function test_manager_can_manage_rental_reference_lists_through_list_actions(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        Livewire::actingAs($manager)
            ->test(ListRentalAreas::class)
            ->callAction(TestAction::make('createArea'), [
                'governorate' => 'القاهرة',
                'name' => 'منطقة اختبار الواجهة',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('rental_areas', [
            'governorate' => 'القاهرة',
            'name' => 'منطقة اختبار الواجهة',
        ]);

        Livewire::actingAs($manager)
            ->test(ListRentalLeadSources::class)
            ->callAction(TestAction::make('addLeadSource'), ['name' => 'مورد اختبار الواجهة'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('rental_lead_sources', ['name' => 'مورد اختبار الواجهة']);

        Livewire::actingAs($manager)
            ->test(ListRentalCloseReasons::class)
            ->callAction(TestAction::make('addCloseReason'), ['name' => 'تصنيف اختبار الواجهة'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('rental_request_close_reasons', ['name' => 'تصنيف اختبار الواجهة']);
    }

    public function test_manager_can_archive_and_restore_a_customer_and_area_through_list_actions(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل الأرشفة من الواجهة',
            'phone' => '01012345559',
            'phone_country' => 'EG',
        ]);
        $area = RentalArea::query()->firstOrFail();

        Livewire::actingAs($manager)
            ->test(ListRentalCustomers::class)
            ->callAction(TestAction::make('archiveCustomer')->table($customer))
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted('rental_customers', ['id' => $customer->id]);

        $archivedCustomer = RentalCustomer::withTrashed()->findOrFail($customer->id);
        Livewire::actingAs($manager)
            ->test(ListRentalCustomers::class)
            ->filterTable('trashed', false)
            ->callAction(TestAction::make('restoreCustomer')->table($archivedCustomer))
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('rental_customers', ['id' => $customer->id, 'deleted_at' => null]);

        Livewire::actingAs($manager)
            ->test(ListRentalAreas::class)
            ->callAction(TestAction::make('archiveArea')->table($area))
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted('rental_areas', ['id' => $area->id]);

        $archivedArea = RentalArea::withTrashed()->findOrFail($area->id);
        Livewire::actingAs($manager)
            ->test(ListRentalAreas::class)
            ->filterTable('trashed', false)
            ->callAction(TestAction::make('restoreArea')->table($archivedArea))
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('rental_areas', ['id' => $area->id, 'deleted_at' => null]);
    }

    public function test_manager_can_create_a_broker_request_then_link_the_customer_through_the_request_action(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $broker = app(BrokerService::class)->create($manager, Department::Rental, [
            'name' => 'وسيط طلب الواجهة',
            'phone' => '01012345560',
            'phone_country' => 'EG',
        ]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل ربط الواجهة',
            'phone' => '01012345561',
            'phone_country' => 'EG',
        ]);

        Livewire::actingAs($manager)
            ->test(CreateRentalRequest::class)
            ->fillForm([
                'broker_id' => $broker->id,
                'area_id' => RentalArea::query()->firstOrFail()->id,
                'owner_user_id' => $manager->id,
                'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $request = RentalRequest::query()->sole();
        $this->assertNull($request->customer_id);
        $this->assertSame($broker->id, $request->broker_id);

        Livewire::actingAs($manager)
            ->test(ListRentalRequests::class)
            ->callAction(TestAction::make('linkCustomer')->table($request), [
                'customer_id' => $customer->id,
                'make_contact' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('rental_requests', [
            'id' => $request->id,
            'customer_id' => $customer->id,
            'broker_id' => $broker->id,
            'contact_party' => 'customer',
        ]);
    }

    private function createRentalRequest(User $actor): object
    {
        $customer = app(CustomerService::class)->create($actor, Department::Rental, [
            'name' => 'عميل اختبار الواجهة',
            'phone' => $actor->is_company_manager ? '01012345551' : '01012345552',
            'phone_country' => 'EG',
        ]);

        return app(RequestService::class)->create($actor, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
    }

    private function createResaleRequest(User $actor): object
    {
        $customer = app(CustomerService::class)->create($actor, Department::Resale, [
            'name' => 'مشتري عرض الواجهة',
            'phone' => '01012345555',
            'phone_country' => 'EG',
        ]);

        return app(RequestService::class)->create($actor, Department::Resale, [
            'customer_id' => $customer->id,
            'area_id' => ResaleArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
    }
}
