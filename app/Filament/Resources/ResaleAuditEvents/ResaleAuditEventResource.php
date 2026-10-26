<?php

namespace App\Filament\Resources\ResaleAuditEvents;

use App\Enums\Department;
use App\Filament\Resources\ResaleAuditEvents\Pages\ListResaleAuditEvents;
use App\Filament\Support\AuditEventResource;
use Filament\Resources\Pages\PageRegistration;

class ResaleAuditEventResource extends AuditEventResource
{
    protected static ?string $modelLabel = 'حدث تدقيق لإعادة البيع';
    protected static ?string $pluralModelLabel = 'سجل التدقيق — إعادة البيع';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string | \UnitEnum | null $navigationGroup = 'إعادة البيع';
    protected static ?int $navigationSort = 90;

    public static function department(): Department
    {
        return Department::Resale;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListResaleAuditEvents::route('/')];
    }
}
