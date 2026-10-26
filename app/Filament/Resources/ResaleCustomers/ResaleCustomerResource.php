<?php

namespace App\Filament\Resources\ResaleCustomers;

use App\Enums\Department;
use App\Filament\Resources\ResaleCustomers\Pages\ListResaleCustomers;
use App\Filament\Support\CustomerResource;
use App\Models\ResaleCustomer;
use Filament\Resources\Pages\PageRegistration;

class ResaleCustomerResource extends CustomerResource
{
    protected static ?string $model = ResaleCustomer::class;
    protected static ?string $modelLabel = 'عميل إعادة بيع';
    protected static ?string $pluralModelLabel = 'عملاء إعادة البيع';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'إعادة البيع';
    protected static ?int $navigationSort = 5;

    public static function department(): Department
    {
        return Department::Resale;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListResaleCustomers::route('/')];
    }
}
