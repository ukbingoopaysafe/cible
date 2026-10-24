<?php

namespace App\Filament\Support;

use App\Enums\Department;
use App\Models\RentalCustomer;
use App\Models\ResaleCustomer;
use App\Models\User;
use App\Services\RequestService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

abstract class CreateSectionRequest extends CreateRecord
{
    abstract protected static function department(): Department;

    public function mount(): void
    {
        parent::mount();

        $customerId = request()->query('customer_id');
        if (! is_scalar($customerId) || ! ctype_digit((string) $customerId)) {
            return;
        }

        $customerClass = static::department() === Department::Rental ? RentalCustomer::class : ResaleCustomer::class;
        if ($customerClass::query()->whereKey((int) $customerId)->exists()) {
            $this->form->fill(['customer_id' => (int) $customerId]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(RequestService::class)->create($actor, static::department(), $data);
    }
}
