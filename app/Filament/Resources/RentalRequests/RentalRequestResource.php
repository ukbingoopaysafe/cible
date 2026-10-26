<?php

namespace App\Filament\Resources\RentalRequests;

use App\Enums\Department;
use App\Filament\Resources\RentalRequests\Pages\CreateRentalRequest;
use App\Filament\Resources\RentalRequests\Pages\EditRentalRequest;
use App\Filament\Resources\RentalRequests\Pages\ListRentalRequests;
use App\Filament\Resources\RentalRequests\Pages\ViewRentalRequest;
use App\Filament\Support\SectionRequestResource;
use App\Models\RentalRequest;
use Filament\Resources\Pages\PageRegistration;

class RentalRequestResource extends SectionRequestResource
{
    protected static ?string $model = RentalRequest::class;

    protected static ?string $modelLabel = 'طلب إيجار';

    protected static ?string $pluralModelLabel = 'طلبات الإيجار';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string | \UnitEnum | null $navigationGroup = 'الإيجار';

    protected static ?int $navigationSort = 10;

    public static function department(): Department
    {
        return Department::Rental;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListRentalRequests::route('/'),
            'create' => CreateRentalRequest::route('/create'),
            'edit' => EditRentalRequest::route('/{record}/edit'),
            'view' => ViewRentalRequest::route('/{record}'),
        ];
    }
}
