<?php

namespace App\Services;

use App\Enums\ContactChangeRequestStatus;
use App\Enums\Department;
use App\Enums\RequestOutcome;
use App\Enums\RequestStatus;
use App\Filament\Resources\ContactChangeRequests\ContactChangeRequestResource;
use App\Filament\Resources\RentalCustomerFollowUps\RentalCustomerFollowUpResource;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Filament\Resources\ResaleCustomerFollowUps\ResaleCustomerFollowUpResource;
use App\Filament\Resources\ResaleRequests\ResaleRequestResource;
use App\Models\AuditEvent;
use App\Models\ContactChangeRequest;
use App\Models\RentalBroker;
use App\Models\RentalCustomerFollowUp;
use App\Models\RentalRequest;
use App\Models\RentalViewing;
use App\Models\ResaleBroker;
use App\Models\ResaleCustomerFollowUp;
use App\Models\ResaleRequest;
use App\Models\User;
use App\Notifications\WorkflowEventNotification;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WorkflowNotificationService
{
    /** @return array{processed: int, pending: int, oldest_due_minutes: int} */
    public function processDueEvents(int $limit = 100): array
    {
        $now = now();
        $candidates = [];
        $scanLimit = max(1, $limit) + 1;
        $companyManagerIds = User::query()->where('is_company_manager', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ([
            [RentalRequest::class, Department::Rental],
            [ResaleRequest::class, Department::Resale],
        ] as [$requestClass, $department]) {
            $this->collectRequestFollowUps($candidates, $requestClass, $department, $companyManagerIds, $now, $scanLimit);
            $this->collectPauseEvents($candidates, $requestClass, $department, $now, $scanLimit);
        }

        foreach ([RentalCustomerFollowUp::class, ResaleCustomerFollowUp::class] as $followUpClass) {
            $this->collectCustomerFollowUps($candidates, $followUpClass, $now, $scanLimit);
        }

        usort($candidates, static function (array $left, array $right): int {
            return [$left['due_at']->getTimestamp(), $left['source_id'], $left['type']]
                <=> [$right['due_at']->getTimestamp(), $right['source_id'], $right['type']];
        });

        $pending = count($candidates);
        $oldestDueMinutes = $pending > 0
            ? max(0, (int) $candidates[0]['due_at']->diffInMinutes($now, false))
            : 0;

        foreach (array_slice($candidates, 0, max(1, $limit)) as $event) {
            $this->processEvent($event, $now, $companyManagerIds);
        }

        return [
            'processed' => min($pending, max(1, $limit)),
            'pending' => $pending,
            'oldest_due_minutes' => $oldestDueMinutes,
        ];
    }

    public function notifyAssignment(Model $request, AuditEvent $auditEvent): void
    {
        $this->retireRequestFollowUpReminders($request);
        $this->retirePauseReminder($request);
        $owner = $request->owner;
        if (! $owner instanceof User) {
            return;
        }

        $code = $this->requestCode($request);
        $this->notify(
            $owner,
            'request.assignment:'.$request::class.':'.$request->getKey().':audit:'.$auditEvent->getKey(),
            'تم إسناد طلب إليك',
            "تم إسناد {$code} إليك لمتابعته.",
            $request,
            $this->requestUrl($request),
            ['event' => 'request.assignment', 'audit_event_id' => $auditEvent->getKey()],
        );
    }

    public function notifyReassignmentRequested(Model $request, Model $reassignment, Department $department, AuditEvent $auditEvent): void
    {
        User::query()->where('is_company_manager', true)->orderBy('id')->each(function (User $manager) use ($request, $reassignment, $department, $auditEvent): void {
            $this->notify(
                $manager,
                'request.reassignment.requested:'.$request::class.':'.$request->getKey().':audit:'.$auditEvent->getKey().':manager:'.$manager->id,
                'طلب إعادة تعيين بانتظار قرارك',
                "يوجد طلب لإعادة تعيين {$this->requestCode($request)}. راجع الطلب واتخذ القرار.",
                $request,
                $this->requestUrl($request),
                [
                    'event' => 'request.reassignment.requested',
                    'audit_event_id' => $auditEvent->getKey(),
                    'reassignment_id' => $reassignment->getKey(),
                    'requester_user_id' => $reassignment->requested_by,
                    'target_user_id' => $reassignment->to_user_id,
                    'department' => $department->value,
                ],
            );
        });
    }

    public function notifyReassignmentDecision(Model $request, Model $reassignment, Department $department, AuditEvent $auditEvent, bool $approved): void
    {
        $this->retirePendingReassignmentNotifications($request);
        $requester = User::query()->find($reassignment->requested_by);
        if (! $requester) {
            return;
        }

        $target = User::query()->find($reassignment->to_user_id);
        $decision = $approved ? 'approved' : 'rejected';
        $title = $approved ? 'تمت الموافقة على طلب إعادة التعيين' : 'تم رفض طلب إعادة التعيين';
        $body = $approved
            ? "تمت الموافقة على إعادة تعيين {$this->requestCode($request)} إلى {$target?->name}."
            : "تم رفض طلب إعادة تعيين {$this->requestCode($request)}.";

        $this->notify(
            $requester,
            'request.reassignment.decided:'.$request::class.':'.$request->getKey().':audit:'.$auditEvent->getKey().':requester:'.$requester->id,
            $title,
            $body,
            $request,
            $this->requestUrl($request),
            [
                'event' => 'request.reassignment.decided',
                'status' => $decision,
                'audit_event_id' => $auditEvent->getKey(),
                'reassignment_id' => $reassignment->getKey(),
                'department' => $department->value,
            ],
        );
    }

    public function retirePendingReassignmentNotifications(Model $request): void
    {
        $this->retirePrefix('request.reassignment.requested:'.$request::class.':'.$request->getKey().':');
    }

    public function notifyContactChangeRequested(ContactChangeRequest $changeRequest, Model $subject, AuditEvent $auditEvent): void
    {
        $department = $changeRequest->department;
        $url = $this->contactChangeUrl($changeRequest, $subject, ContactChangeRequestStatus::Pending->value);
        $subjectLabel = $this->contactChangeSubjectLabel($subject);

        User::query()->where('is_company_manager', true)->orderBy('id')->each(function (User $manager) use ($changeRequest, $subject, $auditEvent, $department, $url, $subjectLabel): void {
            $this->notify(
                $manager,
                'contact_change.requested:'.$changeRequest->getKey().':audit:'.$auditEvent->getKey().':manager:'.$manager->id,
                'طلب تعديل بيانات بانتظار قرارك',
                "يوجد طلب تعديل بيانات لـ{$subjectLabel} «{$subject->name}» في قسم {$department->label()}.",
                $changeRequest,
                $url,
                [
                    'event' => 'contact_change.requested',
                    'department' => $department->value,
                    'audit_event_id' => $auditEvent->getKey(),
                    'contact_change_request_id' => $changeRequest->getKey(),
                    'subject_type' => $subject::class,
                    'subject_id' => $subject->getKey(),
                    'requester_user_id' => $changeRequest->requested_by,
                ],
            );
        });
    }

    public function notifyContactChangeDecision(ContactChangeRequest $changeRequest, Model $subject, AuditEvent $auditEvent, bool $approved): void
    {
        $this->retireContactChangeRequestNotifications($changeRequest);
        $requester = User::query()->find($changeRequest->requested_by);
        if (! $requester) {
            return;
        }

        $decision = $approved ? 'approved' : 'rejected';
        $subjectLabel = $this->contactChangeSubjectLabel($subject);
        $title = $approved ? 'تمت الموافقة على تعديل البيانات' : 'تم رفض تعديل البيانات';
        $body = $approved
            ? "تمت الموافقة على تعديل بيانات {$subjectLabel} «{$subject->name}»."
            : "تم رفض تعديل بيانات {$subjectLabel} «{$subject->name}». سبب الرفض: {$changeRequest->reason}";

        $this->notify(
            $requester,
            'contact_change.decided:'.$changeRequest->getKey().':audit:'.$auditEvent->getKey().':requester:'.$requester->id,
            $title,
            $body,
            $changeRequest,
            $this->contactChangeUrl($changeRequest, $subject, $decision),
            [
                'event' => 'contact_change.decided',
                'department' => $changeRequest->department->value,
                'status' => $decision,
                'audit_event_id' => $auditEvent->getKey(),
                'contact_change_request_id' => $changeRequest->getKey(),
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
            ],
        );
    }

    public function retireContactChangeRequestNotifications(ContactChangeRequest $changeRequest): void
    {
        $this->retirePrefix('contact_change.requested:'.$changeRequest->getKey().':');
    }

    public function notifyRequestPaused(Model $request): void
    {
        $this->retireRequestFollowUpReminders($request);
        $this->retirePauseReminder($request);
    }

    public function notifyRequestResumed(Model $request, AuditEvent $auditEvent, ?DateTimeInterface $previousResumeAt): void
    {
        $this->retirePauseReminder($request);
        $this->retireRequestFollowUpReminders($request);
        $owner = $request->owner;
        if (! $owner instanceof User) {
            return;
        }

        $resumeToken = $previousResumeAt ? $this->dateKey($previousResumeAt) : 'manual';
        $this->notify(
            $owner,
            'request.pause.resumed:'.$request::class.':'.$request->getKey().':'.$resumeToken.':audit:'.$auditEvent->getKey(),
            'تم استئناف الطلب',
            "تم استئناف {$this->requestCode($request)}، وموعد المتابعة التالي هو {$this->formatDateTime($request->next_follow_up_at)}.",
            $request,
            $this->requestUrl($request),
            ['event' => 'request.pause.resumed', 'audit_event_id' => $auditEvent->getKey()],
        );
    }

    public function notifyRequestClosed(Model $request, AuditEvent $auditEvent): void
    {
        $this->retireRequestFollowUpReminders($request);
        $this->retirePauseReminder($request);
        $owner = $request->owner;
        if (! $owner instanceof User) {
            return;
        }

        $outcome = $request->outcome instanceof RequestOutcome
            ? $request->outcome->value
            : (string) $request->outcome;
        $outcomeLabel = $outcome === RequestOutcome::Successful->value ? 'ناجحة' : 'غير ناجحة';
        $this->notify(
            $owner,
            'request.closed:'.$request::class.':'.$request->getKey().':audit:'.$auditEvent->getKey(),
            'تم إغلاق الطلب',
            "تم إغلاق {$this->requestCode($request)} بنتيجة {$outcomeLabel}.",
            $request,
            $this->requestUrl($request),
            ['event' => 'request.closed', 'audit_event_id' => $auditEvent->getKey()],
        );
    }

    public function notifyViewing(Model $request, Model $viewing, string $event, AuditEvent $auditEvent): void
    {
        $owner = $request->owner;
        if (! $owner instanceof User) {
            return;
        }

        [$title, $body] = match ($event) {
            'created' => ['تم تسجيل معاينة', "تم تسجيل محاولة معاينة جديدة على {$this->requestCode($request)}."],
            'scheduled' => ['تم تحديد موعد المعاينة', "تم تحديد موعد المعاينة على {$this->requestCode($request)}: {$this->formatDateTime($viewing->scheduled_at)}."],
            'rescheduled' => ['تم تأجيل المعاينة', "تم تأجيل المعاينة وإنشاء محاولة جديدة للطلب {$this->requestCode($request)}."],
            'cancelled' => ['تم إلغاء المعاينة', "تم إلغاء محاولة المعاينة للطلب {$this->requestCode($request)}."],
            'result_recorded' => ['تم تسجيل نتيجة المعاينة', "تم تسجيل نتيجة «{$this->viewingResultLabel($request, $viewing)}» للطلب {$this->requestCode($request)}."],
            default => ['تحديث المعاينة', "تم تحديث معاينة مرتبطة بالطلب {$this->requestCode($request)}."],
        };

        $this->notify(
            $owner,
            'viewing.'.$event.':'.$viewing::class.':'.$viewing->getKey().':audit:'.$auditEvent->getKey(),
            $title,
            $body,
            $viewing,
            $this->requestUrl($request),
            [
                'event' => "viewing.{$event}",
                'request_id' => $request->getKey(),
                'audit_event_id' => $auditEvent->getKey(),
                ...($event === 'result_recorded' ? ['result' => $viewing->result] : []),
            ],
        );
    }

    public function retireRequestFollowUpReminders(Model $request): void
    {
        $this->retirePrefix('request.follow_up.due:'.$request::class.':'.$request->getKey().':');
        $this->retirePrefix('request.follow_up.overdue:'.$request::class.':'.$request->getKey().':');
    }

    public function retireCustomerFollowUpReminder(Model $followUp): void
    {
        $this->retirePrefix('customer_follow_up.due:'.$followUp::class.':'.$followUp->getKey().':');
    }

    public function notifyCustomerFollowUpAssignment(Model $followUp, AuditEvent $auditEvent): void
    {
        $owner = $followUp->owner;
        if (! $owner instanceof User) {
            return;
        }

        $followUp->loadMissing('customer');
        $this->notify(
            $owner,
            'customer_follow_up.assignment:'.$followUp::class.':'.$followUp->getKey().':audit:'.$auditEvent->getKey(),
            'تم إسناد متابعة عميل إليك',
            "تم إسناد متابعة {$followUp->customer?->name} إليك.",
            $followUp,
            $this->customerFollowUpUrl($followUp),
            ['event' => 'customer_follow_up.assignment', 'audit_event_id' => $auditEvent->getKey()],
        );
    }

    private function collectRequestFollowUps(array &$events, string $requestClass, Department $department, array $managerIds, Carbon $now, int $limit): void
    {
        $added = 0;
        $requestClass::query()
            ->where('status', RequestStatus::Active->value)
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', $now)
            ->orderBy('next_follow_up_at')
            ->orderBy('id')
            ->cursor()
            ->each(function (Model $request) use (&$events, &$added, $requestClass, $department, $managerIds, $now, $limit): bool {
                if ($this->requestFollowUpNeedsProcessing($request, $managerIds, $now)) {
                    $events[] = [
                        'type' => 'request_follow_up',
                        'class' => $requestClass,
                        'department' => $department,
                        'source_id' => (int) $request->getKey(),
                        'due_at' => $request->next_follow_up_at,
                    ];
                    $added++;
                }

                return $added < $limit;
            });
    }

    private function collectPauseEvents(array &$events, string $requestClass, Department $department, Carbon $now, int $limit): void
    {
        $resuming = 0;
        $requestClass::query()
            ->where('status', RequestStatus::Paused->value)
            ->whereNotNull('resume_at')
            ->where('resume_at', '<=', $now)
            ->orderBy('resume_at')
            ->orderBy('id')
            ->cursor()
            ->each(function (Model $request) use (&$events, &$resuming, $requestClass, $department, $limit): bool {
                $events[] = [
                    'type' => 'request_resume',
                    'class' => $requestClass,
                    'department' => $department,
                    'source_id' => (int) $request->getKey(),
                    'due_at' => $request->resume_at,
                ];
                $resuming++;

                return $resuming < $limit;
            });

        $reminders = 0;
        $requestClass::query()
            ->where('status', RequestStatus::Paused->value)
            ->whereNotNull('resume_at')
            ->where('resume_at', '>', $now)
            ->where('resume_at', '<=', $now->copy()->addDays(7))
            ->orderBy('resume_at')
            ->orderBy('id')
            ->cursor()
            ->each(function (Model $request) use (&$events, &$reminders, $requestClass, $department, $limit): bool {
                if ($request->owner_user_id && ! $this->hasNotification($this->pauseReminderKey($request), (int) $request->owner_user_id)) {
                    $events[] = [
                        'type' => 'pause_reminder',
                        'class' => $requestClass,
                        'department' => $department,
                        'source_id' => (int) $request->getKey(),
                        'due_at' => $request->resume_at->copy()->subDays(7)->max(now()),
                    ];
                    $reminders++;
                }

                return $reminders < $limit;
            });
    }

    private function collectCustomerFollowUps(array &$events, string $followUpClass, Carbon $now, int $limit): void
    {
        $added = 0;
        $followUpClass::query()
            ->whereNotNull('category')
            ->where(function ($query): void {
                $query->whereNull('legacy_customer_id')
                    ->orWhereDoesntHave('customer.requests');
            })
            ->whereNotNull('next_follow_up_at')
            ->whereNotNull('owner_user_id')
            ->where('next_follow_up_at', '<=', $now)
            ->orderBy('next_follow_up_at')
            ->orderBy('id')
            ->cursor()
            ->each(function (Model $followUp) use (&$events, &$added, $followUpClass, $limit): bool {
                if (! $this->hasNotification($this->customerFollowUpKey($followUp), (int) $followUp->owner_user_id)) {
                    $events[] = [
                        'type' => 'customer_follow_up',
                        'class' => $followUpClass,
                        'source_id' => (int) $followUp->getKey(),
                        'due_at' => $followUp->next_follow_up_at,
                    ];
                    $added++;
                }

                return $added < $limit;
            });
    }

    private function requestFollowUpNeedsProcessing(Model $request, array $managerIds, Carbon $now): bool
    {
        if (! $request->owner_user_id || ! $this->hasNotification($this->requestDueKey($request), (int) $request->owner_user_id)) {
            return true;
        }

        if ($now->lt($request->next_follow_up_at->copy()->addHours(24))) {
            return false;
        }

        foreach ($managerIds as $managerId) {
            if (! $this->hasNotification($this->requestOverdueKey($request, $managerId), $managerId)) {
                return true;
            }
        }

        return false;
    }

    private function processEvent(array $event, Carbon $now, array $managerIds): void
    {
        match ($event['type']) {
            'request_follow_up' => $this->processRequestFollowUp($event, $now, $managerIds),
            'pause_reminder' => $this->processPauseReminder($event, $now),
            'request_resume' => $this->processAutomaticResume($event, $now),
            'customer_follow_up' => $this->processCustomerFollowUp($event),
            default => null,
        };
    }

    private function processRequestFollowUp(array $event, Carbon $now, array $managerIds): void
    {
        DB::transaction(function () use ($event, $now, $managerIds): void {
            $request = $event['class']::query()->lockForUpdate()->find($event['source_id']);
            if (! $request || $request->status !== RequestStatus::Active || ! $request->next_follow_up_at || $request->next_follow_up_at->gt($now)) {
                return;
            }

            $owner = $request->owner;
            if ($owner instanceof User) {
                $dueAt = $request->next_follow_up_at;
                $this->notify(
                    $owner,
                    $this->requestDueKey($request),
                    'حان موعد متابعة الطلب',
                    "حان موعد متابعة {$this->requestCode($request)}.",
                    $request,
                    $this->requestUrl($request),
                    ['event' => 'request.follow_up.due', 'due_at' => $dueAt->toIso8601String(), 'owner_user_id' => $owner->id],
                );

                if ($now->gte($dueAt->copy()->addHours(24))) {
                    foreach ($managerIds as $managerId) {
                        $manager = User::query()->find($managerId);
                        if (! $manager) {
                            continue;
                        }
                        $this->notify(
                            $manager,
                            $this->requestOverdueKey($request, $managerId),
                            'متابعة طلب متأخرة',
                            "تجاوز موعد متابعة {$this->requestCode($request)} بـ24 ساعة. يرجى مراجعته واتخاذ الإجراء المناسب.",
                            $request,
                            $this->requestUrl($request),
                            ['event' => 'request.follow_up.overdue', 'due_at' => $dueAt->toIso8601String(), 'owner_user_id' => $owner->id, 'department' => $event['department']->value],
                        );
                    }
                }
            }
        });
    }

    private function processPauseReminder(array $event, Carbon $now): void
    {
        DB::transaction(function () use ($event, $now): void {
            $request = $event['class']::query()->lockForUpdate()->find($event['source_id']);
            if (! $request
                || $request->status !== RequestStatus::Paused
                || ! $request->resume_at
                || $request->resume_at->lte($now)
                || $request->resume_at->gt($now->copy()->addDays(7))) {
                return;
            }
            $owner = $request->owner;
            if (! $owner instanceof User) {
                return;
            }

            $this->notify(
                $owner,
                $this->pauseReminderKey($request),
                'تذكير بموعد استئناف الطلب',
                "موعد استئناف {$this->requestCode($request)} هو {$this->formatDateTime($request->resume_at)}.",
                $request,
                $this->requestUrl($request),
                ['event' => 'request.pause.reminder', 'due_at' => $request->resume_at->toIso8601String(), 'owner_user_id' => $owner->id],
            );
        });
    }

    private function processAutomaticResume(array $event, Carbon $now): void
    {
        DB::transaction(function () use ($event, $now): void {
            $request = $event['class']::query()->lockForUpdate()->find($event['source_id']);
            if (! $request || $request->status !== RequestStatus::Paused || ! $request->resume_at || $request->resume_at->gt($now)) {
                return;
            }

            $resumeAt = $request->resume_at->copy();
            $followUpAt = $request->resume_follow_up_at;
            $request->status = RequestStatus::Active;
            $request->paused_at = null;
            $request->pause_reason = null;
            $request->resume_at = null;
            $request->resume_follow_up_at = null;
            $request->next_follow_up_at = $followUpAt;
            $request->save();

            $statusHistoryTable = $event['department'] === Department::Rental
                ? 'rental_request_status_histories'
                : 'resale_request_status_histories';
            DB::table($statusHistoryTable)->insert([
                'request_id' => $request->getKey(),
                'from_status' => RequestStatus::Paused->value,
                'to_status' => RequestStatus::Active->value,
                'changed_by' => null,
                'notes' => 'استئناف تلقائي حسب الموعد المحدد.',
                'created_at' => $now,
            ]);

            $auditEvent = AuditEvent::query()->create([
                'actor_user_id' => null,
                'department' => $event['department']->value,
                'event_key' => 'request.auto_resumed',
                'subject_type' => $request::class,
                'subject_id' => $request->getKey(),
                'before_values' => ['status' => RequestStatus::Paused->value, 'resume_at' => $resumeAt->toIso8601String()],
                'after_values' => ['status' => RequestStatus::Active->value, 'next_follow_up_at' => $followUpAt?->toIso8601String()],
                'reason' => null,
                'created_at' => $now,
            ]);

            $this->notifyRequestResumed($request, $auditEvent, $resumeAt);
        });
    }

    private function processCustomerFollowUp(array $event): void
    {
        DB::transaction(function () use ($event): void {
            $followUp = $event['class']::query()->lockForUpdate()->find($event['source_id']);
            if (! $followUp || ! $followUp->next_follow_up_at || $followUp->next_follow_up_at->gt(now()) || ! $followUp->owner_user_id) {
                return;
            }
            if (! $followUp->category || ($followUp->legacy_customer_id && $followUp->customer()->first()?->requests()->exists())) {
                return;
            }
            $owner = $followUp->owner;
            if (! $owner instanceof User) {
                return;
            }

            $department = $followUp instanceof RentalCustomerFollowUp ? Department::Rental : Department::Resale;
            $followUp->loadMissing(['customer', 'legacyCustomer']);
            $customer = $followUp->customer ?? $followUp->legacyCustomer;

            $this->notify(
                $owner,
                $this->customerFollowUpKey($followUp),
                'حان موعد متابعة العميل',
                "حان موعد متابعة {$customer?->name} في قسم {$department->label()}.",
                $followUp,
                $this->customerFollowUpUrl($followUp),
                ['event' => 'customer_follow_up.due', 'due_at' => $followUp->next_follow_up_at->toIso8601String(), 'owner_user_id' => $owner->id],
            );
        });
    }

    private function notify(User $recipient, string $eventKey, string $title, string $body, Model $source, string $url, array $metadata): void
    {
        $request = $source instanceof RentalRequest || $source instanceof ResaleRequest
            ? $source
            : (method_exists($source, 'request') ? $source->loadMissing('request')->request : null);
        $department = match (true) {
            $source instanceof RentalRequest,
            $source instanceof RentalCustomerFollowUp,
            $source instanceof RentalViewing => Department::Rental,
            default => Department::Resale,
        };
        $dueAt = $metadata['due_at']
            ?? $source->next_follow_up_at
            ?? $source->resume_at
            ?? $source->scheduled_at
            ?? $request?->next_follow_up_at;
        $dueAt = $dueAt instanceof DateTimeInterface ? Carbon::instance($dueAt)->toIso8601String() : $dueAt;

        $recipient->notify(new WorkflowEventNotification(
            $eventKey,
            $title,
            $body,
            $source::class,
            $source->getKey(),
            $url,
            [
                'department' => $department->value,
                'owner_user_id' => $source->owner_user_id ?? $request?->owner_user_id,
                'due_at' => $dueAt,
                ...$metadata,
            ],
        ));
    }

    private function requestDueKey(Model $request): string
    {
        return 'request.follow_up.due:'.$request::class.':'.$request->getKey().':'.$this->dateKey($request->next_follow_up_at).':owner:'.$request->owner_user_id;
    }

    private function requestOverdueKey(Model $request, int $managerId): string
    {
        return 'request.follow_up.overdue:'.$request::class.':'.$request->getKey().':'.$this->dateKey($request->next_follow_up_at).':manager:'.$managerId;
    }

    private function pauseReminderKey(Model $request): string
    {
        return 'request.pause.reminder:'.$request::class.':'.$request->getKey().':'.$this->dateKey($request->resume_at).':owner:'.$request->owner_user_id;
    }

    private function customerFollowUpKey(Model $followUp): string
    {
        return 'customer_follow_up.due:'.$followUp::class.':'.$followUp->getKey().':'.$this->dateKey($followUp->next_follow_up_at).':owner:'.$followUp->owner_user_id;
    }

    private function hasNotification(string $eventKey, int|string $recipientId): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', (new User)->getMorphClass())
            ->where('notifiable_id', $recipientId)
            ->where('workflow_event_key', $eventKey)
            ->exists();
    }

    private function retirePauseReminder(Model $request): void
    {
        $this->retirePrefix('request.pause.reminder:'.$request::class.':'.$request->getKey().':');
    }

    private function retirePrefix(string $prefix): void
    {
        DB::table('notifications')
            ->whereRaw("workflow_event_key LIKE ? ESCAPE '!'", [strtr($prefix, ['!' => '!!', '%' => '!%', '_' => '!_']).'%'])
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }

    private function requestUrl(Model $request): string
    {
        $resource = $request instanceof RentalRequest ? RentalRequestResource::class : ResaleRequestResource::class;

        return $resource::getUrl('view', ['record' => $request->getKey()]);
    }

    private function customerFollowUpUrl(Model $followUp): string
    {
        $resource = $followUp instanceof RentalCustomerFollowUp
            ? RentalCustomerFollowUpResource::class
            : ResaleCustomerFollowUpResource::class;

        return $resource::getUrl('index');
    }

    private function contactChangeUrl(ContactChangeRequest $changeRequest, Model $subject, string $status): string
    {
        return ContactChangeRequestResource::getUrl('index', [
            'tableFilters' => [
                'status' => ['value' => $status],
                'department' => ['value' => $changeRequest->department->value],
                'subject_type' => ['value' => $subject->getMorphClass()],
            ],
        ]);
    }

    private function contactChangeSubjectLabel(Model $subject): string
    {
        return $subject instanceof RentalBroker || $subject instanceof ResaleBroker ? 'الوسيط' : 'العميل';
    }

    private function viewingResultLabel(Model $request, Model $viewing): string
    {
        $labels = $request instanceof RentalRequest
            ? [
                'reserved' => 'حجز',
                'unsuitable' => 'غير مناسبة',
                'other_apartment_viewing' => 'تحديد معاينة لشقة أخرى',
                'rented_same_unit' => 'تم التأجير',
                'awaiting_customer_reply' => 'انتظار الرد',
            ]
            : [
                'interested' => 'يفكر ويدرس المكان والخدمات',
                'not_interested' => 'غير مهتم',
                'second_viewing' => 'معاينة مرة أخرى',
                'negotiating' => 'التفاوض في السعر',
            ];

        return $labels[$viewing->result] ?? 'تم تحديثها';
    }

    private function requestCode(Model $request): string
    {
        $department = $request instanceof RentalRequest ? Department::Rental : Department::Resale;

        return $department->requestCode($request->getKey()) ?? (string) $request->getKey();
    }

    private function dateKey(?DateTimeInterface $date): string
    {
        return $date ? Carbon::instance($date)->format('YmdHis') : 'none';
    }

    private function formatDateTime(?DateTimeInterface $date): string
    {
        return $date ? Carbon::instance($date)->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—';
    }
}
