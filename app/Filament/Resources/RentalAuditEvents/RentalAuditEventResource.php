<?php

namespace App\Filament\Resources\RentalAuditEvents;

use App\Enums\Department;
use App\Filament\Resources\RentalAuditEvents\Pages\ListRentalAuditEvents;
use App\Filament\Support\AuditEventResource;
use Filament\Resources\Pages\PageRegistration;

class RentalAuditEventResource extends AuditEventResource
{
    protected static ?string $modelLabel = 'حدث تدقيق للإيجار';
    protected static ?string $pluralModelLabel = 'سجل التدقيق — الإيجار';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string | \UnitEnum | null $navigationGroup = 'الإيجار';
    protected static ?int $navigationSort = 90;

    public static function department(): Department
    {
        return Department::Rental;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListRentalAuditEvents::route('/')];
    }
}
