<?php

namespace App\Filament\Resources\RentalLeadSources;

use App\Enums\Department;
use App\Filament\Resources\RentalLeadSources\Pages\ListRentalLeadSources;
use App\Filament\Support\LeadSourceResource;
use App\Models\RentalLeadSource;
use Filament\Resources\Pages\PageRegistration;

class RentalLeadSourceResource extends LeadSourceResource
{
    protected static ?string $model = RentalLeadSource::class;
    protected static ?string $modelLabel = 'مصدر وصول للإيجار';
    protected static ?string $pluralModelLabel = 'مصادر الوصول — الإيجار';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';
    protected static string | \UnitEnum | null $navigationGroup = 'الإيجار';
    protected static ?int $navigationSort = 30;

    public static function department(): Department
    {
        return Department::Rental;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListRentalLeadSources::route('/')];
    }
}
