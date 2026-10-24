<?php

namespace App\Filament\Resources\ResaleBrokers;

use App\Enums\Department;
use App\Filament\Resources\ResaleBrokers\Pages\ListResaleBrokers;
use App\Filament\Support\BrokerResource;
use App\Models\ResaleBroker;
use Filament\Resources\Pages\PageRegistration;

class ResaleBrokerResource extends BrokerResource
{
    protected static ?string $model = ResaleBroker::class;
    protected static ?string $modelLabel = 'وسيط إعادة بيع';
    protected static ?string $pluralModelLabel = 'وسطاء إعادة البيع';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static string | \UnitEnum | null $navigationGroup = 'إعادة البيع';
    protected static ?int $navigationSort = 20;

    public static function department(): Department
    {
        return Department::Resale;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListResaleBrokers::route('/')];
    }
}
