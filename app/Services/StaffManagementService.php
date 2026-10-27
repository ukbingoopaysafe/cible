<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffManagementService
{
    /** @return list<Department> */
    public function managedDepartments(User $actor): array
    {
        abort_unless($actor->is_company_manager, 403);

        return Department::cases();
    }

    public function createEmployee(User $actor, array $data): User
    {
        abort_unless($actor->is_company_manager, 403);
        $isManager = (bool) ($data['is_company_manager'] ?? false);
        $departments = $isManager ? [] : $this->validateDepartments($actor, $data['departments'] ?? []);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
            'is_company_manager' => ['sometimes', 'boolean'],
            'can_add_rental_areas' => ['sometimes', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($actor, $validated, $departments, $isManager): User {
            $user = User::query()->create([
                'name' => trim($validated['name']),
                'email' => mb_strtolower(trim($validated['email'])),
                'avatar_url' => $validated['avatar_url'] ?? null,
                'password' => $validated['password'],
            ]);
            $user->is_company_manager = $isManager;
            $user->can_add_rental_areas = ! $isManager
                && (bool) ($validated['can_add_rental_areas'] ?? false);
            $user->save();

            if ($isManager) {
                $this->recordAudit($actor, Department::Rental, 'staff.manager_created', $user, null, [
                    'name' => $user->name,
                    'is_company_manager' => true,
                    'email_set' => true,
                ]);
            } else {
                foreach ($departments as $department) {
                    $user->departmentMemberships()->create(['department' => $department]);
                    $this->recordAudit($actor, $department, 'staff.employee_created', $user, null, [
                        'name' => $user->name,
                        'department_access' => true,
                        'email_set' => true,
                        'can_add_rental_areas' => $user->can_add_rental_areas,
                    ]);
                }
            }

            return $user;
        });
    }

    public function updateEmployee(User $actor, int $userId, array $data): User
    {
        abort_unless($actor->is_company_manager, 403);
        $managed = $this->managedDepartments($actor);
        $user = User::query()->with('departmentMemberships')->findOrFail($userId);

        if ($actor->is($user) && isset($data['is_company_manager']) && ! (bool) $data['is_company_manager']) {
            throw ValidationException::withMessages([
                'is_company_manager' => 'لا يمكنك إلغاء صلاحية المدير عن حسابك لتجنب فقدان صلاحية الوصول للإدارة.',
            ]);
        }

        $isManager = isset($data['is_company_manager'])
            ? (bool) $data['is_company_manager']
            : (bool) $user->is_company_manager;

        $departments = $isManager
            ? []
            : $this->validateDepartments($actor, $data['departments'] ?? [], allowEmpty: true);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:12'],
            'is_company_manager' => ['sometimes', 'boolean'],
            'can_add_rental_areas' => ['sometimes', 'boolean'],
        ])->validate();

        $beforeName = $user->name;
        $beforeEmail = $user->email;
        $beforeIsManager = (bool) $user->is_company_manager;
        $beforeDepartments = $user->departmentMemberships->pluck('department')->all();
        $beforeCanAddRentalAreas = $user->can_add_rental_areas;
        $passwordChanged = filled($validated['password'] ?? null);

        return DB::transaction(function () use (
            $actor, $user, $validated, $departments, $managed, $isManager,
            $beforeName, $beforeEmail, $beforeIsManager, $beforeDepartments, $beforeCanAddRentalAreas, $passwordChanged
        ): User {
            $user->name = trim($validated['name']);
            $user->email = mb_strtolower(trim($validated['email']));
            $user->is_company_manager = $isManager;
            $user->can_add_rental_areas = ! $isManager
                && (bool) ($validated['can_add_rental_areas'] ?? false);

            if (array_key_exists('avatar_url', $validated)) {
                $user->avatar_url = $validated['avatar_url'];
            }

            if (filled($validated['password'] ?? null)) {
                $user->password = $validated['password'];
            }
            $user->save();

            if ($isManager) {
                $user->departmentMemberships()->delete();
                $this->recordAudit($actor, Department::Rental, 'staff.employee_updated', $user, [
                    'name' => $beforeName,
                    'is_company_manager' => $beforeIsManager,
                    'email_changed' => false,
                    'password_changed' => false,
                ], [
                    'name' => $user->name,
                    'is_company_manager' => true,
                    'email_changed' => $beforeEmail !== $user->email,
                    'password_changed' => $passwordChanged,
                ]);
            } else {
                foreach ($managed as $department) {
                    $membership = $user->departmentMemberships->firstWhere('department', $department);
                    if (in_array($department, $departments, true) && ! $membership) {
                        $user->departmentMemberships()->create(['department' => $department]);
                    } elseif (! in_array($department, $departments, true) && $membership) {
                        $membership->delete();
                    }
                }

                if (! $user->departmentMemberships()->exists()) {
                    throw ValidationException::withMessages(['departments' => 'يجب أن يظل الموظف تابعًا لقسم واحد على الأقل.']);
                }

                foreach ($managed as $department) {
                    $hasBefore = in_array($department->value, $beforeDepartments, true);
                    $hasAfter = $user->departmentMemberships()->where('department', $department->value)->exists();
                    $before = [
                        'name' => $beforeName,
                        'department_access' => $hasBefore,
                        'email_changed' => false,
                        'password_changed' => false,
                        'can_add_rental_areas' => $beforeCanAddRentalAreas,
                    ];
                    $after = [
                        'name' => $user->name,
                        'department_access' => $hasAfter,
                        'email_changed' => $beforeEmail !== $user->email,
                        'password_changed' => $passwordChanged,
                        'can_add_rental_areas' => $user->can_add_rental_areas,
                    ];
                    if ($before !== $after) {
                        $this->recordAudit($actor, $department, 'staff.employee_updated', $user, $before, $after);
                    }
                }
            }

            return $user->refresh();
        });
    }

    public function disableMfa(User $actor, int $userId): User
    {
        abort_unless($actor->is_company_manager, 403);
        $user = User::query()->findOrFail($userId);
        $user->disableMultiFactorAuthentication();

        $this->recordAudit(
            actor: $actor,
            department: Department::Rental,
            event: 'staff.mfa_disabled',
            subject: $user,
            before: ['mfa_enabled' => true],
            after: ['mfa_enabled' => false],
        );

        return $user;
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function recordAudit(User $actor, Department $department, string $event, User $subject, ?array $before, array $after): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'department' => $department->value,
            'event_key' => $event,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'before_values' => $before,
            'after_values' => $after,
            'reason' => null,
            'created_at' => now(),
        ]);
    }

    /** @return list<Department> */
    private function validateDepartments(User $actor, array $values, bool $allowEmpty = false): array
    {
        $managed = $this->managedDepartments($actor);
        $departments = collect($values)->map(function ($value): Department {
            if ($value instanceof Department) {
                return $value;
            }
            try {
                return Department::from((string) $value);
            } catch (\ValueError) {
                throw ValidationException::withMessages(['departments' => 'القسم المحدد غير صالح.']);
            }
        })->unique()->values()->all();

        $managedValues = array_map(fn (Department $department): string => $department->value, $managed);
        $departmentValues = array_map(fn (Department $department): string => $department->value, $departments);
        if ((! $allowEmpty && $departments === []) || array_diff($departmentValues, $managedValues) !== []) {
            throw ValidationException::withMessages(['departments' => 'اختر قسمًا واحدًا على الأقل من الأقسام التي تديرها.']);
        }

        return $departments;
    }

    /** @param list<Department> $managed */
    private function authorizeEmployee(User $actor, User $user, array $managed): void
    {
        if ($actor->is($user) || $user->is_company_manager || ! $user->departmentMemberships->contains(
            fn ($membership): bool => in_array($membership->department, $managed, true),
        )) {
            throw new AuthorizationException;
        }
    }
}
