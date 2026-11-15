<?php

namespace App\Services;

use App\Enums\Department;
use App\Enums\LeadSourceType;
use App\Models\AuditEvent;
use App\Models\RentalLeadSource;
use App\Models\ResaleLeadSource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadSourceService
{
    /** @param array{name: string, type: string} $data */
    public function create(User $actor, Department $department, array $data): Model
    {
        $this->authorizeManager($actor, $department);
        $class = $this->modelClass($department);
        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }
        $attributes = Validator::make($data, [
            'name' => ['required', 'string', 'max:100', Rule::unique($class::query()->getModel()->getTable(), 'name')],
            'type' => ['required', Rule::in(array_keys(LeadSourceType::managerCreatableOptions()))],
        ])->validate();
        if ($attributes['name'] === '' || mb_strtolower($attributes['name'], 'UTF-8') === 'أخرى') {
            throw ValidationException::withMessages(['name' => 'أدخل اسم مصدر صالحًا؛ خيار «أخرى» موجود بالفعل.']);
        }

        return DB::transaction(function () use ($class, $attributes, $actor, $department): Model {
            $source = new $class;
            $source->name = $attributes['name'];
            $source->type = $attributes['type'];
            $source->is_active = true;
            $source->save();

            $this->audit($actor, $department, 'lead_source.created', $source, null, [
                'name' => $source->name,
                'type' => $source->type->value,
                'is_active' => true,
            ]);

            return $source;
        });
    }

    public function classify(User $actor, Department $department, int $sourceId, string $type): Model
    {
        $this->authorizeManager($actor, $department);
        $class = $this->modelClass($department);
        $validated = Validator::make(['type' => $type], [
            'type' => ['required', Rule::in(array_keys(LeadSourceType::managerCreatableOptions()))],
        ])->validate();

        return DB::transaction(function () use ($class, $sourceId, $validated, $actor, $department): Model {
            $source = $class::query()->lockForUpdate()->findOrFail($sourceId);
            if ($source->type !== LeadSourceType::UnclassifiedLegacy) {
                throw ValidationException::withMessages(['type' => 'يمكن تصنيف المصادر القديمة غير المصنفة فقط.']);
            }

            $before = ['type' => $source->type->value];
            $source->type = $validated['type'];
            $source->save();
            $this->audit($actor, $department, 'lead_source.classified', $source, $before, [
                'type' => $source->type->value,
                'is_active' => (bool) $source->is_active,
            ]);

            return $source;
        });
    }

    public function setActive(User $actor, Department $department, int $sourceId, bool $active): Model
    {
        $this->authorizeManager($actor, $department);
        $source = $this->modelClass($department)::query()->findOrFail($sourceId);

        if ($source->type === LeadSourceType::Other && ! $active) {
            throw ValidationException::withMessages(['source' => 'يجب أن يظل خيار «أخرى» متاحًا.']);
        }
        if ($active && $source->type === LeadSourceType::LegacyContactMethod) {
            throw ValidationException::withMessages(['source' => 'لا يمكن إعادة تفعيل وسيلة تواصل تاريخية كمصدر وصول.']);
        }
        if ($active && $source->type === LeadSourceType::UnclassifiedLegacy) {
            throw ValidationException::withMessages(['source' => 'صنّف المصدر القديم قبل إعادة تفعيله.']);
        }

        if ((bool) $source->is_active === $active) {
            return $source;
        }

        return DB::transaction(function () use ($source, $active, $actor, $department): Model {
            $before = ['is_active' => (bool) $source->is_active];
            $source->is_active = $active;
            $source->save();
            $this->audit($actor, $department, 'lead_source.activation_changed', $source, $before, [
                'is_active' => $active,
            ]);

            return $source;
        });
    }

    private function audit(User $actor, Department $department, string $event, Model $subject, ?array $before, array $after): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'department' => $department->value,
            'event_key' => $event,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'reason' => null,
            'created_at' => now(),
        ]);
    }

    private function authorizeManager(User $actor, Department $department): void
    {
        if (! $actor->canManageDepartment($department)) {
            throw new AuthorizationException('إدارة مصادر الوصول متاحة لمدير القسم فقط.');
        }
    }

    /** @return class-string<RentalLeadSource|ResaleLeadSource> */
    private function modelClass(Department $department): string
    {
        return match ($department) {
            Department::Rental => RentalLeadSource::class,
            Department::Resale => ResaleLeadSource::class,
        };
    }
}
