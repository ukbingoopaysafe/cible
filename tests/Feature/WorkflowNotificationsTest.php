<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\RequestStatus;
use App\Models\AuditEvent;
use App\Models\RentalArea;
use App\Models\RentalCustomerFollowUp;
use App\Models\RentalRequest;
use App\Models\ResaleArea;
use App\Models\ResaleRequest;
use App\Models\User;
use App\Models\UserDepartment;
use App\Services\BrokerService;
use App\Services\ContactChangeApprovalService;
use App\Services\CustomerFollowUpService;
use App\Services\CustomerService;
use App\Services\RecommendationService;
use App\Services\RequestService;
use App\Services\WorkflowNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkflowNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_event_key_has_a_prefix_lookup_index(): void
    {
        $indexes = DB::connection()->getSchemaBuilder()->getIndexes('notifications');

        $this->assertNotEmpty(array_filter(
            $indexes,
            static fn (array $index): bool => $index['columns'] === ['workflow_event_key'],
        ));
    }

    public function test_retiring_request_reminders_matches_the_exact_event_prefix(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createRentalRequest($manager, $manager, now()->addDay());
        $exactKey = 'request.follow_up.due:'.RentalRequest::class.':'.$request->id.':20261117100000:owner:'.$manager->id;
        $lookalikeKey = 'request.followXup.due:'.RentalRequest::class.':'.$request->id.':20261117100000:owner:'.$manager->id;

        foreach ([$exactKey, $lookalikeKey] as $key) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'workflow',
                'notifiable_type' => $manager->getMorphClass(),
                'notifiable_id' => $manager->id,
                'data' => '{}',
                'workflow_event_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(WorkflowNotificationService::class)->retireRequestFollowUpReminders($request);

        $this->assertNotNull(DB::table('notifications')->where('workflow_event_key', $exactKey)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('workflow_event_key', $lookalikeKey)->value('read_at'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_request_assignment_is_persisted_and_reprocessing_does_not_duplicate_it(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $creator = User::factory()->create(['is_company_manager' => true]);
        $owner = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createRentalRequest($creator, $owner, now()->addDay());

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $owner->id,
        ]);

        $assignment = DB::table('notifications')
            ->where('notifiable_id', $owner->id)
            ->where('workflow_event_key', 'like', 'request.assignment:%')
            ->sole();

        $this->assertStringContainsString((string) $request->id, $assignment->workflow_event_key);
        $assignmentData = json_decode($assignment->data, true);
        $this->assertSame('filament', $assignmentData['format']);
        $this->assertSame('rental', $assignmentData['workflow']['department']);
        $this->assertSame($owner->id, $assignmentData['workflow']['owner_user_id']);
        $this->assertTrue($assignmentData['actions'][0]['shouldMarkAsRead']);
        $this->assertSame($assignmentData['workflow']['url'], $assignmentData['actions'][0]['url']);

        app(WorkflowNotificationService::class)->notifyAssignment(
            $request,
            AuditEvent::query()->where('event_key', 'request.created')->sole(),
        );

        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'request.assignment:%')->count());
    }

    public function test_assignment_audit_identity_keeps_a_to_b_to_a_transitions_distinct(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $firstOwner = User::factory()->create(['is_company_manager' => false]);
        $secondOwner = User::factory()->create(['is_company_manager' => false]);
        UserDepartment::query()->create(['user_id' => $firstOwner->id, 'department' => Department::Rental]);
        UserDepartment::query()->create(['user_id' => $secondOwner->id, 'department' => Department::Rental]);
        $request = $this->createRentalRequest($manager, $firstOwner, now()->addDay());
        $service = app(RequestService::class);

        $service->reassignDirectly($manager, Department::Rental, $request->id, $secondOwner->id, 'توزيع العمل');
        $service->reassignDirectly($manager, Department::Rental, $request->id, $firstOwner->id, 'إعادة توزيع العمل');

        $assignmentKeys = DB::table('notifications')
            ->where('workflow_event_key', 'like', 'request.assignment:%')
            ->orderBy('workflow_event_key')
            ->pluck('workflow_event_key')
            ->all();

        $this->assertCount(3, $assignmentKeys);
        $this->assertCount(3, array_unique($assignmentKeys));
        $this->assertSame(2, DB::table('notifications')->where('notifiable_id', $firstOwner->id)->where('workflow_event_key', 'like', 'request.assignment:%')->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $secondOwner->id)->where('workflow_event_key', 'like', 'request.assignment:%')->count());
    }

    public function test_pending_reassignment_notifies_all_managers_and_cancellation_retires_their_alerts(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $firstManager = User::factory()->create(['is_company_manager' => true]);
        $secondManager = User::factory()->create(['is_company_manager' => true]);
        $requester = User::factory()->create(['is_company_manager' => false]);
        $target = User::factory()->create(['is_company_manager' => false]);
        UserDepartment::query()->create(['user_id' => $requester->id, 'department' => Department::Rental]);
        UserDepartment::query()->create(['user_id' => $target->id, 'department' => Department::Rental]);
        $request = $this->createRentalRequest($requester, $requester, now()->addDay());

        app(RequestService::class)->requestReassignment($requester, Department::Rental, $request->id, $target->id, 'توزيع العمل');

        $managerNotifications = DB::table('notifications')->where('workflow_event_key', 'like', 'request.reassignment.requested:%')->get();
        $this->assertSame([$firstManager->id, $secondManager->id], $managerNotifications->pluck('notifiable_id')->map(fn ($id): int => (int) $id)->sort()->values()->all());
        $this->assertSame(0, DB::table('notifications')->where('workflow_event_key', 'like', 'request.reassignment.requested:%')->where('notifiable_id', $requester->id)->count());

        app(RequestService::class)->cancelReassignment($requester, Department::Rental, $request->id);

        foreach ($managerNotifications as $notification) {
            $this->assertNotNull(DB::table('notifications')->where('id', $notification->id)->value('read_at'));
        }
    }

    public function test_reassignment_approval_and_rejection_notify_the_requester(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $requester = User::factory()->create(['is_company_manager' => false]);
        $firstTarget = User::factory()->create(['is_company_manager' => false]);
        $secondTarget = User::factory()->create(['is_company_manager' => false]);
        foreach ([$requester, $firstTarget, $secondTarget] as $employee) {
            UserDepartment::query()->create(['user_id' => $employee->id, 'department' => Department::Rental]);
        }
        $service = app(RequestService::class);
        $approvedRequest = $this->createRentalRequest($requester, $requester, now()->addDay());
        $service->requestReassignment($requester, Department::Rental, $approvedRequest->id, $firstTarget->id, 'توزيع العمل');
        $service->decideReassignment($manager, Department::Rental, $approvedRequest->id, true);

        $approvedDecision = DB::table('notifications')
            ->where('notifiable_id', $requester->id)
            ->where('workflow_event_key', 'like', 'request.reassignment.decided:%')
            ->sole();
        $this->assertSame('approved', json_decode($approvedDecision->data, true)['workflow']['status']);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $firstTarget->id)->where('workflow_event_key', 'like', 'request.assignment:%')->count());
        $this->assertSame(0, DB::table('notifications')->where('workflow_event_key', 'like', 'request.reassignment.requested:%')->whereNull('read_at')->count());

        $rejectedRequest = $this->createRentalRequest($requester, $requester, now()->addDay(), 'عميل طلب إعادة تعيين مرفوض', '01012345673');
        $rejectedReassignment = $service->requestReassignment($requester, Department::Rental, $rejectedRequest->id, $secondTarget->id, 'ظرف طارئ');
        $service->decideReassignment($manager, Department::Rental, $rejectedRequest->id, false);

        $rejectedDecision = DB::table('notifications')
            ->where('notifiable_id', $requester->id)
            ->where('workflow_event_key', 'like', 'request.reassignment.decided:%')
            ->get()
            ->first(fn ($notification): bool => (int) json_decode($notification->data, true)['workflow']['reassignment_id'] === (int) $rejectedReassignment->id);
        $this->assertNotNull($rejectedDecision);
        $this->assertSame('rejected', json_decode($rejectedDecision->data, true)['workflow']['status']);
    }

    public function test_contact_change_requests_notify_managers_and_decisions_notify_the_requester(): void
    {
        $firstManager = User::factory()->create(['is_company_manager' => true]);
        $secondManager = User::factory()->create(['is_company_manager' => true]);
        $requester = User::factory()->create(['is_company_manager' => false]);
        UserDepartment::query()->create(['user_id' => $requester->id, 'department' => Department::Rental]);
        $customer = app(CustomerService::class)->create($requester, Department::Rental, [
            'name' => 'عميل طلب تعديل بيانات',
            'phone' => '01012345679',
            'phone_country' => 'EG',
        ]);
        $service = app(ContactChangeApprovalService::class);
        $before = app(CustomerService::class)->auditValues($customer);
        $after = [...$before, 'name' => 'اسم العميل المعدل'];

        $cancelled = $service->submit($requester, Department::Rental, $customer, $before, $after);
        $managerAlerts = DB::table('notifications')
            ->where('workflow_event_key', 'like', 'contact_change.requested:%')
            ->get();
        $this->assertSame([$firstManager->id, $secondManager->id], $managerAlerts->pluck('notifiable_id')->map(fn ($id): int => (int) $id)->sort()->values()->all());
        $managerAlertData = json_decode($managerAlerts->first()->data, true);
        $this->assertStringContainsString('عميل طلب تعديل بيانات', $managerAlertData['body']);
        parse_str((string) parse_url($managerAlertData['workflow']['url'], PHP_URL_QUERY), $managerQuery);
        $this->assertSame('pending', $managerQuery['tableFilters']['status']['value']);
        $this->assertSame('rental', $managerQuery['tableFilters']['department']['value']);

        $service->cancel($requester, $cancelled->id);
        foreach ($managerAlerts as $managerAlert) {
            $this->assertNotNull(DB::table('notifications')->where('id', $managerAlert->id)->value('read_at'));
        }

        $before = app(CustomerService::class)->auditValues($customer);
        $rejected = $service->submit($requester, Department::Rental, $customer, $before, [...$before, 'name' => 'اسم مرفوض']);
        $service->decide($firstManager, $rejected->id, false, 'الاسم غير مطابق للمستندات');
        $rejection = DB::table('notifications')
            ->where('notifiable_id', $requester->id)
            ->where('workflow_event_key', 'like', 'contact_change.decided:%')
            ->sole();
        $rejectionData = json_decode($rejection->data, true);
        $this->assertSame('rejected', $rejectionData['workflow']['status']);
        $this->assertStringContainsString('الاسم غير مطابق للمستندات', $rejectionData['body']);
        parse_str((string) parse_url($rejectionData['workflow']['url'], PHP_URL_QUERY), $rejectionQuery);
        $this->assertSame('rejected', $rejectionQuery['tableFilters']['status']['value']);

        $before = app(CustomerService::class)->auditValues($customer);
        $approved = $service->submit($requester, Department::Rental, $customer, $before, [...$before, 'name' => 'اسم معتمد']);
        $service->decide($secondManager, $approved->id, true);
        $approval = DB::table('notifications')
            ->where('notifiable_id', $requester->id)
            ->where('workflow_event_key', 'like', 'contact_change.decided:%')
            ->get()
            ->first(fn ($notification): bool => (int) json_decode($notification->data, true)['workflow']['contact_change_request_id'] === (int) $approved->id);
        $this->assertNotNull($approval);
        $this->assertSame('approved', json_decode($approval->data, true)['workflow']['status']);
        $this->assertSame('اسم معتمد', $customer->fresh()->name);
        $this->assertSame(0, DB::table('notifications')->where('workflow_event_key', 'like', 'contact_change.requested:%')->whereNull('read_at')->count());
    }

    public function test_contact_change_notifications_route_to_each_department_and_record_type(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $requester = User::factory()->create(['is_company_manager' => false]);
        foreach ([Department::Rental, Department::Resale] as $department) {
            UserDepartment::query()->create(['user_id' => $requester->id, 'department' => $department]);
        }

        $cases = [
            [Department::Rental, 'customer', '01012345684'],
            [Department::Resale, 'customer', '01012345685'],
            [Department::Rental, 'broker', null],
            [Department::Resale, 'broker', null],
        ];

        foreach ($cases as $index => [$department, $kind, $phone]) {
            $name = "جهة اتصال {$department->value} {$kind} {$index}";
            $subject = $kind === 'customer'
                ? app(CustomerService::class)->create($requester, $department, [
                    'name' => $name,
                    'phone' => $phone,
                    'phone_country' => 'EG',
                ])
                : app(BrokerService::class)->create($requester, $department, ['name' => $name]);
            $snapshotService = $kind === 'customer' ? CustomerService::class : BrokerService::class;
            $before = app($snapshotService)->auditValues($subject);
            $changeRequest = app(ContactChangeApprovalService::class)->submit(
                $requester,
                $department,
                $subject,
                $before,
                [...$before, 'name' => $name.' معدل'],
            );

            $notification = DB::table('notifications')
                ->where('notifiable_id', $manager->id)
                ->where('workflow_event_key', 'like', 'contact_change.requested:%')
                ->get()
                ->first(fn ($row): bool => (int) json_decode($row->data, true)['workflow']['contact_change_request_id'] === (int) $changeRequest->id);
            $this->assertNotNull($notification);
            $workflow = json_decode($notification->data, true)['workflow'];
            parse_str((string) parse_url($workflow['url'], PHP_URL_QUERY), $query);
            $this->assertSame($department->value, $workflow['department']);
            $this->assertSame($department->value, $query['tableFilters']['department']['value']);
            $this->assertSame($subject->getMorphClass(), $query['tableFilters']['subject_type']['value']);
            $this->assertSame('pending', $query['tableFilters']['status']['value']);
        }
    }

    public function test_due_follow_up_notifies_owner_then_escalates_once_to_authorized_managers_after_24_hours(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $secondManager = User::factory()->create(['is_company_manager' => true]);
        $departmentEmployee = User::factory()->create(['is_company_manager' => false]);
        UserDepartment::query()->create(['user_id' => $departmentEmployee->id, 'department' => Department::Rental]);
        $request = $this->createRentalRequest($manager, $manager, now()->addHour());
        $dueAt = now()->subMinutes(10);
        $request->update(['next_follow_up_at' => $dueAt]);

        $this->artisan('workflow:process-notifications')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $manager->id,
        ]);
        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.due:%')->count());
        $this->assertSame(0, DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.overdue:%')->count());

        Carbon::setTestNow(now()->addHours(25));
        $this->artisan('workflow:process-notifications')->assertExitCode(0);

        $overdueKeys = DB::table('notifications')
            ->where('workflow_event_key', 'like', 'request.follow_up.overdue:%')
            ->pluck('notifiable_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$manager->id, $secondManager->id], $overdueKeys);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $departmentEmployee->id,
            'workflow_event_key' => 'request.follow_up.overdue:'.RentalRequest::class.':'.$request->id.':'.$dueAt->format('YmdHis').':manager:'.$departmentEmployee->id,
        ]);

        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $this->assertSame(2, DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.overdue:%')->count());
    }

    public function test_reassigning_a_customer_follow_up_notifies_the_new_owner(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $previousOwner = User::factory()->create(['is_company_manager' => false]);
        $newOwner = User::factory()->create(['is_company_manager' => false]);
        UserDepartment::query()->create(['user_id' => $previousOwner->id, 'department' => Department::Rental]);
        UserDepartment::query()->create(['user_id' => $newOwner->id, 'department' => Department::Rental]);
        $request = $this->createRentalRequest($manager, $previousOwner, now()->addDay());
        app(RequestService::class)->close($manager, Department::Rental, $request->id, ['outcome' => 'successful']);
        $case = $request->customerFollowUp()->sole();
        $case->update(['next_follow_up_at' => now()->subMinute()]);

        $this->artisan('workflow:process-notifications')->assertExitCode(0);

        app(CustomerFollowUpService::class)->reassignOwner($manager, Department::Rental, $case->id, $newOwner->id);

        $notification = DB::table('notifications')
            ->where('workflow_event_key', 'like', 'customer_follow_up.assignment:%')
            ->sole();
        $data = json_decode($notification->data, true);
        $this->assertSame($newOwner->id, $notification->notifiable_id);
        $this->assertSame($case->id, $data['workflow']['source_id']);
        $this->assertSame('rental', $data['workflow']['department']);
        $this->assertStringContainsString('/admin/rental-customer-follow-ups', $data['actions'][0]['url']);
        $oldReminder = DB::table('notifications')
            ->where('workflow_event_key', 'like', 'customer_follow_up.due:%')
            ->where('notifiable_id', $previousOwner->id)
            ->sole();
        $this->assertNotNull($oldReminder->read_at);

        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $currentReminder = DB::table('notifications')
            ->where('workflow_event_key', 'like', 'customer_follow_up.due:%')
            ->where('notifiable_id', $newOwner->id)
            ->sole();
        $this->assertNull($currentReminder->read_at);
    }

    public function test_follow_up_rescheduling_retires_the_old_unread_reminder(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createRentalRequest($manager, $manager, now()->addHour());
        $request->update(['next_follow_up_at' => now()->subHours(25)]);

        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $oldReminder = DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.due:%')->sole();
        $oldEscalation = DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.overdue:%')->sole();
        $this->assertNull($oldReminder->read_at);
        $this->assertNull($oldEscalation->read_at);

        app(RequestService::class)->setNextFollowUp($manager, Department::Rental, $request->id, now()->addDays(2)->toDateTimeString());
        $this->assertNotNull(DB::table('notifications')->where('id', $oldReminder->id)->value('read_at'));
        $this->assertNotNull(DB::table('notifications')->where('id', $oldEscalation->id)->value('read_at'));

        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.due:%')->count());
    }

    public function test_pause_reminder_and_automatic_resume_are_recovered_without_duplicate_notifications(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $resumeAt = now()->addDays(5)->startOfMinute();
        $resumeFollowUpAt = $resumeAt->copy()->addDay();
        $request = $this->createRentalRequest($manager, $manager, now()->addDay());

        app(RequestService::class)->pause(
            $manager,
            Department::Rental,
            $request->id,
            'في انتظار قرار العميل',
            $resumeAt->toDateTimeString(),
            $resumeFollowUpAt->toDateTimeString(),
        );

        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'request.pause.reminder:%')->count());

        Carbon::setTestNow($resumeAt->copy()->addMinute());
        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $this->artisan('workflow:process-notifications')->assertExitCode(0);

        $request->refresh();
        $this->assertSame(RequestStatus::Active, $request->status);
        $this->assertSame($resumeFollowUpAt->toDateTimeString(), $request->next_follow_up_at->toDateTimeString());
        $this->assertNull($request->resume_at);
        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'request.pause.resumed:%')->count());
    }

    public function test_missed_pause_reminder_is_skipped_when_the_scheduler_recovers_after_resume_time(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $resumeAt = now()->addDays(5)->startOfMinute();
        $resumeFollowUpAt = $resumeAt->copy()->addDay();
        $request = $this->createRentalRequest($manager, $manager, now()->addDay());
        app(RequestService::class)->pause(
            $manager,
            Department::Rental,
            $request->id,
            'في انتظار قرار العميل',
            $resumeAt->toDateTimeString(),
            $resumeFollowUpAt->toDateTimeString(),
        );

        Carbon::setTestNow($resumeAt->copy()->addMinute());
        $this->artisan('workflow:process-notifications')->assertExitCode(0);

        $this->assertSame(RequestStatus::Active, $request->fresh()->status);
        $this->assertSame(0, DB::table('notifications')->where('workflow_event_key', 'like', 'request.pause.reminder:%')->count());
        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'request.pause.resumed:%')->count());
    }

    public function test_pause_reminder_is_created_at_the_seven_day_boundary(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $resumeAt = now()->addDays(8)->startOfMinute();
        $request = $this->createRentalRequest($manager, $manager, now()->addDay());
        app(RequestService::class)->pause(
            $manager,
            Department::Rental,
            $request->id,
            'في انتظار قرار العميل',
            $resumeAt->toDateTimeString(),
            $resumeAt->copy()->addDay()->toDateTimeString(),
        );

        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $this->assertSame(0, DB::table('notifications')->where('workflow_event_key', 'like', 'request.pause.reminder:%')->count());

        Carbon::setTestNow(now()->addDay());
        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'request.pause.reminder:%')->count());
    }

    public function test_viewing_creation_notifies_the_request_owner_with_a_link_to_the_request(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createRentalRequest($manager, $manager, now()->addDay());

        app(RecommendationService::class)->scheduleViewing($manager, Department::Rental, $request->id, [
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
        ]);

        $notification = DB::table('notifications')->where('workflow_event_key', 'like', 'viewing.created:%')->sole();
        $data = json_decode($notification->data, true);
        $this->assertSame($manager->id, $notification->notifiable_id);
        $this->assertSame($request->id, $data['workflow']['request_id']);
        $this->assertNotEmpty($data['actions'][0]['url']);
    }

    public function test_viewing_schedule_changes_and_cancellation_each_notify_the_current_owner(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createRentalRequest($manager, $manager, now()->addDay());
        $service = app(RecommendationService::class);
        $viewing = $service->scheduleViewing($manager, Department::Rental, $request->id, [
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
        ]);

        $service->setViewingSchedule($manager, Department::Rental, $viewing->id, now()->addDays(3)->toDateTimeString());
        $service->cancelViewing($manager, Department::Rental, $viewing->id);

        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'viewing.created:%')->count());
        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'viewing.scheduled:%')->count());
        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'viewing.cancelled:%')->count());
    }

    public function test_closed_request_customer_case_follow_up_gets_a_due_notification(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createRentalRequest($manager, $manager, now()->addDay());
        app(RequestService::class)->close($manager, Department::Rental, $request->id, ['outcome' => 'successful']);
        $case = $request->customerFollowUp()->sole();
        $case->update(['next_follow_up_at' => now()->subMinute()]);

        $this->artisan('workflow:process-notifications')->assertExitCode(0);

        $notification = DB::table('notifications')
            ->where('workflow_event_key', 'like', 'customer_follow_up.due:%')
            ->sole();
        $this->assertSame($manager->id, $notification->notifiable_id);
        $this->assertSame('customer_follow_up.due', json_decode($notification->data, true)['workflow']['event']);
        $this->assertStringContainsString('/admin/rental-customer-follow-ups', json_decode($notification->data, true)['actions'][0]['url']);
    }

    public function test_recording_a_viewing_result_notifies_the_request_owner_with_the_result(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createRentalRequest($manager, $manager, now()->addDay());
        $service = app(RecommendationService::class);
        $viewing = $service->scheduleViewing($manager, Department::Rental, $request->id, [
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
        ]);

        $service->completeViewing($manager, Department::Rental, $viewing->id);
        $service->recordViewingResult($manager, Department::Rental, $viewing->id, [
            'result' => 'awaiting_customer_reply',
        ]);

        $notification = DB::table('notifications')->where('workflow_event_key', 'like', 'viewing.result_recorded:%')->sole();
        $data = json_decode($notification->data, true);
        $this->assertSame($manager->id, $notification->notifiable_id);
        $this->assertSame('awaiting_customer_reply', $data['workflow']['result']);
        $this->assertSame($request->id, $data['workflow']['request_id']);
        $this->assertStringContainsString('انتظار الرد', $data['body']);
    }

    public function test_resale_due_follow_up_uses_resale_recipient_data_and_link(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createResaleRequest($manager, $manager, now()->addDay());
        $request->update(['next_follow_up_at' => now()->subMinute()]);

        $this->artisan('workflow:process-notifications')->assertExitCode(0);

        $notification = DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.due:%')->sole();
        $data = json_decode($notification->data, true);
        $this->assertSame('resale', $data['workflow']['department']);
        $this->assertSame($request->id, $data['workflow']['source_id']);
        $this->assertStringContainsString('/admin/resale-requests/'.$request->id, $data['actions'][0]['url']);
    }

    public function test_resale_viewing_result_notifies_the_request_owner(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createResaleRequest($manager, $manager, now()->addDay());
        $service = app(RecommendationService::class);
        $viewing = $service->scheduleViewing($manager, Department::Resale, $request->id, [
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
        ]);

        $service->completeViewing($manager, Department::Resale, $viewing->id);
        $service->recordViewingResult($manager, Department::Resale, $viewing->id, ['result' => 'negotiating']);

        $notification = DB::table('notifications')->where('workflow_event_key', 'like', 'viewing.result_recorded:%')->sole();
        $data = json_decode($notification->data, true);
        $this->assertSame($manager->id, $notification->notifiable_id);
        $this->assertSame('resale', $data['workflow']['department']);
        $this->assertSame('negotiating', $data['workflow']['result']);
        $this->assertStringContainsString('التفاوض في السعر', $data['body']);
    }

    public function test_pausing_a_request_retires_its_due_reminder_and_prevents_escalation(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createRentalRequest($manager, $manager, now()->addDay());
        $request->update(['next_follow_up_at' => now()->subMinute()]);

        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $dueReminder = DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.due:%')->sole();
        app(RequestService::class)->pause(
            $manager,
            Department::Rental,
            $request->id,
            'في انتظار العميل',
            now()->addDays(5)->toDateTimeString(),
            now()->addDays(6)->toDateTimeString(),
        );

        $this->assertNotNull(DB::table('notifications')->where('id', $dueReminder->id)->value('read_at'));
        Carbon::setTestNow(now()->addHours(25));
        $this->artisan('workflow:process-notifications')->assertExitCode(0);

        $this->assertSame(0, DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.overdue:%')->count());
        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'request.pause.reminder:%')->count());
    }

    public function test_closing_a_request_retires_its_due_reminder_and_prevents_overdue_escalation(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createRentalRequest($manager, $manager, now()->addDay());
        $request->update(['next_follow_up_at' => now()->subMinute()]);

        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $dueReminder = DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.due:%')->sole();
        app(RequestService::class)->close($manager, Department::Rental, $request->id, ['outcome' => 'successful']);

        $this->assertNotNull(DB::table('notifications')->where('id', $dueReminder->id)->value('read_at'));
        Carbon::setTestNow(now()->addHours(25));
        $this->artisan('workflow:process-notifications')->assertExitCode(0);

        $this->assertSame(0, DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.overdue:%')->count());
    }

    public function test_uncategorized_history_only_customer_follow_up_does_not_create_a_due_alert(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createRentalRequest($manager, $manager, now()->addDay());
        app(RequestService::class)->close($manager, Department::Rental, $request->id, ['outcome' => 'successful']);
        $historyOnly = RentalCustomerFollowUp::query()->create([
            'customer_id' => $request->customer_id,
            'owner_user_id' => $manager->id,
            'category' => null,
            'next_follow_up_at' => now()->subDay(),
        ]);

        $this->artisan('workflow:process-notifications')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'workflow_event_key' => 'customer_follow_up.due:'.RentalCustomerFollowUp::class.':'.$historyOnly->id.':'.now()->subDay()->format('YmdHis').':owner:'.$manager->id,
        ]);
    }

    public function test_legacy_case_reminder_is_retired_when_customer_gets_a_request(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل legacy أصبح له طلب',
            'phone' => '01012345901',
            'phone_country' => 'EG',
        ]);
        $case = app(CustomerFollowUpService::class)->startForCustomer($manager, Department::Rental, $customer->id);
        $case->update(['next_follow_up_at' => now()->subMinute()]);

        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $reminder = DB::table('notifications')->where('workflow_event_key', 'like', 'customer_follow_up.due:%')->sole();
        $this->assertNull($reminder->read_at);

        app(RequestService::class)->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);

        $this->assertNotNull(DB::table('notifications')->where('id', $reminder->id)->value('read_at'));
        $this->artisan('workflow:process-notifications')->assertExitCode(0);
        $this->assertSame(1, DB::table('notifications')->where('workflow_event_key', 'like', 'customer_follow_up.due:%')->count());
    }

    public function test_processor_handles_only_one_hundred_due_events_and_leaves_the_next_for_the_following_run(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00 Africa/Cairo');
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل دفعة التنبيهات',
            'phone' => '01012345671',
            'phone_country' => 'EG',
        ]);
        $requestService = app(RequestService::class);
        $areaId = RentalArea::query()->firstOrFail()->id;

        for ($index = 0; $index < 101; $index++) {
            $request = $requestService->create($manager, Department::Rental, [
                'customer_id' => $customer->id,
                'area_id' => $areaId,
                'owner_user_id' => $manager->id,
                'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            ]);
            $request->update(['next_follow_up_at' => now()->subMinute()]);
        }

        $firstRun = app(WorkflowNotificationService::class)->processDueEvents(100);
        $this->assertSame(['processed' => 100, 'pending' => 101, 'oldest_due_minutes' => 1], $firstRun);
        $this->assertSame(100, DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.due:%')->count());

        $secondRun = app(WorkflowNotificationService::class)->processDueEvents(100);
        $this->assertSame(1, $secondRun['processed']);
        $this->assertSame(1, $secondRun['pending']);
        $this->assertSame(101, DB::table('notifications')->where('workflow_event_key', 'like', 'request.follow_up.due:%')->count());
    }

    private function createRentalRequest(
        User $actor,
        User $owner,
        Carbon $nextFollowUpAt,
        string $customerName = 'عميل اختبار التنبيهات',
        string $phone = '01012345670',
    ): RentalRequest {
        if (! $actor->is_company_manager) {
            UserDepartment::query()->firstOrCreate(['user_id' => $actor->id, 'department' => Department::Rental]);
        }

        $customer = app(CustomerService::class)->create($actor, Department::Rental, [
            'name' => $customerName,
            'phone' => $phone,
            'phone_country' => 'EG',
        ]);

        return app(RequestService::class)->create($actor, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'owner_user_id' => $owner->id,
            'next_follow_up_at' => $nextFollowUpAt->toDateTimeString(),
        ]);
    }

    private function createResaleRequest(User $actor, User $owner, Carbon $nextFollowUpAt): ResaleRequest
    {
        $customer = app(CustomerService::class)->create($actor, Department::Resale, [
            'name' => 'عميل اختبار تنبيهات البيع',
            'phone' => '01012345672',
            'phone_country' => 'EG',
        ]);

        return app(RequestService::class)->create($actor, Department::Resale, [
            'customer_id' => $customer->id,
            'area_id' => ResaleArea::query()->firstOrFail()->id,
            'owner_user_id' => $owner->id,
            'next_follow_up_at' => $nextFollowUpAt->toDateTimeString(),
        ]);
    }
}
