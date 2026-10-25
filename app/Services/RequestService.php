<?php

namespace App\Services;

use App\Enums\ContactParty;
use App\Enums\Department;
use App\Enums\FinishingLevel;
use App\Enums\FloorPreference;
use App\Enums\RentalRequestStage;
use App\Enums\RequestOutcome;
use App\Enums\RequestStatus;
use App\Enums\ResaleRequestStage;
use App\Enums\RequestUnitType;
use App\Models\RentalBroker;
use App\Models\RentalCustomer;
use App\Models\RentalArea;
use App\Models\AuditEvent;
use App\Models\RentalRequest;
use App\Models\RentalLeadSource;
use App\Models\RentalRequestCloseReason;
use App\Models\RentalRequestReassignment;
use App\Models\ResaleBroker;
use App\Models\ResaleCustomer;
use App\Models\ResaleArea;
use App\Models\ResaleRequest;
use App\Models\ResaleLeadSource;
use App\Models\ResaleRequestCloseReason;
use App\Models\ResaleRequestReassignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequestService
{
    public function requestReassignment(User $actor, Department $department, int $requestId, int $targetUserId, string $reason): Model
    {
        $this->authorizeDepartment($actor, $department);
        $reason = trim($reason);
        if ($actor->canManageDepartment($department)) {
            throw ValidationException::withMessages(['request' => 'يستخدم المدير إعادة التعيين المباشرة بدل طلب الموافقة.']);
        }

        $validated = Validator::make(['target_user_id' => $targetUserId, 'reason' => $reason], [
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:10000'],
        ])->validate();
        $target = User::query()->findOrFail($validated['target_user_id']);
        if (! $target->canWorkInDepartment($department)) {
            throw ValidationException::withMessages(['target_user_id' => 'الموظف المختار ليس عضوًا في هذا القسم.']);
        }
        if ($target->is_company_manager) {
            throw ValidationException::withMessages(['target_user_id' => 'إعادة التعيين إلى مدير تحتاج إجراءً خاصًا غير متاح حاليًا.']);
        }
        if ((int) $target->id === (int) $actor->id) {
            throw ValidationException::withMessages(['target_user_id' => 'اختر موظفًا آخر في القسم.']);
        }

        $classes = $this->classesFor($department);
        $reassignmentClass = $this->reassignmentClassFor($department);

        return DB::transaction(function () use ($classes, $reassignmentClass, $requestId, $target, $actor, $department, $validated): Model {
            $request = $classes['request']::query()->lockForUpdate()->findOrFail($requestId);
            if ($request->status === RequestStatus::Closed) {
                throw ValidationException::withMessages(['request' => 'لا يمكن إعادة تعيين طلب مغلق.']);
            }
            if ((int) $request->owner_user_id === (int) $target->id) {
                throw ValidationException::withMessages(['target_user_id' => 'الموظف المختار مسؤول الطلب بالفعل.']);
            }
            if ($reassignmentClass::query()->where('request_id', $requestId)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['request' => 'يوجد طلب إعادة تعيين بانتظار قرار المدير بالفعل.']);
            }

            $reassignment = $reassignmentClass::query()->create([
                'request_id' => $requestId,
                'requested_by' => $actor->id,
                'from_user_id' => $request->owner_user_id,
                'to_user_id' => $target->id,
                'reason' => trim($validated['reason']),
                'status' => 'pending',
            ]);
            $this->recordAuditEvent($actor, $department, 'request.reassignment.requested', $request, [
                'owner_user_id' => $request->owner_user_id,
            ], [
                'owner_user_id' => $request->owner_user_id,
                'target_user_id' => $target->id,
                'reassignment_id' => $reassignment->getKey(),
                'status' => 'pending',
            ], $validated['reason']);

            return $reassignment;
        });
    }

    public function decideReassignment(User $actor, Department $department, int $requestId, bool $approved): Model
    {
        if (! $actor->canManageDepartment($department)) {
            throw new AuthorizationException('إدارة طلبات إعادة التعيين متاحة لمدير القسم فقط.');
        }

        $classes = $this->classesFor($department);
        $reassignmentClass = $this->reassignmentClassFor($department);

        return DB::transaction(function () use ($classes, $reassignmentClass, $requestId, $actor, $department, $approved): Model {
            $request = $classes['request']::query()->lockForUpdate()->findOrFail($requestId);
            $reassignment = $reassignmentClass::query()
                ->where('request_id', $requestId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->latest('id')
                ->firstOrFail();

            if ($request->status === RequestStatus::Closed || (int) $request->owner_user_id !== (int) $reassignment->from_user_id) {
                throw ValidationException::withMessages(['request' => 'تغير الطلب منذ إرسال إعادة التعيين، ولا يمكن اعتمادها.']);
            }
            if ($approved) {
                if ((int) $reassignment->to_user_id === (int) $actor->id) {
                    throw ValidationException::withMessages(['request' => 'لا يمكن للمدير اعتماد طلب يستهدف تعيينه هو؛ يلزم حسم هذا المسار قبل التنفيذ.']);
                }
                $target = User::query()->findOrFail($reassignment->to_user_id);
                if (! $target->canWorkInDepartment($department)) {
                    throw ValidationException::withMessages(['request' => 'الموظف المستهدف لم يعد عضوًا في القسم.']);
                }
                if ($target->is_company_manager) {
                    throw ValidationException::withMessages(['request' => 'إعادة التعيين إلى مدير تحتاج إجراءً خاصًا غير متاح حاليًا.']);
                }
            }

            $previousOwnerId = (int) $request->owner_user_id;
            $reassignment->status = $approved ? 'approved' : 'rejected';
            $reassignment->decided_by = $actor->id;
            $reassignment->decided_at = now();
            $reassignment->save();

            if ($approved) {
                $request->owner_user_id = $reassignment->to_user_id;
                $request->save();
            }

            $this->recordAuditEvent(
                $actor,
                $department,
                $approved ? 'request.reassignment.approved' : 'request.reassignment.rejected',
                $request,
                ['owner_user_id' => $previousOwnerId],
                [
                    'owner_user_id' => $request->owner_user_id,
                    'reassignment_id' => $reassignment->getKey(),
                    'requested_by' => $reassignment->requested_by,
                    'target_user_id' => $reassignment->to_user_id,
                    'status' => $reassignment->status,
                    'decided_by' => $actor->id,
                ],
                $reassignment->reason,
            );

            return $request;
        });
    }

    public function cancelReassignment(User $actor, Department $department, int $requestId): Model
    {
        $this->authorizeDepartment($actor, $department);
        $classes = $this->classesFor($department);
        $reassignmentClass = $this->reassignmentClassFor($department);

        return DB::transaction(function () use ($classes, $reassignmentClass, $requestId, $actor, $department): Model {
            $request = $classes['request']::query()->lockForUpdate()->findOrFail($requestId);
            $reassignment = $reassignmentClass::query()
                ->where('request_id', $requestId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->latest('id')
                ->firstOrFail();

            if ((int) $reassignment->requested_by !== (int) $actor->id) {
                throw new AuthorizationException('إلغاء طلب إعادة التعيين متاح لمقدم الطلب فقط.');
            }

            $reassignment->status = 'cancelled';
            $reassignment->decided_at = now();
            $reassignment->save();

            $this->recordAuditEvent($actor, $department, 'request.reassignment.cancelled', $request,
                ['owner_user_id' => $request->owner_user_id],
                [
                    'owner_user_id' => $request->owner_user_id,
                    'reassignment_id' => $reassignment->getKey(),
                    'requested_by' => $reassignment->requested_by,
                    'target_user_id' => $reassignment->to_user_id,
                    'status' => 'cancelled',
                ],
                $reassignment->reason,
            );

            return $reassignment;
        });
    }

    public function reassignDirectly(User $actor, Department $department, int $requestId, int $targetUserId, string $reason): Model
    {
        if (! $actor->canManageDepartment($department)) {
            throw new AuthorizationException('إعادة التعيين المباشرة متاحة لمدير القسم فقط.');
        }
        $reason = trim($reason);

        $validated = Validator::make(['target_user_id' => $targetUserId, 'reason' => $reason], [
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:10000'],
        ])->validate();
        $target = User::query()->findOrFail($validated['target_user_id']);
        if (! $target->canWorkInDepartment($department)) {
            throw ValidationException::withMessages(['target_user_id' => 'الموظف المختار ليس عضوًا في هذا القسم.']);
        }
        if ($target->is_company_manager) {
            throw ValidationException::withMessages(['target_user_id' => 'إعادة التعيين إلى مدير تحتاج إجراءً خاصًا غير متاح حاليًا.']);
        }
        if ((int) $target->id === (int) $actor->id) {
            throw ValidationException::withMessages(['target_user_id' => 'لا يمكن تعيين المدير لنفسه من هذا الإجراء.']);
        }

        $classes = $this->classesFor($department);
        $reassignmentClass = $this->reassignmentClassFor($department);

        return DB::transaction(function () use ($classes, $reassignmentClass, $requestId, $target, $actor, $department, $validated): Model {
            $request = $classes['request']::query()->lockForUpdate()->findOrFail($requestId);
            if ($request->status === RequestStatus::Closed) {
                throw ValidationException::withMessages(['request' => 'لا يمكن إعادة تعيين طلب مغلق.']);
            }
            if ((int) $request->owner_user_id === (int) $target->id) {
                throw ValidationException::withMessages(['target_user_id' => 'الموظف المختار مسؤول الطلب بالفعل.']);
            }
            if ($reassignmentClass::query()->where('request_id', $requestId)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['request' => 'احسم طلب إعادة التعيين المعلق قبل إعادة التعيين المباشرة.']);
            }

            $previousOwnerId = (int) $request->owner_user_id;
            $followUpAt = $request->next_follow_up_at?->toIso8601String();
            $request->owner_user_id = $target->id;
            $request->save();
            $this->recordAuditEvent($actor, $department, 'request.reassignment.direct', $request, [
                'owner_user_id' => $previousOwnerId,
                'next_follow_up_at' => $followUpAt,
            ], [
                'owner_user_id' => $target->id,
                'next_follow_up_at' => $followUpAt,
            ], $validated['reason']);

            return $request;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateDetails(User $actor, Department $department, int $requestId, array $data): Model
    {
        $this->authorizeDepartment($actor, $department);
        $classes = $this->classesFor($department);
        $request = $classes['request']::query()->findOrFail($requestId);

        $editable = [
            'customer_id', 'broker_id', 'area_id', 'lead_source_id', 'lead_source_other', 'contact_party',
            'unit_type', 'unit_type_other', 'budget_declared', 'budget_targeted', 'budget_currency',
            'other_requirements', 'notes',
        ];
        $editable = $department === Department::Rental
            ? [...$editable, 'bedrooms', 'occupants', 'household', 'rental_term', 'rental_term_count', 'finishing', 'finishing_other', 'floor_preference', 'elevator_required']
            : [...$editable, 'area_min', 'area_max'];
        $data = array_intersect_key($data, array_flip($editable));
        $data = array_merge(array_fill_keys($editable, null), $data);

        $rules = [
            'customer_id' => ['nullable', 'integer', Rule::exists($classes['customer']::query()->getModel()->getTable(), 'id')],
            'broker_id' => ['nullable', 'integer', Rule::exists($classes['broker']::query()->getModel()->getTable(), 'id')],
            'area_id' => ['required', 'integer', Rule::exists($classes['area_table'], 'id')],
            'lead_source_id' => ['nullable', 'integer', Rule::exists($classes['lead_source']::query()->getModel()->getTable(), 'id')],
            'lead_source_other' => ['nullable', 'string', 'max:255'],
            'contact_party' => ['nullable', Rule::enum(ContactParty::class)],
            'unit_type' => ['nullable', Rule::enum(RequestUnitType::class)],
            'unit_type_other' => ['nullable', 'string', 'max:255'],
            'budget_declared' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'budget_targeted' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'budget_currency' => ['nullable', Rule::in(['EGP', 'USD'])],
            'other_requirements' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];

        if ($department === Department::Rental) {
            $rules += [
                'bedrooms' => ['nullable', 'integer', 'min:1', 'max:100'],
                'occupants' => ['nullable', 'integer', 'min:1', 'max:1000'],
                'household' => ['nullable', 'boolean'],
                'rental_term' => ['nullable', Rule::enum(\App\Enums\RentalTerm::class)],
                'rental_term_count' => ['nullable', 'integer', 'min:1', 'max:3650'],
                'finishing' => ['nullable', Rule::enum(FinishingLevel::class)],
                'finishing_other' => ['nullable', 'string', 'max:255'],
                'floor_preference' => ['nullable', Rule::enum(FloorPreference::class)],
                'elevator_required' => ['nullable', 'boolean'],
            ];
        } else {
            $rules += [
                'area_min' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
                'area_max' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            ];
        }

        $validator = Validator::make($data, $rules);
        $validator->after(function ($validator) use ($data, $classes, $department, $request): void {
            $customerId = $data['customer_id'];
            $brokerId = $data['broker_id'];
            $contactParty = $data['contact_party'];

            if (! $customerId && ! $brokerId) {
                $validator->errors()->add('customer_id', 'يجب ربط الطلب بعميل معروف أو وسيط واحد على الأقل.');
            }
            if ($customerId && $brokerId && ! $contactParty) {
                $validator->errors()->add('contact_party', 'حدد العميل أو الوسيط كجهة الاتصال الحالية.');
            }
            if ($contactParty === ContactParty::Customer->value && ! $customerId) {
                $validator->errors()->add('contact_party', 'يجب ربط العميل قبل اختياره كجهة اتصال.');
            }
            if ($contactParty === ContactParty::Broker->value && ! $brokerId) {
                $validator->errors()->add('contact_party', 'يجب ربط الوسيط قبل اختياره كجهة اتصال.');
            }
            $currentCustomerIsArchived = $customerId
                && (int) $request->customer_id === (int) $customerId
                && $classes['customer']::withTrashed()->whereKey($customerId)->whereNotNull('deleted_at')->exists();
            if ($customerId && ! $classes['customer']::query()->whereKey($customerId)->exists() && ! $currentCustomerIsArchived) {
                $validator->errors()->add('customer_id', 'العميل غير موجود في هذا القسم.');
            }
            $currentAreaIsArchived = (int) $request->area_id === (int) ($data['area_id'] ?? 0)
                && $classes['area']::withTrashed()->whereKey($data['area_id'])->whereNotNull('deleted_at')->exists();
            if (! $classes['area']::query()->whereKey($data['area_id'])->exists() && ! $currentAreaIsArchived) {
                $validator->errors()->add('area_id', 'المنطقة غير متاحة للطلبات الجديدة.');
            }
            if ($brokerId && ! $classes['broker']::query()->whereKey($brokerId)->exists()) {
                $validator->errors()->add('broker_id', 'الوسيط غير موجود في هذا القسم.');
            }

            $sourceId = $data['lead_source_id'];
            $source = $sourceId ? $classes['lead_source']::query()->find($sourceId) : null;
            if ($sourceId && (! $source || (! $source->is_active && (int) $sourceId !== (int) $request->lead_source_id))) {
                $validator->errors()->add('lead_source_id', 'مصدر الوصول غير متاح للاستخدام.');
            }
            if ($source?->is_other && empty($data['lead_source_other'])) {
                $validator->errors()->add('lead_source_other', 'اكتب مصدر الوصول عند اختيار «أخرى».');
            }
            if (! $source?->is_other && ! empty($data['lead_source_other'])) {
                $validator->errors()->add('lead_source_other', 'يُستخدم هذا الوصف مع مصدر الوصول «أخرى» فقط.');
            }
            if ($data['unit_type'] === RequestUnitType::Other->value && empty($data['unit_type_other'])) {
                $validator->errors()->add('unit_type_other', 'اكتب نوع الوحدة عند اختيار «أخرى».');
            }
            if ($data['unit_type'] !== RequestUnitType::Other->value && ! empty($data['unit_type_other'])) {
                $validator->errors()->add('unit_type_other', 'يُستخدم هذا الوصف مع نوع الوحدة «أخرى» فقط.');
            }
            if ($department === Department::Rental) {
                if ($data['finishing'] === FinishingLevel::Other->value && empty($data['finishing_other'])) {
                    $validator->errors()->add('finishing_other', 'اكتب مستوى التشطيب عند اختيار «أخرى».');
                }
                if ($data['finishing'] !== FinishingLevel::Other->value && ! empty($data['finishing_other'])) {
                    $validator->errors()->add('finishing_other', 'يُستخدم هذا الوصف مع مستوى التشطيب «أخرى» فقط.');
                }
            } elseif ($data['area_min'] && $data['area_max'] && $data['area_min'] > $data['area_max']) {
                $validator->errors()->add('area_max', 'يجب ألا تقل المساحة القصوى عن المساحة الدنيا.');
            }
        });

        $attributes = $validator->validate();
        if (! $attributes['contact_party']) {
            $attributes['contact_party'] = $attributes['customer_id'] ? ContactParty::Customer : ContactParty::Broker;
        }
        $attributes['budget_currency'] = ($attributes['budget_declared'] || $attributes['budget_targeted'])
            ? ($attributes['budget_currency'] ?: 'EGP')
            : null;

        $before = [];
        $after = [];
        foreach ($attributes as $key => $value) {
            $current = $request->getAttribute($key);
            $current = $current instanceof \BackedEnum ? $current->value : $current;
            $normalized = $value instanceof \BackedEnum ? $value->value : $value;
            if ((string) ($current ?? '') !== (string) ($normalized ?? '')) {
                $before[$key] = $current;
                $after[$key] = $normalized;
            }
        }

        if ($after === []) {
            return $request;
        }

        return DB::transaction(function () use ($request, $attributes, $before, $after, $department, $actor): Model {
            $request->fill($attributes);
            $request->save();
            AuditEvent::query()->create([
                'actor_user_id' => $actor->id,
                'department' => $department->value,
                'event_key' => 'request.details_updated',
                'subject_type' => $request::class,
                'subject_id' => $request->getKey(),
                'before_values' => $this->auditSnapshot($before),
                'after_values' => $this->auditSnapshot($after),
                'reason' => null,
                'created_at' => now(),
            ]);

            return $request;
        });
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, Department $department, array $data): Model
    {
        $this->authorizeDepartment($actor, $department);

        $classes = $this->classesFor($department);
        $data['owner_user_id'] ??= $actor->id;

        $rules = [
            'customer_id' => ['nullable', 'integer', Rule::exists($classes['customer']::query()->getModel()->getTable(), 'id')],
            'broker_id' => ['nullable', 'integer', Rule::exists($classes['broker']::query()->getModel()->getTable(), 'id')],
            'area_id' => ['required', 'integer', Rule::exists($classes['area_table'], 'id')],
            'lead_source_id' => ['nullable', 'integer', Rule::exists($classes['lead_source']::query()->getModel()->getTable(), 'id')],
            'lead_source_other' => ['nullable', 'string', 'max:255'],
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'contact_party' => ['nullable', Rule::enum(ContactParty::class)],
            'next_follow_up_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'budget_declared' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'budget_targeted' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'budget_currency' => ['sometimes', Rule::in(['EGP', 'USD'])],
        ];

        if ($department === Department::Rental) {
            $rules += [
                'unit_type' => ['nullable', Rule::enum(RequestUnitType::class)],
                'unit_type_other' => ['nullable', 'string', 'max:255'],
                'bedrooms' => ['nullable', 'integer', 'min:1', 'max:100'],
                'occupants' => ['nullable', 'integer', 'min:1', 'max:1000'],
                'household' => ['nullable', 'boolean'],
                'rental_term' => ['nullable', Rule::enum(\App\Enums\RentalTerm::class)],
                'rental_term_count' => ['nullable', 'integer', 'min:1', 'max:3650'],
                'finishing' => ['nullable', Rule::enum(FinishingLevel::class)],
                'finishing_other' => ['nullable', 'string', 'max:255'],
                'floor_preference' => ['nullable', Rule::enum(FloorPreference::class)],
                'elevator_required' => ['nullable', 'boolean'],
                'other_requirements' => ['nullable', 'string', 'max:10000'],
            ];
        } else {
            $rules += [
                'unit_type' => ['nullable', Rule::enum(RequestUnitType::class)],
                'area_min' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
                'area_max' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
                'other_requirements' => ['nullable', 'string', 'max:10000'],
            ];
        }

        $validator = Validator::make($data, $rules);
        $validator->after(function ($validator) use ($data, $actor, $department, $classes): void {
            $customerId = $data['customer_id'] ?? null;
            $brokerId = $data['broker_id'] ?? null;
            $contactParty = $data['contact_party'] ?? null;

            if (! $customerId && ! $brokerId) {
                $validator->errors()->add('customer_id', 'يجب ربط الطلب بعميل معروف أو وسيط واحد على الأقل.');
            }

            if ($customerId && $brokerId && ! $contactParty) {
                $validator->errors()->add('contact_party', 'حدد العميل أو الوسيط كجهة الاتصال الحالية.');
            }

            if ($contactParty === ContactParty::Customer->value && ! $customerId) {
                $validator->errors()->add('contact_party', 'يجب ربط العميل قبل اختياره كجهة اتصال.');
            }

            if ($contactParty === ContactParty::Broker->value && ! $brokerId) {
                $validator->errors()->add('contact_party', 'يجب ربط الوسيط قبل اختياره كجهة اتصال.');
            }

            if (($data['area_min'] ?? null) && ($data['area_max'] ?? null) && $data['area_min'] > $data['area_max']) {
                $validator->errors()->add('area_max', 'يجب ألا تقل المساحة القصوى عن المساحة الدنيا.');
            }

            $ownerId = (int) ($data['owner_user_id'] ?? 0);
            $owner = User::query()->find($ownerId);
            if ($owner && ! $owner->canWorkInDepartment($department)) {
                $validator->errors()->add('owner_user_id', 'المسؤول المختار ليس عضوًا في هذا القسم.');
            }
            if ($ownerId !== (int) $actor->id && ! $actor->canManageDepartment($department)) {
                $validator->errors()->add('owner_user_id', 'يمكن للموظف إسناد الطلب لنفسه فقط.');
            }

            // These classes are department-specific; validating against them prevents cross-section links.
            if ($customerId && ! $classes['customer']::query()->whereKey($customerId)->exists()) {
                $validator->errors()->add('customer_id', 'العميل غير موجود في هذا القسم.');
            }
            if (! $classes['area']::query()->whereKey($data['area_id'])->exists()) {
                $validator->errors()->add('area_id', 'المنطقة غير متاحة للطلبات الجديدة.');
            }
            if ($brokerId && ! $classes['broker']::query()->whereKey($brokerId)->exists()) {
                $validator->errors()->add('broker_id', 'الوسيط غير موجود في هذا القسم.');
            }

            $leadSourceId = $data['lead_source_id'] ?? null;
            $leadSource = $leadSourceId ? $classes['lead_source']::query()->find($leadSourceId) : null;
            if ($leadSourceId && ! $leadSource?->is_active) {
                $validator->errors()->add('lead_source_id', 'مصدر الوصول غير متاح للاستخدام.');
            }
            if ($leadSource?->is_other && empty($data['lead_source_other'])) {
                $validator->errors()->add('lead_source_other', 'اكتب مصدر الوصول عند اختيار «أخرى».');
            }
            if (! $leadSource?->is_other && ! empty($data['lead_source_other'])) {
                $validator->errors()->add('lead_source_other', 'يُستخدم هذا الوصف مع مصدر الوصول «أخرى» فقط.');
            }

            if (($data['unit_type'] ?? null) === RequestUnitType::Other->value && empty($data['unit_type_other'])) {
                $validator->errors()->add('unit_type_other', 'اكتب نوع الوحدة عند اختيار «أخرى».');
            }
            if (($data['unit_type'] ?? null) !== RequestUnitType::Other->value && ! empty($data['unit_type_other'])) {
                $validator->errors()->add('unit_type_other', 'يُستخدم هذا الوصف مع نوع الوحدة «أخرى» فقط.');
            }
            if ($department === Department::Rental) {
                if (($data['finishing'] ?? null) === FinishingLevel::Other->value && empty($data['finishing_other'])) {
                    $validator->errors()->add('finishing_other', 'اكتب مستوى التشطيب عند اختيار «أخرى».');
                }
                if (($data['finishing'] ?? null) !== FinishingLevel::Other->value && ! empty($data['finishing_other'])) {
                    $validator->errors()->add('finishing_other', 'يُستخدم هذا الوصف مع مستوى التشطيب «أخرى» فقط.');
                }
            }
        });

        $attributes = $validator->validate();
        $modelClass = $classes['request'];
        $request = new $modelClass;
        $request->fill($attributes);
        $request->status = RequestStatus::Active;
        $request->stage = $department === Department::Rental
            ? RentalRequestStage::Intake
            : ResaleRequestStage::Intake;
        if (! empty($attributes['budget_declared']) || ! empty($attributes['budget_targeted'])) {
            $request->budget_currency ??= 'EGP';
        } else {
            $request->budget_currency = null;
        }

        if (! $request->contact_party) {
            $request->contact_party = $request->customer_id
                ? ContactParty::Customer
                : ContactParty::Broker;
        }

        return DB::transaction(function () use ($request, $department, $actor): Model {
            $request->save();
            $this->recordStageChange($department, $request, null, 'intake', $actor);
            $this->recordAuditEvent($actor, $department, 'request.created', $request, null, [
                'status' => RequestStatus::Active->value,
                'stage' => $request->stage->value,
                'customer_id' => $request->customer_id,
                'broker_id' => $request->broker_id,
                'owner_user_id' => $request->owner_user_id,
            ], null);

            return $request;
        });
    }

    public function linkCustomer(User $actor, Department $department, int $requestId, int $customerId, bool $makeContact = false): Model
    {
        $this->authorizeDepartment($actor, $department);
        $classes = $this->classesFor($department);
        $request = $classes['request']::query()->findOrFail($requestId);

        if (! $classes['customer']::query()->whereKey($customerId)->exists()) {
            throw ValidationException::withMessages(['customer_id' => 'العميل غير موجود في هذا القسم.']);
        }

        if ($request->customer_id && (int) $request->customer_id !== $customerId) {
            throw ValidationException::withMessages(['customer_id' => 'الطلب مرتبط بالفعل بعميل آخر؛ لا يمكن استبدال العميل.']);
        }
        if ((int) $request->customer_id === $customerId && (! $makeContact || $request->contact_party === ContactParty::Customer)) {
            return $request;
        }

        $before = [
            'customer_id' => $request->customer_id,
            'contact_party' => $request->contact_party->value,
        ];

        return DB::transaction(function () use ($request, $customerId, $makeContact, $before, $actor, $department): Model {
            $request->customer_id = $customerId;
            if ($makeContact) {
                $request->contact_party = ContactParty::Customer;
            }
            $request->save();

            $deal = $request->deal;
            if ($deal) {
                $deal->customer_id = $customerId;
                $deal->save();
                app(AftercareService::class)->startForDeal($actor, $department, $deal);
            }

            AuditEvent::query()->create([
                'actor_user_id' => $actor->id,
                'department' => $department->value,
                'event_key' => 'request.customer_linked',
                'subject_type' => $request::class,
                'subject_id' => $request->getKey(),
                'before_values' => $before,
                'after_values' => [
                    'customer_id' => $customerId,
                    'contact_party' => $request->contact_party->value,
                ],
                'reason' => null,
                'created_at' => now(),
            ]);

            return $request;
        });
    }

    public function setNextFollowUp(User $actor, Department $department, int $requestId, string $nextFollowUpAt): Model
    {
        $this->authorizeDepartment($actor, $department);
        $validated = Validator::make(['next_follow_up_at' => $nextFollowUpAt], [
            'next_follow_up_at' => ['required', 'date', 'after:now'],
        ])->validate();
        $classes = $this->classesFor($department);
        $request = $classes['request']::query()->findOrFail($requestId);

        if ($request->status !== RequestStatus::Active) {
            throw ValidationException::withMessages(['next_follow_up_at' => 'يُحدد موعد المتابعة للطلب النشط فقط.']);
        }

        $before = $request->next_follow_up_at?->toDateTimeString();
        DB::transaction(function () use ($request, $validated, $before, $department, $actor): void {
            $request->next_follow_up_at = $validated['next_follow_up_at'];
            $request->save();
            $this->recordAuditEvent($actor, $department, 'request.follow_up_changed', $request,
                ['next_follow_up_at' => $before],
                ['next_follow_up_at' => $request->next_follow_up_at?->toDateTimeString()],
                null,
            );
        });

        return $request;
    }

    public function changeStage(User $actor, Department $department, int $requestId, string $stage, ?string $notes = null): Model
    {
        $this->authorizeDepartment($actor, $department);
        $classes = $this->classesFor($department);
        $stageEnum = $department === Department::Rental ? RentalRequestStage::class : ResaleRequestStage::class;
        $validated = Validator::make(['stage' => $stage, 'notes' => $notes], [
            'stage' => ['required', Rule::enum($stageEnum)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ])->validate();
        $request = $classes['request']::query()->findOrFail($requestId);

        if ($request->status !== RequestStatus::Active) {
            throw ValidationException::withMessages(['stage' => 'يمكن تغيير مرحلة الطلب النشط فقط.']);
        }

        $from = $request->stage->value;
        if ($from === $validated['stage']) {
            return $request;
        }

        return DB::transaction(function () use ($request, $validated, $department, $actor, $from): Model {
            $request->stage = $validated['stage'];
            $request->save();
            $this->recordStageChange($department, $request, $from, $validated['stage'], $actor, $validated['notes'] ?? null);

            return $request;
        });
    }

    public function pause(User $actor, Department $department, int $requestId, ?string $notes = null): Model
    {
        $this->authorizeDepartment($actor, $department);
        $notes = Validator::make(['notes' => $notes], ['notes' => ['nullable', 'string', 'max:10000']])->validate()['notes'] ?? null;
        $classes = $this->classesFor($department);
        $request = $classes['request']::query()->findOrFail($requestId);

        if ($request->status !== RequestStatus::Active) {
            throw ValidationException::withMessages(['status' => 'يمكن إيقاف الطلب النشط مؤقتًا فقط.']);
        }

        return DB::transaction(function () use ($request, $department, $actor, $notes): Model {
            $request->status = RequestStatus::Paused;
            $request->paused_at = now();
            $request->next_follow_up_at = null;
            $request->save();
            $this->recordStatusChange($department, $request, RequestStatus::Active, RequestStatus::Paused, $actor, $notes);

            return $request;
        });
    }

    public function resume(User $actor, Department $department, int $requestId, string $nextFollowUpAt, ?string $notes = null): Model
    {
        $this->authorizeDepartment($actor, $department);
        $validated = Validator::make(['next_follow_up_at' => $nextFollowUpAt, 'notes' => $notes], [
            'next_follow_up_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ])->validate();
        $classes = $this->classesFor($department);
        $request = $classes['request']::query()->findOrFail($requestId);

        if ($request->status !== RequestStatus::Paused) {
            throw ValidationException::withMessages(['status' => 'يمكن استئناف الطلب المتوقف مؤقتًا فقط.']);
        }

        return DB::transaction(function () use ($request, $validated, $department, $actor): Model {
            $request->status = RequestStatus::Active;
            $request->paused_at = null;
            $request->next_follow_up_at = $validated['next_follow_up_at'];
            $request->save();
            $this->recordStatusChange($department, $request, RequestStatus::Paused, RequestStatus::Active, $actor, $validated['notes'] ?? null);

            return $request;
        });
    }

    /** @param array{outcome: string, outcome_reason_text?: string|null, outcome_reason_id?: int|null, outcome_notes?: string|null} $data */
    public function close(User $actor, Department $department, int $requestId, array $data): Model
    {
        $this->authorizeDepartment($actor, $department);
        $classes = $this->classesFor($department);
        $validated = Validator::make($data, [
            'outcome' => ['required', Rule::enum(RequestOutcome::class)],
            'outcome_reason_id' => ['nullable', 'integer', Rule::exists($classes['close_reason']::query()->getModel()->getTable(), 'id')],
            'outcome_reason_text' => ['nullable', 'string', 'max:10000'],
            'outcome_notes' => ['nullable', 'string', 'max:10000'],
        ])->validate();

        $reason = isset($validated['outcome_reason_id'])
            ? $classes['close_reason']::query()->where('is_active', true)->find($validated['outcome_reason_id'])
            : null;

        if (! empty($validated['outcome_reason_id']) && ! $reason) {
            throw ValidationException::withMessages(['outcome_reason_id' => 'تصنيف سبب الإغلاق غير متاح.']);
        }

        if ($validated['outcome'] === RequestOutcome::Unsuccessful->value && blank($validated['outcome_reason_text'] ?? null)) {
            throw ValidationException::withMessages(['outcome_reason_text' => 'اكتب سبب إغلاق الطلب دون نجاح.']);
        }
        if ($validated['outcome'] === RequestOutcome::Successful->value && (! empty($validated['outcome_reason_id']) || ! empty($validated['outcome_reason_text']))) {
            throw ValidationException::withMessages(['outcome_reason_text' => 'لا يسجل سبب إغلاق غير ناجح عند نجاح الطلب.']);
        }

        $request = $classes['request']::query()->findOrFail($requestId);
        if ($request->status === RequestStatus::Closed) {
            throw ValidationException::withMessages(['status' => 'الطلب مغلق بالفعل.']);
        }
        $reassignmentClass = $this->reassignmentClassFor($department);
        if ($reassignmentClass::query()->where('request_id', $requestId)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['status' => 'احسم طلب إعادة التعيين المعلق قبل إغلاق الطلب.']);
        }

        return DB::transaction(function () use ($request, $validated, $department, $actor): Model {
            $from = $request->status;
            $request->status = RequestStatus::Closed;
            $request->closed_at = now();
            $request->next_follow_up_at = null;
            $request->outcome = $validated['outcome'];
            $request->outcome_reason_id = $validated['outcome_reason_id'] ?? null;
            $request->outcome_reason_text = $validated['outcome_reason_text'] ?? null;
            $request->outcome_reason_other = null;
            $request->outcome_notes = $validated['outcome_notes'] ?? null;
            $request->save();
            $historyNotes = trim(implode("\n\n", array_filter([
                $validated['outcome_reason_text'] ?? null,
                $validated['outcome_notes'] ?? null,
            ])));
            $this->recordStatusChange($department, $request, $from, RequestStatus::Closed, $actor, $historyNotes !== '' ? $historyNotes : null);

            return $request;
        });
    }

    private function authorizeDepartment(User $actor, Department $department): void
    {
        if (! $actor->canWorkInDepartment($department)) {
            throw new AuthorizationException('لا تملك صلاحية الوصول إلى هذا القسم.');
        }
    }

    /** @return array{request: class-string<Model>, customer: class-string<Model>, broker: class-string<Model>, area_table: string, lead_source: class-string<Model>, close_reason: class-string<Model>} */
    private function classesFor(Department $department): array
    {
        return match ($department) {
            Department::Rental => [
                'request' => RentalRequest::class,
                'customer' => RentalCustomer::class,
                'broker' => RentalBroker::class,
                'area_table' => 'rental_areas',
                'area' => RentalArea::class,
                'lead_source' => RentalLeadSource::class,
                'close_reason' => RentalRequestCloseReason::class,
            ],
            Department::Resale => [
                'request' => ResaleRequest::class,
                'customer' => ResaleCustomer::class,
                'broker' => ResaleBroker::class,
                'area_table' => 'resale_areas',
                'area' => ResaleArea::class,
                'lead_source' => ResaleLeadSource::class,
                'close_reason' => ResaleRequestCloseReason::class,
            ],
        };
    }

    private function recordStageChange(Department $department, Model $request, ?string $from, string $to, User $actor, ?string $notes = null): void
    {
        DB::table($department === Department::Rental ? 'rental_request_stage_histories' : 'resale_request_stage_histories')->insert([
            'request_id' => $request->getKey(),
            'from_stage' => $from,
            'to_stage' => $to,
            'changed_by' => $actor->id,
            'notes' => $notes,
            'created_at' => now(),
        ]);
        $this->recordAuditEvent($actor, $department, 'request.stage_changed', $request,
            ['stage' => $from], ['stage' => $to], null);
    }

    private function recordStatusChange(Department $department, Model $request, RequestStatus $from, RequestStatus $to, User $actor, ?string $notes = null): void
    {
        DB::table($department === Department::Rental ? 'rental_request_status_histories' : 'resale_request_status_histories')->insert([
            'request_id' => $request->getKey(),
            'from_status' => $from->value,
            'to_status' => $to->value,
            'changed_by' => $actor->id,
            'notes' => $notes,
            'created_at' => now(),
        ]);
        $this->recordAuditEvent($actor, $department, 'request.status_changed', $request,
            ['status' => $from->value],
            ['status' => $to->value, ...($to === RequestStatus::Closed ? ['outcome' => $request->outcome?->value] : [])],
            null,
        );
    }

    private function reassignmentClassFor(Department $department): string
    {
        return match ($department) {
            Department::Rental => RentalRequestReassignment::class,
            Department::Resale => ResaleRequestReassignment::class,
        };
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function recordAuditEvent(
        User $actor,
        Department $department,
        string $eventKey,
        Model $request,
        ?array $before,
        array $after,
        ?string $reason,
    ): void {
        AuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'department' => $department->value,
            'event_key' => $eventKey,
            'subject_type' => $request::class,
            'subject_id' => $request->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed>|null $values @return array<string, mixed>|null */
    private function auditSnapshot(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (['notes', 'other_requirements', 'outcome_reason_text', 'outcome_notes', 'lead_source_other', 'unit_type_other', 'finishing_other'] as $field) {
            if (array_key_exists($field, $values) && filled($values[$field])) {
                $values[$field] = '[تغير النص؛ المحتوى محفوظ في سجل الطلب]';
            }
        }

        return $values;
    }
}
