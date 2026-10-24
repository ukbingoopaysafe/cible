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
use App\Models\AuditEvent;
use App\Models\RentalRequest;
use App\Models\RentalLeadSource;
use App\Models\RentalRequestCloseReason;
use App\Models\ResaleBroker;
use App\Models\ResaleCustomer;
use App\Models\ResaleRequest;
use App\Models\ResaleLeadSource;
use App\Models\ResaleRequestCloseReason;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequestService
{
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

        $request->next_follow_up_at = $validated['next_follow_up_at'];
        $request->save();

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
                'lead_source' => RentalLeadSource::class,
                'close_reason' => RentalRequestCloseReason::class,
            ],
            Department::Resale => [
                'request' => ResaleRequest::class,
                'customer' => ResaleCustomer::class,
                'broker' => ResaleBroker::class,
                'area_table' => 'resale_areas',
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
    }
}
