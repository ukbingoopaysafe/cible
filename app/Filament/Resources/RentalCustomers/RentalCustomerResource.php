<?php

namespace App\Filament\Resources\RentalCustomers;

use App\Enums\Department;
use App\Filament\Resources\RentalCustomers\Pages\ListRentalCustomers;
use App\Filament\Support\CustomerResource;
use App\Models\RentalCustomer;
use Filament\Resources\Pages\PageRegistration;

class RentalCustomerResource extends CustomerResource
{
    protected static ?string $model = RentalCustomer::class;
    protected static ?string $modelLabel = 'عميل إيجار';
    protected static ?string $pluralModelLabel = 'عملاء الإيجار';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'الإيجار';
    protected static ?int $navigationSort = 5;

    public static function department(): Department
    {
        return Department::Rental;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListRentalCustomers::route('/')];
    }
}
