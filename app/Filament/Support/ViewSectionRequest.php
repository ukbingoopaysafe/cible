<?php

namespace App\Filament\Support;

use App\Enums\Department;
use App\Support\RecordCode;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

abstract class ViewSectionRequest extends ViewRecord
{
    public function getTitle(): string | Htmlable
    {
        /** @var class-string<SectionRequestResource> $resource */
        $resource = static::getResource();
        $dept = $resource::department();
        $code = RecordCode::request($dept, $this->getRecord()?->id);
        $prefix = $dept === Department::Rental ? 'طلب إيجار' : 'طلب إعادة بيع';

        return "{$prefix} {$code}";
    }

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
