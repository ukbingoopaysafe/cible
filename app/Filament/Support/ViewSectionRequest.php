<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;

abstract class ViewSectionRequest extends ViewRecord
{
    /** @return array<Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        /** @var class-string<SectionRequestResource> $resource */
        $resource = static::getResource();

        return [
            $resource::recordContactAction(),
            $resource::getRequestActionsGroup(),
        ];
    }

    protected function afterActionCalled(Action $action): void
    {
        parent::afterActionCalled($action);

        if ($this->hasRecord() && $this->getRecord()->exists) {
            $this->getRecord()->refresh();
        }
    }
}
