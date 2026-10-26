<?php

namespace App\Filament\Resources\ResaleLeadSources;

use App\Enums\Department;
use App\Filament\Resources\ResaleLeadSources\Pages\ListResaleLeadSources;
use App\Filament\Support\LeadSourceResource;
use App\Models\ResaleLeadSource;
use Filament\Resources\Pages\PageRegistration;

class ResaleLeadSourceResource extends LeadSourceResource
{
    protected static ?string $model = ResaleLeadSource::class;
    protected static ?string $modelLabel = 'مصدر وصول لإعادة البيع';
    protected static ?string $pluralModelLabel = 'مصادر الوصول — إعادة البيع';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';
    protected static string | \UnitEnum | null $navigationGroup = 'إعادة البيع';
    protected static ?int $navigationSort = 30;

    public static function department(): Department
    {
        return Department::Resale;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListResaleLeadSources::route('/')];
    }
}
