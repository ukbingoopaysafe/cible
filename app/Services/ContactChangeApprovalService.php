<?php

namespace App\Services;

use App\Enums\ContactChangeRequestStatus;
use App\Enums\Department;
use App\Models\AuditEvent;
use App\Models\ContactChangeRequest;
use App\Models\RentalBroker;
use App\Models\RentalCustomer;
use App\Models\ResaleBroker;
use App\Models\ResaleCustomer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ContactChangeApprovalService
{
    /** @param array<string, mixed> $after */
    public function submit(
        User $actor,
        Department $department,
        Model $subject,
        array $expectedBefore,
        array $after,
        bool $reviewedSimilarNames = false,
    ): ContactChangeRequest {
        if ($actor->is_company_manager || ! $actor->canWorkInDepartment($department)) {
            throw new AuthorizationException('لا تملك صلاحية إرسال طلب تعديل لهذا السجل.');
        }

        $this->assertSubjectDepartment($subject, $department);

        return DB::transaction(function () use ($actor, $department, $subject, $expectedBefore, $after, $reviewedSimilarNames): ContactChangeRequest {
            $lockedSubject = $subject->newQuery()->lockForUpdate()->findOrFail($subject->getKey());
            $before = $this->snapshot($lockedSubject);

            if ($before !== $expectedBefore) {
                throw ValidationException::withMessages([
                    'request' => 'تغيرت بيانات السجل أثناء تحرير النموذج. أغلقه وافتحه مجددًا لمراجعة أحدث البيانات.',
                ]);
            }

            if ($before === $after) {
                throw ValidationException::withMessages([
                    'request' => 'لم تتغير بيانات العميل أو الوسيط لإرسال طلب تعديل.',
                ]);
            }

            $pendingExists = ContactChangeRequest::query()
                ->where('subject_type', $lockedSubject->getMorphClass())
                ->where('subject_id', $lockedSubject->getKey())
                ->where('status', ContactChangeRequestStatus::Pending->value)
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'request' => 'يوجد طلب تعديل معلق لهذا السجل. انتظر قرار المدير أو ألغِ الطلب الحالي أولًا.',
                ]);
            }

            $request = ContactChangeRequest::query()->create([
                'subject_type' => $lockedSubject->getMorphClass(),
                'subject_id' => $lockedSubject->getKey(),
                'department' => $department->value,
                'requested_by' => $actor->id,
                'before_values' => $before,
                'after_values' => $after,
                'reviewed_similar_names' => $reviewedSimilarNames,
                'status' => ContactChangeRequestStatus::Pending->value,
            ]);

            $this->recordAudit(
                $actor,
                $department,
                'contact_change.requested',
                $lockedSubject,
                $before,
                $after,
            );

            return $request;
        });
    }

    public function decide(User $actor, int $requestId, bool $approved, ?string $reason = null): ContactChangeRequest
    {
        if (! $actor->is_company_manager) {
            throw new AuthorizationException('اعتماد طلبات تعديل بيانات العملاء والوسطاء متاح لمدير الشركة فقط.');
        }

        $reason = trim((string) $reason);
        if (! $approved && $reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'سبب رفض طلب التعديل مطلوب.',
            ]);
        }
        if (mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'يجب ألا يتجاوز سبب الرفض 2000 حرف.',
            ]);
        }

        return DB::transaction(function () use ($actor, $requestId, $approved, $reason): ContactChangeRequest {
            $request = ContactChangeRequest::query()->lockForUpdate()->findOrFail($requestId);
            if ($request->status !== ContactChangeRequestStatus::Pending) {
                throw ValidationException::withMessages([
                    'request' => 'تم حسم طلب التعديل مسبقًا.',
                ]);
            }

            $subject = $request->subject;
            if (! $subject instanceof Model) {
                throw ValidationException::withMessages([
                    'request' => 'لم يعد سجل العميل أو الوسيط موجودًا؛ لا يمكن حسم الطلب.',
                ]);
            }

            $department = $request->department;
            $this->assertSubjectDepartment($subject, $department);
            $subject = $subject->newQuery()->lockForUpdate()->findOrFail($subject->getKey());

            if ($approved) {
                $current = $this->snapshot($subject);
                if ($current !== $request->before_values) {
                    throw ValidationException::withMessages([
                        'request' => 'تغيرت بيانات السجل بعد إرسال الطلب. راجع البيانات الحالية وارفض الطلب بسبب التعارض قبل إجراء تعديل جديد.',
                    ]);
                }

                $this->applyApprovedValues($actor, $department, $subject, $request);
            }

            $beforeDecision = ['status' => $request->status->value];
            $request->status = $approved
                ? ContactChangeRequestStatus::Approved
                : ContactChangeRequestStatus::Rejected;
            $request->decided_by = $actor->id;
            $request->reason = $approved ? null : $reason;
            $request->decided_at = now();
            $request->save();

            $this->recordAudit(
                $actor,
                $department,
                $approved ? 'contact_change.approved' : 'contact_change.rejected',
                $subject,
                $beforeDecision + ['request_id' => $request->id, 'requested_by' => $request->requested_by],
                ['status' => $request->status->value, 'decided_by' => $actor->id],
                $approved ? null : $reason,
            );

            return $request->refresh();
        });
    }

    public function cancel(User $actor, int $requestId): ContactChangeRequest
    {
        return DB::transaction(function () use ($actor, $requestId): ContactChangeRequest {
            $request = ContactChangeRequest::query()->lockForUpdate()->findOrFail($requestId);
            if ((int) $request->requested_by !== (int) $actor->id) {
                throw new AuthorizationException('يمكن لمقدم الطلب إلغاء طلبه فقط.');
            }

            if (! $actor->canWorkInDepartment($request->department)) {
                throw new AuthorizationException('لا تملك صلاحية الوصول إلى قسم هذا الطلب.');
            }

            if ($request->status !== ContactChangeRequestStatus::Pending) {
                throw ValidationException::withMessages([
                    'request' => 'لا يمكن إلغاء طلب سبق حسمه.',
                ]);
            }

            $subject = $request->subject;
            if (! $subject instanceof Model) {
                throw ValidationException::withMessages([
                    'request' => 'لم يعد سجل العميل أو الوسيط موجودًا؛ لا يمكن إلغاء الطلب.',
                ]);
            }

            $department = $request->department;
            $this->assertSubjectDepartment($subject, $department);
            $beforeDecision = ['status' => $request->status->value];
            $request->status = ContactChangeRequestStatus::Cancelled;
            $request->decided_by = $actor->id;
            $request->decided_at = now();
            $request->save();

            $this->recordAudit(
                $actor,
                $department,
                'contact_change.cancelled',
                $subject,
                $beforeDecision + ['request_id' => $request->id],
                ['status' => $request->status->value],
            );

            return $request->refresh();
        });
    }

    public function hasPendingFor(Model $subject): bool
    {
        return ContactChangeRequest::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('status', ContactChangeRequestStatus::Pending->value)
            ->exists();
    }

    /** @return array<string, mixed> */
    private function snapshot(Model $subject): array
    {
        if ($subject instanceof RentalCustomer || $subject instanceof ResaleCustomer) {
            return app(CustomerService::class)->auditValues($subject);
        }

        if ($subject instanceof RentalBroker || $subject instanceof ResaleBroker) {
            return app(BrokerService::class)->auditValues($subject);
        }

        throw new AuthorizationException('نوع السجل غير مسموح بطلب تعديل بياناته.');
    }

    private function assertSubjectDepartment(Model $subject, Department $department): void
    {
        $expectedDepartment = match (true) {
            $subject instanceof RentalCustomer, $subject instanceof RentalBroker => Department::Rental,
            $subject instanceof ResaleCustomer, $subject instanceof ResaleBroker => Department::Resale,
            default => null,
        };

        if ($expectedDepartment !== $department) {
            throw new AuthorizationException('لا يتطابق القسم مع نوع سجل العميل أو الوسيط.');
        }
    }

    private function applyApprovedValues(
        User $actor,
        Department $department,
        Model $subject,
        ContactChangeRequest $request,
    ): void {
        $after = $request->after_values ?? [];
        $phones = collect($after['phones'] ?? [])
            ->map(fn (array $phone): array => [
                'phone' => (string) ($phone['phone_e164'] ?? ''),
                'phone_country' => (string) ($phone['phone_country'] ?? ''),
                'is_primary' => (bool) ($phone['is_primary'] ?? false),
            ])
            ->all();

        if ($subject instanceof RentalCustomer || $subject instanceof ResaleCustomer) {
            app(CustomerService::class)->update($actor, $department, (int) $subject->getKey(), [
                'name' => $after['name'] ?? '',
                'nationality' => $after['nationality'] ?? null,
                'phones' => $phones,
            ], $request->reviewed_similar_names);

            return;
        }

        if ($subject instanceof RentalBroker || $subject instanceof ResaleBroker) {
            app(BrokerService::class)->update($actor, $department, (int) $subject->getKey(), [
                'name' => $after['name'] ?? '',
                'phones' => $phones,
            ], $request->reviewed_similar_names);

            return;
        }

        throw new AuthorizationException('نوع السجل غير مسموح باعتماد تعديله.');
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function recordAudit(
        User $actor,
        Department $department,
        string $event,
        Model $subject,
        ?array $before,
        ?array $after,
        ?string $reason = null,
    ): void {
        AuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'department' => $department->value,
            'event_key' => $event,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
