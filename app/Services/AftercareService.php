<?php

namespace App\Services;

use App\Enums\ContactActivityChannel;
use App\Enums\Department;
use App\Models\AuditEvent;
use App\Models\RentalAftercare;
use App\Models\RentalAftercareActivity;
use App\Models\RentalCustomer;
use App\Models\RentalDeal;
use App\Models\ResaleAftercare;
use App\Models\ResaleAftercareActivity;
use App\Models\ResaleCustomer;
use App\Models\ResaleDeal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AftercareService
{
    public function startForDeal(User $actor, Department $department, Model $deal): ?Model
    {
        $this->authorize($actor, $department);
        if ($deal->customer_id === null) {
            return null;
        }

        $profileClass = $department === Department::Rental ? RentalAftercare::class : ResaleAftercare::class;

        $profile = $profileClass::query()->firstOrCreate(
            ['customer_id' => $deal->customer_id],
            ['owner_user_id' => $deal->request()->value('owner_user_id')],
        );

        if ($profile->wasRecentlyCreated) {
            $this->recordAudit($actor, $department, 'aftercare.started', $profile, ['customer_id' => $deal->customer_id, 'owner_user_id' => $profile->owner_user_id]);
        }

        return $profile;
    }

    public function startForCustomer(User $actor, Department $department, int $customerId): Model
    {
        $this->authorize($actor, $department);
        $customerClass = $department === Department::Rental ? RentalCustomer::class : ResaleCustomer::class;
        $dealClass = $department === Department::Rental ? RentalDeal::class : ResaleDeal::class;
        $profileClass = $department === Department::Rental ? RentalAftercare::class : ResaleAftercare::class;
        $customer = $customerClass::query()->findOrFail($customerId);

        if (! $dealClass::query()->where('customer_id', $customer->id)->exists()) {
            throw ValidationException::withMessages(['customer' => 'لا يمكن بدء ما بعد الصفقة قبل وجود صفقة ناجحة مرتبطة بهذا العميل.']);
        }

        $profile = $profileClass::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            ['owner_user_id' => $actor->id],
        );
        if ($profile->wasRecentlyCreated) {
            $this->recordAudit($actor, $department, 'aftercare.started', $profile, ['customer_id' => $customer->id, 'owner_user_id' => $profile->owner_user_id]);
        }

        return $profile;
    }

    /** @param array<string, mixed> $data */
    public function recordActivity(User $actor, Department $department, int $customerId, array $data): Model
    {
        $this->authorize($actor, $department);
        $activityClass = $department === Department::Rental ? RentalAftercareActivity::class : ResaleAftercareActivity::class;
        $profileClass = $department === Department::Rental ? RentalAftercare::class : ResaleAftercare::class;
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

        return DB::transaction(function () use ($actor, $department, $customerId, $activityClass, $profileClass, $data, $dealTable): Model {
            $profile = $profileClass::query()->where('customer_id', $customerId)->lockForUpdate()->firstOrFail();
            if (filled($data['deal_id'] ?? null) && ! DB::table($dealTable)->where('id', $data['deal_id'])->where('customer_id', $customerId)->exists()) {
                throw ValidationException::withMessages(['deal_id' => 'الصفقة المختارة لا تخص هذا العميل.']);
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

            $profile->next_follow_up_at = $data['next_follow_up_at'] ?? null;
            $profile->save();
            $this->recordAudit($actor, $department, 'aftercare.activity.recorded', $activity, [
                'customer_id' => $customerId,
                'next_follow_up_at' => $profile->next_follow_up_at?->toIso8601String(),
            ]);

            return $activity;
        });
    }

    /** @return Collection<int, RentalAftercareActivity|ResaleAftercareActivity> */
    public function history(User $actor, Department $department, int $customerId): Collection
    {
        $this->authorize($actor, $department);
        $profileClass = $department === Department::Rental ? RentalAftercare::class : ResaleAftercare::class;

        return $profileClass::query()->where('customer_id', $customerId)->firstOrFail()
            ->activities()->with(['actor', 'deal'])->orderByDesc('occurred_at')->orderByDesc('id')->get();
    }

    private function authorize(User $actor, Department $department): void
    {
        if (! $actor->canWorkInDepartment($department)) {
            throw new AuthorizationException('لا تملك صلاحية الوصول إلى هذا القسم.');
        }
    }

    /** @param array<string, mixed> $after */
    private function recordAudit(User $actor, Department $department, string $eventKey, Model $subject, array $after): void
    {
        AuditEvent::query()->create([
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
