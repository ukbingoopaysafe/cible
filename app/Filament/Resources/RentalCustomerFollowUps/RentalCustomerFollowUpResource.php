<?php

namespace App\Filament\Resources\RentalCustomerFollowUps;

use App\Enums\Department;
use App\Filament\Resources\RentalCustomerFollowUps\Pages\ListRentalCustomerFollowUps;
use App\Filament\Support\CustomerFollowUpResource;
use App\Models\RentalCustomerFollowUp;
use Filament\Resources\Pages\PageRegistration;

class RentalCustomerFollowUpResource extends CustomerFollowUpResource
{
    protected static ?string $model = RentalCustomerFollowUp::class;

    protected static ?string $slug = 'rental-customer-follow-ups';

    protected static ?string $modelLabel = 'متابعة العميل';

    protected static ?string $pluralModelLabel = 'متابعات العملاء';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|\UnitEnum|null $navigationGroup = 'الإيجار';

    protected static ?int $navigationSort = 15;

    public static function department(): Department
    {
        return Department::Rental;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListRentalCustomerFollowUps::route('/'),
        ];
    }
}
