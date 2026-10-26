<?php

namespace App\Filament\Resources\RentalCloseReasons;

use App\Enums\Department;
use App\Filament\Resources\RentalCloseReasons\Pages\ListRentalCloseReasons;
use App\Filament\Support\CloseReasonResource;
use App\Models\RentalRequestCloseReason;
use Filament\Resources\Pages\PageRegistration;

class RentalCloseReasonResource extends CloseReasonResource
{
    protected static ?string $model = RentalRequestCloseReason::class;
    protected static ?string $modelLabel = 'تصنيف إغلاق الإيجار';
    protected static ?string $pluralModelLabel = 'تصنيفات إغلاق الإيجار';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';
    protected static string | \UnitEnum | null $navigationGroup = 'الإيجار';
    protected static ?int $navigationSort = 36;

    public static function department(): Department
    {
        return Department::Rental;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListRentalCloseReasons::route('/')];
    }
}
