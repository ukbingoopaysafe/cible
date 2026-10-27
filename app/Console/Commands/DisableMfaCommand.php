<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DisableMfaCommand extends Command
{
    protected $signature = 'cible:mfa:disable {email? : البريد الإلكتروني للمستخدم}';

    protected $description = 'Disable Multi-Factor Authentication (MFA) for a user account';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! $email) {
            $mfaUsers = User::query()
                ->whereNotNull('app_authentication_secret')
                ->get(['id', 'name', 'email', 'is_company_manager']);

            if ($mfaUsers->isEmpty()) {
                $this->components->info('لا توجد أي حسابات مفعل عليها المصادقة الثنائية (MFA) حاليًا.');

                return self::SUCCESS;
            }

            $this->table(
                ['ID', 'الاسم', 'البريد الإلكتروني', 'الدور'],
                $mfaUsers->map(fn (User $u): array => [
                    $u->id,
                    $u->name,
                    $u->email,
                    $u->is_company_manager ? 'مدير شركة' : 'موظف',
                ])
            );

            $choices = $mfaUsers->pluck('email')->all();
            $email = $this->choice('اختر الحساب الذي تريد إلغاء MFA له', $choices);
        }

        $user = User::query()->where('email', mb_strtolower(trim((string) $email)))->first();

        if (! $user) {
            $this->components->error("لم يتم العثور على مستخدم بالبريد: {$email}");

            return self::FAILURE;
        }

        if (! $user->hasMultiFactorAuthentication()) {
            $this->components->info("المصادقة الثنائية (MFA) غير مفعلة بالفعل لحساب {$user->email}.");

            return self::SUCCESS;
        }

        $user->disableMultiFactorAuthentication();

        $this->components->info("تم إلغاء وتعطيل المصادقة الثنائية (MFA) بنجاح لحساب {$user->name} ({$user->email}).");

        return self::SUCCESS;
    }
}
