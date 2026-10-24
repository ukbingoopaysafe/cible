<?php

namespace App\Filament\Resources\RentalBrokers;

use App\Enums\Department;
use App\Filament\Resources\RentalBrokers\Pages\ListRentalBrokers;
use App\Filament\Support\BrokerResource;
use App\Models\RentalBroker;
use Filament\Resources\Pages\PageRegistration;

class RentalBrokerResource extends BrokerResource
{
    protected static ?string $model = RentalBroker::class;
    protected static ?string $modelLabel = 'وسيط إيجار';
    protected static ?string $pluralModelLabel = 'وسطاء الإيجار';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static string | \UnitEnum | null $navigationGroup = 'الإيجار';
    protected static ?int $navigationSort = 20;

    public static function department(): Department
    {
        return Department::Rental;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListRentalBrokers::route('/')];
    }
}
