<?php

namespace App\Console\Commands;

use App\Enums\Department;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateUserCommand extends Command
{
    protected $signature = 'cible:user:create';

    protected $description = 'Create an internal Cible user with explicit department access';

    public function handle(): int
    {
        $name = trim((string) $this->ask('اسم المستخدم'));
        $email = mb_strtolower(trim((string) $this->ask('البريد الإلكتروني')));

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('الاسم والبريد الإلكتروني الصحيح مطلوبان.');

            return self::INVALID;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->components->error('يوجد مستخدم بهذا البريد الإلكتروني بالفعل.');

            return self::INVALID;
        }

        $password = (string) $this->secret('كلمة المرور (12 حرفًا على الأقل)');

        if (mb_strlen($password) < 12 || $password !== $this->secret('أعد كتابة كلمة المرور')) {
            $this->components->error('كلمة المرور غير مطابقة أو أقصر من 12 حرفًا.');

            return self::INVALID;
        }

        $role = $this->choice('نوع الحساب', ['موظف', 'مدير الشركة']);
        $departmentOptions = [
            Department::Rental->label(),
            Department::Resale->label(),
        ];
        $departments = $role === 'مدير الشركة'
            ? $departmentOptions
            : $this->choice('الأقسام المصرح بها', $departmentOptions, null, null, true);

        if ($departments === []) {
            $this->components->error('يجب اختيار قسم واحد على الأقل.');

            return self::INVALID;
        }

        $user = DB::transaction(function () use ($name, $email, $password, $role, $departments): User {
            $user = new User;
            $user->name = $name;
            $user->email = $email;
            $user->password = $password;
            $user->is_company_manager = $role === 'مدير الشركة';
            $user->save();

            foreach ($departments as $departmentLabel) {
                $department = match ($departmentLabel) {
                    Department::Rental->label() => Department::Rental,
                    Department::Resale->label() => Department::Resale,
                };

                $user->departmentMemberships()->create([
                    'department' => $department,
                ]);
            }

            return $user;
        });

        $this->components->info("تم إنشاء حساب {$user->email}.");

        return self::SUCCESS;
    }
}
