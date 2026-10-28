<?php

namespace App\Filament\Resources\RentalAftercares;

use App\Enums\Department;
use App\Filament\Resources\RentalAftercares\Pages\ListRentalAftercares;
use App\Filament\Support\AftercareResource;
use App\Models\RentalAftercare;
use Filament\Resources\Pages\PageRegistration;

class RentalAftercareResource extends AftercareResource
{
    protected static ?string $model = RentalAftercare::class;

    protected static ?string $slug = 'rental-aftercares';

    protected static ?string $modelLabel = 'متابعة ما بعد الصفقة';

    protected static ?string $pluralModelLabel = 'ما بعد الصفقة';

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
            'index' => ListRentalAftercares::route('/'),
        ];
    }
}
