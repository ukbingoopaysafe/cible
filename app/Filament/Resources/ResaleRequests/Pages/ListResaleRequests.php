<?php

namespace App\Filament\Resources\ResaleRequests\Pages;

use App\Filament\Resources\ResaleRequests\ResaleRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResaleRequests extends ListRecords
{
    protected static string $resource = ResaleRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة طلب إعادة بيع'),
        ];
    }
}
