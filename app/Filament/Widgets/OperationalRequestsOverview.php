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
        return 'ملخص الطلبات';
    }

    protected function getDescription(): ?string
    {
        return null;
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
                ->color('primary');
            $stats[] = Stat::make($department->label().' • متابعات متأخرة', (clone $activeQuery)->where('next_follow_up_at', '<', $now)->count())
                ->color('danger');
            $stats[] = Stat::make($department->label().' • متابعات اليوم', (clone $activeQuery)->whereBetween('next_follow_up_at', [$now, $now->copy()->endOfDay()])->count())
                ->color('warning');
            $stats[] = Stat::make($department->label().' • بانتظار ربط عميل', (clone $activeQuery)->whereNull('customer_id')->count())
                ->color('info');
            $stats[] = Stat::make($department->label().' • متوقفة مؤقتًا', (clone $baseQuery)->where('status', RequestStatus::Paused->value)->count())
                ->color('gray');
            $aftercareQuery = $aftercareClass::query();
            $stats[] = Stat::make($department->label().' • ما بعد الصفقة (متأخر)', (clone $aftercareQuery)->where('next_follow_up_at', '<', $now)->count())
                ->color('danger');
            $stats[] = Stat::make($department->label().' • ما بعد الصفقة (اليوم)', (clone $aftercareQuery)->whereBetween('next_follow_up_at', [$now, $now->copy()->endOfDay()])->count())
                ->color('warning');
        }

        return $stats;
    }
}
