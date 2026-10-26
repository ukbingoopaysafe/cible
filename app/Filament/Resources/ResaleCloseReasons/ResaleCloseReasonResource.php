<?php

namespace App\Filament\Resources\ResaleCloseReasons;

use App\Enums\Department;
use App\Filament\Resources\ResaleCloseReasons\Pages\ListResaleCloseReasons;
use App\Filament\Support\CloseReasonResource;
use App\Models\ResaleRequestCloseReason;
use Filament\Resources\Pages\PageRegistration;

class ResaleCloseReasonResource extends CloseReasonResource
{
    protected static ?string $model = ResaleRequestCloseReason::class;
    protected static ?string $modelLabel = 'تصنيف إغلاق إعادة البيع';
    protected static ?string $pluralModelLabel = 'تصنيفات إغلاق إعادة البيع';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';
    protected static string | \UnitEnum | null $navigationGroup = 'إعادة البيع';
    protected static ?int $navigationSort = 36;

    public static function department(): Department
    {
        return Department::Resale;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListResaleCloseReasons::route('/')];
    }
}
