<?php

namespace App\Filament\Resources\ResaleRequests;

use App\Enums\Department;
use App\Filament\Resources\ResaleRequests\Pages\CreateResaleRequest;
use App\Filament\Resources\ResaleRequests\Pages\ListResaleRequests;
use App\Filament\Resources\ResaleRequests\Pages\ViewResaleRequest;
use App\Filament\Support\SectionRequestResource;
use App\Models\ResaleRequest;
use Filament\Resources\Pages\PageRegistration;

class ResaleRequestResource extends SectionRequestResource
{
    protected static ?string $model = ResaleRequest::class;

    protected static ?string $modelLabel = 'طلب مشتري إعادة بيع';

    protected static ?string $pluralModelLabel = 'طلبات مشتري إعادة البيع';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home-modern';

    protected static string | \UnitEnum | null $navigationGroup = 'إعادة البيع';

    protected static ?int $navigationSort = 10;

    public static function department(): Department
    {
        return Department::Resale;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListResaleRequests::route('/'),
            'create' => CreateResaleRequest::route('/create'),
            'view' => ViewResaleRequest::route('/{record}'),
        ];
    }
}
