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
        $departments = $this->validateDepartments($actor, $data['departments'] ?? []);
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12'],
            'can_add_rental_areas' => ['sometimes', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($actor, $validated, $departments): User {
            $user = User::query()->create([
                'name' => trim($validated['name']),
                'email' => mb_strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'is_company_manager' => false,
                'can_add_rental_areas' => in_array(Department::Rental, $departments, true)
                    && (bool) ($validated['can_add_rental_areas'] ?? false),
            ]);
            foreach ($departments as $department) {
                $user->departmentMemberships()->create(['department' => $department]);
                $this->recordAudit($actor, $department, 'staff.employee_created', $user, null, [
                    'name' => $user->name,
                    'department_access' => true,
                    'email_set' => true,
                    'can_add_rental_areas' => $department === Department::Rental ? $user->can_add_rental_areas : null,
                ]);
            }

            return $user;
        });
    }

    public function updateEmployee(User $actor, int $userId, array $data): User
    {
        $managed = $this->managedDepartments($actor);
        $user = User::query()->with('departmentMemberships')->findOrFail($userId);
        $this->authorizeEmployee($actor, $user, $managed);
        $departments = $this->validateDepartments($actor, $data['departments'] ?? [], allowEmpty: true);
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:12'],
            'can_add_rental_areas' => ['sometimes', 'boolean'],
        ])->validate();

        $beforeName = $user->name;
        $beforeEmail = $user->email;
        $beforeDepartments = $user->departmentMemberships->pluck('department')->all();
        $beforeCanAddRentalAreas = $user->can_add_rental_areas;
        $passwordChanged = filled($validated['password'] ?? null);

        return DB::transaction(function () use ($actor, $user, $validated, $departments, $managed, $beforeName, $beforeEmail, $beforeDepartments, $beforeCanAddRentalAreas, $passwordChanged): User {
            $user->name = trim($validated['name']);
            $user->email = mb_strtolower(trim($validated['email']));
            $user->can_add_rental_areas = in_array(Department::Rental, $departments, true)
                && (bool) ($validated['can_add_rental_areas'] ?? false);
            if (filled($validated['password'] ?? null)) {
                $user->password = $validated['password'];
            }
            $user->save();

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
                    'can_add_rental_areas' => $department === Department::Rental ? $beforeCanAddRentalAreas : null,
                ];
                $after = [
                    'name' => $user->name,
                    'department_access' => $hasAfter,
                    'email_changed' => $beforeEmail !== $user->email,
                    'password_changed' => $passwordChanged,
                    'can_add_rental_areas' => $department === Department::Rental ? $user->can_add_rental_areas : null,
                ];
                if ($before !== $after) {
                    $this->recordAudit($actor, $department, 'staff.employee_updated', $user, $before, $after);
                }
            }

            return $user->refresh();
        });
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
