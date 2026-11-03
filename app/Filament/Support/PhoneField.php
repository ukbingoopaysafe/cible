<?php

namespace App\Filament\Support;

use App\Services\CustomerPhoneNormalizer;
use App\Support\PhoneCountries;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PhoneField
{
    /**
     * A fused country-code select and phone input that validates against the
     * selected country's libphonenumber rules.
     */
    public static function make(
        string $label = 'رقم الهاتف',
        bool $required = true,
        string $phoneField = 'phone',
        string $countryField = 'phone_country',
    ): FusedGroup {
        $countrySelect = Select::make($countryField)
            ->hiddenLabel()
            ->options(PhoneCountries::options())
            ->allowHtml()
            ->extraAttributes(['class' => 'fi-phone-country-select fi-fixed-positioning-context'])
            ->default(PhoneCountries::DEFAULT)
            ->selectablePlaceholder(false)
            ->searchable()
            ->searchPrompt('ابحث باسم الدولة أو رمزها...')
            ->noSearchResultsMessage('لا توجد دولة مطابقة.')
            ->required($required)
            ->live()
            ->dehydrated(fn (Get $get): bool => $required || filled($get($phoneField)))
            ->rules([
                $required ? 'required' : 'nullable',
                Rule::in(array_keys(PhoneCountries::options())),
            ])
            ->validationMessages([
                'in' => 'اختر دولة صالحة من القائمة.',
            ])
            ->columnSpan(['default' => 1, 'sm' => 1]);

        $phoneInput = TextInput::make($phoneField)
            ->hiddenLabel()
            ->tel()
            ->required($required)
            ->maxLength(40)
            ->placeholder(fn (Get $get): ?string => PhoneCountries::isOther((string) $get($countryField))
                ? '+4915112345678'
                : PhoneCountries::example((string) $get($countryField)))
            ->rules([
                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($countryField, $get): void {
                    if (blank($value)) {
                        return;
                    }

                    try {
                        app(CustomerPhoneNormalizer::class)->normalize(
                            (string) $value,
                            (string) ($get($countryField) ?: PhoneCountries::DEFAULT),
                        );
                    } catch (ValidationException $exception) {
                        $fail(collect($exception->errors())->flatten()->first());
                    }
                },
            ])
            ->extraInputAttributes([
                'dir' => 'ltr',
                'autocomplete' => 'tel',
                'inputmode' => 'tel',
                'class' => 'fi-phone-number-input',
            ])
            ->columnSpan(['default' => 1, 'sm' => 2]);

        return FusedGroup::make([$countrySelect, $phoneInput])
            ->label($label)
            ->columns(['default' => 1, 'sm' => 3]);
    }
}
