<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\RentalArea;
use App\Models\ResaleArea;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AreaArchiveService
{
    public function archive(User $actor, Department $department, int $areaId): void
    {
        $this->authorizeManager($actor, $department);
        $areaClass = $department === Department::Rental ? RentalArea::class : ResaleArea::class;

        DB::transaction(function () use ($actor, $department, $areaClass, $areaId): void {
            $area = $areaClass::withTrashed()->lockForUpdate()->findOrFail($areaId);
            if ($area->trashed()) {
                return;
            }

            $before = ['governorate' => $area->governorate, 'name' => $area->name];
            $area->delete();

            if (Schema::hasTable('areas')) {
                Area::query()
                    ->where('governorate', $area->governorate)
                    ->where('name', $area->name)
                    ->delete();
            }

            $this->recordAudit($actor, $department, 'area.archived', $area, $before, ['archived' => true]);
        });
    }

    public function archiveUnified(User $actor, int $areaId): void
    {
        abort_unless($actor->is_company_manager, 403);

        DB::transaction(function () use ($actor, $areaId): void {
            $area = Area::withTrashed()->lockForUpdate()->findOrFail($areaId);
            if ($area->trashed()) {
                return;
            }

            $before = ['governorate' => $area->governorate, 'name' => $area->name];
            $area->delete();

            if (Schema::hasTable('rental_areas')) {
                RentalArea::query()->where('governorate', $area->governorate)->where('name', $area->name)->delete();
            }
            if (Schema::hasTable('resale_areas')) {
                ResaleArea::query()->where('governorate', $area->governorate)->where('name', $area->name)->delete();
            }

            $this->recordAudit($actor, Department::Rental, 'area.archived', $area, $before, ['archived' => true]);
        });
    }

    public function restore(User $actor, Department $department, int $areaId): void
    {
        $this->authorizeManager($actor, $department);
        $areaClass = $department === Department::Rental ? RentalArea::class : ResaleArea::class;

        DB::transaction(function () use ($actor, $department, $areaClass, $areaId): void {
            $area = $areaClass::withTrashed()->lockForUpdate()->findOrFail($areaId);
            if (! $area->trashed()) {
                return;
            }

            $area->restore();

            if (Schema::hasTable('areas')) {
                Area::withTrashed()
                    ->where('governorate', $area->governorate)
                    ->where('name', $area->name)
                    ->restore();
            }

            $this->recordAudit($actor, $department, 'area.restored', $area, ['archived' => true], ['archived' => false]);
        });
    }

    public function restoreUnified(User $actor, int $areaId): void
    {
        abort_unless($actor->is_company_manager, 403);

        DB::transaction(function () use ($actor, $areaId): void {
            $area = Area::withTrashed()->lockForUpdate()->findOrFail($areaId);
            if (! $area->trashed()) {
                return;
            }

            $area->restore();

            if (Schema::hasTable('rental_areas')) {
                RentalArea::withTrashed()->where('governorate', $area->governorate)->where('name', $area->name)->restore();
            }
            if (Schema::hasTable('resale_areas')) {
                ResaleArea::withTrashed()->where('governorate', $area->governorate)->where('name', $area->name)->restore();
            }

            $this->recordAudit($actor, Department::Rental, 'area.restored', $area, ['archived' => true], ['archived' => false]);
        });
    }

    private function authorizeManager(User $actor, Department $department): void
    {
        if (! $actor->canManageDepartment($department)) {
            throw new AuthorizationException('أرشفة المناطق واستعادتها متاحة لمدير الشركة فقط.');
        }
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function recordAudit(User $actor, Department $department, string $event, Model $area, ?array $before, array $after): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'department' => $department->value,
            'event_key' => $event,
            'subject_type' => $area::class,
            'subject_id' => $area->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'reason' => null,
            'created_at' => now(),
        ]);
    }
}
