<?php

namespace App\Filament\Resources\ResaleCustomerFollowUps;

use App\Enums\Department;
use App\Filament\Resources\ResaleCustomerFollowUps\Pages\ListResaleCustomerFollowUps;
use App\Filament\Support\CustomerFollowUpResource;
use App\Models\ResaleCustomerFollowUp;
use Filament\Resources\Pages\PageRegistration;

class ResaleCustomerFollowUpResource extends CustomerFollowUpResource
{
    protected static ?string $model = ResaleCustomerFollowUp::class;

    protected static ?string $slug = 'resale-customer-follow-ups';

    protected static ?string $modelLabel = 'متابعة العميل';

    protected static ?string $pluralModelLabel = 'متابعات العملاء';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|\UnitEnum|null $navigationGroup = 'إعادة البيع';

    protected static ?int $navigationSort = 15;

    public static function department(): Department
    {
        return Department::Resale;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListResaleCustomerFollowUps::route('/'),
        ];
    }
}
