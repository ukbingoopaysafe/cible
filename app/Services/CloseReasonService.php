<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\AuditEvent;
use App\Models\RentalRequest;
use App\Models\RentalRequestCloseReason;
use App\Models\ResaleRequest;
use App\Models\ResaleRequestCloseReason;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CloseReasonService
{
    /** @param array{name: string} $data */
    public function create(User $actor, Department $department, array $data): Model
    {
        $this->authorizeManager($actor, $department);
        $class = $this->modelClass($department);
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $attributes = Validator::make($data, [
            'name' => ['required', 'string', 'max:120', 'unique:'.$class::query()->getModel()->getTable().',name'],
        ])->validate();

        if ($attributes['name'] === '') {
            throw ValidationException::withMessages(['name' => 'اسم التصنيف مطلوب.']);
        }

        return DB::transaction(function () use ($actor, $department, $class, $attributes): Model {
            $reason = new $class;
            $reason->key = 'custom_'.Str::lower((string) Str::ulid());
            $reason->name = $attributes['name'];
            $reason->is_other = false;
            $reason->is_active = true;
            $reason->sort_order = (int) $class::query()->max('sort_order') + 10;
            $reason->save();

            $this->audit($actor, $department, 'close_reason.created', $reason, null, [
                'key' => $reason->key,
                'name' => $reason->name,
                'is_active' => true,
            ]);

            return $reason;
        });
    }

    public function setActive(User $actor, Department $department, int $reasonId, bool $active): Model
    {
        $this->authorizeManager($actor, $department);
        $reason = $this->modelClass($department)::query()->findOrFail($reasonId);

        if ((bool) $reason->is_active === $active) {
            return $reason;
        }

        return DB::transaction(function () use ($actor, $department, $reason, $active): Model {
            $before = ['is_active' => (bool) $reason->is_active];
            $reason->is_active = $active;
            $reason->save();
            $this->audit($actor, $department, 'close_reason.activation_changed', $reason, $before, [
                'is_active' => $active,
            ]);

            return $reason;
        });
    }

    public function updateName(User $actor, Department $department, int $reasonId, string $name): Model
    {
        $this->authorizeManager($actor, $department);
        $class = $this->modelClass($department);
        $reason = $class::query()->findOrFail($reasonId);
        $data = Validator::make(['name' => trim($name)], [
            'name' => ['required', 'string', 'max:120', Rule::unique($class::query()->getModel()->getTable(), 'name')->ignore($reasonId)],
        ])->validate();

        return DB::transaction(function () use ($actor, $department, $reason, $data): Model {
            $before = ['name' => $reason->name];
            $reason->name = $data['name'];
            $reason->save();
            $this->audit($actor, $department, 'close_reason.renamed', $reason, $before, ['name' => $reason->name]);

            return $reason;
        });
    }

    public function delete(User $actor, Department $department, int $reasonId): void
    {
        $this->authorizeManager($actor, $department);
        $class = $this->modelClass($department);
        $requestClass = $department === Department::Rental ? RentalRequest::class : ResaleRequest::class;

        DB::transaction(function () use ($actor, $department, $class, $requestClass, $reasonId): void {
            $reason = $class::query()->lockForUpdate()->findOrFail($reasonId);
            if ($requestClass::query()->where('outcome_reason_id', $reasonId)->exists()) {
                throw ValidationException::withMessages(['reason' => 'لا يمكن حذف تصنيف مرتبط بطلبات سابقة؛ عطّله بدلًا من ذلك.']);
            }

            $before = ['key' => $reason->key, 'name' => $reason->name, 'is_active' => (bool) $reason->is_active];
            $this->audit($actor, $department, 'close_reason.deleted', $reason, $before, []);
            $reason->delete();
        });
    }

    private function authorizeManager(User $actor, Department $department): void
    {
        if (! $actor->canManageDepartment($department)) {
            throw new AuthorizationException('إدارة تصنيفات الإغلاق متاحة لمدير القسم فقط.');
        }
    }

    /** @return class-string<RentalRequestCloseReason|ResaleRequestCloseReason> */
    private function modelClass(Department $department): string
    {
        return match ($department) {
            Department::Rental => RentalRequestCloseReason::class,
            Department::Resale => ResaleRequestCloseReason::class,
        };
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function audit(User $actor, Department $department, string $eventKey, Model $reason, ?array $before, array $after): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'department' => $department->value,
            'event_key' => $eventKey,
            'subject_type' => $reason::class,
            'subject_id' => $reason->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'reason' => null,
            'created_at' => now(),
        ]);
    }
}
