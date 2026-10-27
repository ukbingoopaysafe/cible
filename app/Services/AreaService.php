<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\RentalArea;
use App\Models\ResaleArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AreaService
{
    public function create(User $actor, array $data): Area
    {
        abort_unless($actor->is_company_manager || $actor->canAddRentalAreas(), 403);

        $data['name'] = trim((string) ($data['name'] ?? ''));

        $validated = Validator::make($data, [
            'governorate' => ['required', Rule::in(['القاهرة', 'الجيزة'])],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('areas', 'name')->where('governorate', $data['governorate'] ?? null),
            ],
        ], [
            'name.unique' => 'هذه المنطقة موجودة بالفعل داخل المحافظة المحددة.',
        ])->validate();

        return DB::transaction(function () use ($actor, $validated): Area {
            $area = Area::query()->create([
                'governorate' => $validated['governorate'],
                'name' => $validated['name'],
            ]);

            if (Schema::hasTable('rental_areas')) {
                RentalArea::query()->firstOrCreate(
                    ['governorate' => $validated['governorate'], 'name' => $validated['name']]
                );
            }
            if (Schema::hasTable('resale_areas')) {
                ResaleArea::query()->firstOrCreate(
                    ['governorate' => $validated['governorate'], 'name' => $validated['name']]
                );
            }

            AuditEvent::query()->create([
                'actor_user_id' => $actor->id,
                'department' => Department::Rental->value,
                'event_key' => 'area.created',
                'subject_type' => $area::class,
                'subject_id' => $area->id,
                'before_values' => null,
                'after_values' => ['governorate' => $area->governorate, 'name' => $area->name],
                'reason' => null,
                'created_at' => now(),
            ]);

            return $area;
        });
    }
}
