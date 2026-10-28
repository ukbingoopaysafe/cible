<?php

namespace App\Filament\Resources\ResaleAftercares;

use App\Enums\Department;
use App\Filament\Resources\ResaleAftercares\Pages\ListResaleAftercares;
use App\Filament\Support\AftercareResource;
use App\Models\ResaleAftercare;
use Filament\Resources\Pages\PageRegistration;

class ResaleAftercareResource extends AftercareResource
{
    protected static ?string $model = ResaleAftercare::class;

    protected static ?string $slug = 'resale-aftercares';

    protected static ?string $modelLabel = 'متابعة ما بعد الصفقة';

    protected static ?string $pluralModelLabel = 'ما بعد الصفقة';

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
            'index' => ListResaleAftercares::route('/'),
        ];
    }
}
