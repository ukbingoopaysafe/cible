<?php

namespace App\Filament\Support;

use App\Support\PhoneCountries;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\Eloquent\Model;

final class ContactPhonesField
{
    public static function make(bool $required): Repeater
    {
        return Repeater::make('phones')
            ->label('أرقام الهاتف')
            ->helperText('الرقم الأول هو الأساسي تلقائيًا، وباقي الأرقام إضافية.')
            ->schema([
                PhoneField::make('رقم الهاتف', required: $required)
                    ->columnSpanFull(),
            ])
            ->extraAttributes(['class' => 'fi-contact-phones'])
            ->defaultItems($required ? 1 : 0)
            ->minItems($required ? 1 : 0)
            ->addAction(fn (Action $action): Action => $action
                ->label('إضافة رقم')
                ->icon('heroicon-m-plus'))
            ->addActionAlignment('start')
            ->itemLabel(fn (?int $index): string => $index === 0 ? 'الرقم الأساسي' : 'رقم إضافي')
            ->compact()
            ->reorderable(false);
    }

    /** @return array{phones: list<array{phone: string, phone_country: string}>} */
    public static function fromContact(Model $contact): array
    {
        $phoneRecords = $contact->phoneNumbers()
            ->reorder()
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        return [
            'phones' => $phoneRecords->map(fn (Model $phone): array => [
                'phone' => PhoneCountries::nationalNumber($phone->phone_e164, $phone->phone_country),
                'phone_country' => PhoneCountries::selectable($phone->phone_country),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    public static function toServiceData(array $data): array
    {
        $phones = is_array($data['phones'] ?? null) ? $data['phones'] : [];
        $primaryAssigned = false;

        foreach ($phones as &$phone) {
            if (! is_array($phone)) {
                continue;
            }

            $phone['is_primary'] = ! $primaryAssigned && filled($phone['phone'] ?? null);
            $primaryAssigned = $primaryAssigned || $phone['is_primary'];
        }
        unset($phone);

        $data['phones'] = $phones;

        return $data;
    }
}
