<?php

namespace App\Services;

use App\Enums\Department;
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

        return $actor->departmentMemberships()->pluck('department')
            ->map(fn (string $value): Department => Department::from($value))->all();
    }

    public function createEmployee(User $actor, array $data): User
    {
        $departments = $this->validateDepartments($actor, $data['departments'] ?? []);
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12'],
        ])->validate();

        return DB::transaction(function () use ($validated, $departments): User {
            $user = User::query()->create([
                'name' => trim($validated['name']),
                'email' => mb_strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'is_company_manager' => false,
            ]);
            foreach ($departments as $department) {
                $user->departmentMemberships()->create(['department' => $department]);
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
        ])->validate();

        return DB::transaction(function () use ($user, $validated, $departments, $managed): User {
            $user->name = trim($validated['name']);
            $user->email = mb_strtolower(trim($validated['email']));
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

            return $user->refresh();
        });
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
