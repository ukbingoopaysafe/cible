<?php

namespace App\Filament\Widgets;

use App\Enums\Department;
use App\Enums\RequestStatus;
use App\Models\RentalAftercare;
use App\Models\RentalRequest;
use App\Models\ResaleAftercare;
use App\Models\ResaleRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationalRequestsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = [
        'default' => 1,
        'sm' => 2,
        'md' => 2,
        'lg' => 3,
        'xl' => 4,
    ];

    public static function canView(): bool
    {
        return auth()->user() instanceof User;
    }

    protected function getHeading(): ?string
    {
        return 'ملخص التشغيل حسب القسم';
    }

    protected function getDescription(): ?string
    {
        return 'مؤشرات الطلبات ومواعيد ما بعد الصفقة ضمن الأقسام المسندة إلى حسابك فقط.';
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return [];
        }

        $stats = [];
        foreach (Department::cases() as $department) {
            if (! $actor->canWorkInDepartment($department)) {
                continue;
            }

            $requestClass = $department === Department::Rental ? RentalRequest::class : ResaleRequest::class;
            $aftercareClass = $department === Department::Rental ? RentalAftercare::class : ResaleAftercare::class;
            $baseQuery = $requestClass::query();
            $activeQuery = (clone $baseQuery)->where('status', RequestStatus::Active->value);
            $now = now();

            $stats[] = Stat::make($department->label().' • طلبات نشطة', (clone $activeQuery)->count())
                ->description('تحتاج متابعة مستمرة')
                ->color('primary');
            $stats[] = Stat::make($department->label().' • متابعات متأخرة', (clone $activeQuery)->where('next_follow_up_at', '<', $now)->count())
                ->description('موعدها قبل الوقت الحالي')
                ->color('danger');
            $stats[] = Stat::make($department->label().' • متابعات اليوم', (clone $activeQuery)->whereBetween('next_follow_up_at', [$now, $now->copy()->endOfDay()])->count())
                ->description('من الآن حتى نهاية اليوم')
                ->color('warning');
            $stats[] = Stat::make($department->label().' • بانتظار ربط عميل', (clone $activeQuery)->whereNull('customer_id')->count())
                ->description('طلبات واردة قبل معرفة العميل')
                ->color('info');
            $stats[] = Stat::make($department->label().' • متوقفة مؤقتًا', (clone $baseQuery)->where('status', RequestStatus::Paused->value)->count())
                ->description('تُستأنف من مرحلتها السابقة')
                ->color('gray');
            $aftercareQuery = $aftercareClass::query();
            $stats[] = Stat::make($department->label().' • متابعات ما بعد الصفقة المتأخرة', (clone $aftercareQuery)->where('next_follow_up_at', '<', $now)->count())
                ->description('تواصل اختياري بعد صفقة ناجحة')
                ->color('danger');
            $stats[] = Stat::make($department->label().' • متابعات ما بعد الصفقة اليوم', (clone $aftercareQuery)->whereBetween('next_follow_up_at', [$now, $now->copy()->endOfDay()])->count())
                ->description('مواعيد اليوم حتى نهاية اليوم')
                ->color('warning');
        }

        return $stats;
    }
}
