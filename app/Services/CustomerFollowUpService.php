<?php

namespace App\Services;

use App\Enums\ContactActivityChannel;
use App\Enums\CustomerFollowUpCategory;
use App\Enums\Department;
use App\Enums\RequestOutcome;
use App\Models\AuditEvent;
use App\Models\RentalCustomerFollowUp;
use App\Models\RentalCustomerFollowUpActivity;
use App\Models\RentalCustomer;
use App\Models\RentalDeal;
use App\Models\RentalRequest;
use App\Models\ResaleCustomerFollowUp;
use App\Models\ResaleCustomerFollowUpActivity;
use App\Models\ResaleCustomer;
use App\Models\ResaleDeal;
use App\Models\ResaleRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerFollowUpService
{
    public function startForDeal(User $actor, Department $department, Model $deal): ?Model
    {
        $this->authorize($actor, $department);
        $request = $deal->request;
        if ($deal->customer_id === null || ! $request) {
            return null;
        }

        $profileClass = $department === Department::Rental ? RentalCustomerFollowUp::class : ResaleCustomerFollowUp::class;

        $profile = $profileClass::query()->where('request_id', $request->id)->first()
            ?? $this->createForClosedRequest($actor, $department, $request);
        if (! $profile) {
            return null;
        }
        $profile->deal_id = $deal->id;
        $profile->save();

        return $profile;
    }

    public function createForClosedRequest(User $actor, Department $department, Model $request): ?Model
    {
        $this->authorize($actor, $department);
        $requestClass = $department === Department::Rental ? RentalRequest::class : ResaleRequest::class;
        if (! $request instanceof $requestClass) {
            throw new AuthorizationException('الطلب لا ينتمي إلى القسم المحدد.');
        }
        if ($request->status->value !== 'closed' || ! $request->outcome || ! $request->customer_id) {
            return null;
        }

        $profileClass = $department === Department::Rental ? RentalCustomerFollowUp::class : ResaleCustomerFollowUp::class;
        $dealClass = $department === Department::Rental ? RentalDeal::class : ResaleDeal::class;
        $deal = $request->outcome === RequestOutcome::Successful
            ? $dealClass::query()->where('request_id', $request->id)->first()
            : null;
        $category = $request->outcome === RequestOutcome::Successful
            ? CustomerFollowUpCategory::Successful
            : CustomerFollowUpCategory::Unsuccessful;

        return DB::transaction(function () use ($actor, $department, $profileClass, $request, $deal, $category): Model {
            $profile = $profileClass::query()->firstOrCreate(
                ['request_id' => $request->id],
                [
                    'customer_id' => $request->customer_id,
                    'owner_user_id' => $request->owner_user_id,
                    'deal_id' => $deal?->id,
                    'category' => $category,
                ],
            );

            if ($profile->wasRecentlyCreated) {
                $this->recordAudit($actor, $department, 'customer_follow_up.started', $profile, [
                    'request_id' => $request->id,
                    'customer_id' => $request->customer_id,
                    'category' => $category->value,
                    'owner_user_id' => $profile->owner_user_id,
                ]);
            }

            return $profile;
        });
    }

    public function retireLegacyCaseReminder(Department $department, int $customerId): void
    {
        $profileClass = $department === Department::Rental ? RentalCustomerFollowUp::class : ResaleCustomerFollowUp::class;

        $profileClass::query()
            ->where('customer_id', $customerId)
            ->whereNotNull('legacy_customer_id')
            ->get()
            ->each(fn (Model $profile) => app(WorkflowNotificationService::class)->retireCustomerFollowUpReminder($profile));
    }

    public function startForCustomer(User $actor, Department $department, int $customerId): Model
    {
        $this->authorize($actor, $department);
        $customerClass = $department === Department::Rental ? RentalCustomer::class : ResaleCustomer::class;
        $profileClass = $department === Department::Rental ? RentalCustomerFollowUp::class : ResaleCustomerFollowUp::class;
        $customer = $customerClass::query()->findOrFail($customerId);

        if ($customer->requests()->exists()) {
            throw ValidationException::withMessages(['customer' => 'تُسجل متابعة الطلب من سجل الطلب نفسه؛ لا يمكن إنشاء متابعة «بدون طلب» لعميل لديه طلبات.']);
        }

        $profile = $profileClass::query()->firstOrCreate(
            ['legacy_customer_id' => $customer->id],
            [
                'customer_id' => $customer->id,
                'owner_user_id' => $actor->id,
                'category' => CustomerFollowUpCategory::LegacyWithoutRequest,
            ],
        );
        if ($profile->wasRecentlyCreated) {
            $this->recordAudit($actor, $department, 'customer_follow_up.started', $profile, [
                'customer_id' => $customer->id,
                'category' => CustomerFollowUpCategory::LegacyWithoutRequest->value,
                'owner_user_id' => $profile->owner_user_id,
            ]);
        }

        return $profile;
    }

    /** @param array<string, mixed> $data */
    public function recordActivity(User $actor, Department $department, int $customerId, int $followUpId, array $data): Model
    {
        $this->authorize($actor, $department);
        $activityClass = $department === Department::Rental ? RentalCustomerFollowUpActivity::class : ResaleCustomerFollowUpActivity::class;
        $profileClass = $department === Department::Rental ? RentalCustomerFollowUp::class : ResaleCustomerFollowUp::class;
        $dealTable = $department === Department::Rental ? 'rental_deals' : 'resale_deals';
        $data = Validator::make($data, [
            'deal_id' => ['nullable', 'integer', Rule::exists($dealTable, 'id')],
            'channel' => ['required', Rule::enum(ContactActivityChannel::class)],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'result' => ['required', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'referral_details' => ['nullable', 'string', 'max:10000'],
            'next_follow_up_at' => ['nullable', 'date', 'after:now'],
        ])->validate();

        return DB::transaction(function () use ($actor, $department, $customerId, $followUpId, $activityClass, $profileClass, $data, $dealTable): Model {
            $profile = $profileClass::query()->where('customer_id', $customerId)->lockForUpdate()->findOrFail($followUpId);
            $this->ensureActionableCase($profile);
            if (filled($data['deal_id'] ?? null)) {
                if (! $profile->request_id || $profile->category !== CustomerFollowUpCategory::Successful) {
                    throw ValidationException::withMessages(['deal_id' => 'لا يمكن ربط صفقة إلا بمتابعة طلب ناجح.']);
                }

                $dealQuery = DB::table($dealTable)
                    ->where('id', $data['deal_id'])
                    ->where('customer_id', $profile->customer_id)
                    ->where('request_id', $profile->request_id);
                if (! $dealQuery->exists()) {
                    throw ValidationException::withMessages(['deal_id' => 'الصفقة المختارة لا تخص سجل المتابعة هذا.']);
                }
                $profile->deal_id ??= (int) $data['deal_id'];
            }

            $activity = $profile->activities()->create([
                'deal_id' => $data['deal_id'] ?? null,
                'actor_user_id' => $actor->id,
                'channel' => $data['channel'],
                'occurred_at' => $data['occurred_at'],
                'result' => trim($data['result']),
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'referral_details' => filled($data['referral_details'] ?? null) ? trim($data['referral_details']) : null,
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
            ]);

            $previousFollowUpAt = $profile->next_follow_up_at?->toDateTimeString();
            $profile->next_follow_up_at = $data['next_follow_up_at'] ?? null;
            $profile->save();
            if ($previousFollowUpAt !== $profile->next_follow_up_at?->toDateTimeString()) {
                app(WorkflowNotificationService::class)->retireCustomerFollowUpReminder($profile);
            }
            $this->recordAudit($actor, $department, 'customer_follow_up.activity.recorded', $activity, [
                'customer_id' => $profile->customer_id,
                'follow_up_id' => $profile->id,
                'next_follow_up_at' => $profile->next_follow_up_at?->toIso8601String(),
            ]);

            return $activity;
        });
    }

    public function reassignOwner(User $actor, Department $department, int $followUpId, int $ownerUserId): Model
    {
        $this->authorize($actor, $department);
        $profileClass = $department === Department::Rental ? RentalCustomerFollowUp::class : ResaleCustomerFollowUp::class;
        $owner = User::query()->findOrFail($ownerUserId);
        if (! $owner->canWorkInDepartment($department)) {
            throw ValidationException::withMessages(['owner_user_id' => 'الموظف المختار لا يملك صلاحية العمل في هذا القسم.']);
        }

        return DB::transaction(function () use ($actor, $department, $profileClass, $followUpId, $owner): Model {
            $profile = $profileClass::query()->lockForUpdate()->findOrFail($followUpId);
            $this->ensureActionableCase($profile);
            $previousOwnerId = (int) $profile->owner_user_id;
            if ($previousOwnerId === (int) $owner->id) {
                return $profile;
            }

            $profile->owner_user_id = $owner->id;
            $profile->save();
            app(WorkflowNotificationService::class)->retireCustomerFollowUpReminder($profile);
            $auditEvent = $this->recordAudit($actor, $department, 'customer_follow_up.owner_changed', $profile, [
                'owner_user_id' => $previousOwnerId,
                'new_owner_user_id' => $owner->id,
            ]);
            app(WorkflowNotificationService::class)->notifyCustomerFollowUpAssignment($profile, $auditEvent);

            return $profile;
        });
    }

    /** @return Collection<int, RentalCustomerFollowUpActivity|ResaleCustomerFollowUpActivity> */
    public function history(User $actor, Department $department, int $followUpId): Collection
    {
        $this->authorize($actor, $department);
        $profileClass = $department === Department::Rental ? RentalCustomerFollowUp::class : ResaleCustomerFollowUp::class;

        return $profileClass::query()->findOrFail($followUpId)
            ->activities()->with(['actor', 'deal'])->orderByDesc('occurred_at')->orderByDesc('id')->get();
    }

    private function authorize(User $actor, Department $department): void
    {
        if (! $actor->canWorkInDepartment($department)) {
            throw new AuthorizationException('لا تملك صلاحية الوصول إلى هذا القسم.');
        }
    }

    private function ensureActionableCase(Model $profile): void
    {
        $legacyCaseNowHasRequests = $profile->legacy_customer_id
            && ($profile->customer()->first()?->requests()->exists() ?? false);

        if (! ($profile->category instanceof CustomerFollowUpCategory) || $legacyCaseNowHasRequests) {
            throw ValidationException::withMessages(['follow_up' => 'هذا سجل تاريخي للقراءة فقط ولا يمكن تسجيل متابعة جديدة عليه.']);
        }
    }

    /** @param array<string, mixed> $after */
    private function recordAudit(User $actor, Department $department, string $eventKey, Model $subject, array $after): AuditEvent
    {
        return AuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'department' => $department->value,
            'event_key' => $eventKey,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'before_values' => null,
            'after_values' => $after,
            'reason' => null,
            'created_at' => now(),
        ]);
    }
}
