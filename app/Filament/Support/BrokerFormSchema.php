<?php

namespace App\Filament\Support;

use App\Services\CustomerPhoneNormalizer;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;
use Propaganistas\LaravelPhone\PhoneNumber;

final class BrokerFormSchema
{
    /** @return array<int, Component> */
    public static function fields(bool $editing = false): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])
                ->schema([
                    TextInput::make('name')
                        ->label('اسم الوسيط')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('phone')
                        ->label('رقم الهاتف')
                        ->tel()
                        ->maxLength(40)
                        ->live(onBlur: true)
                        ->helperText('يُوحّد النظام الرقم ويتحقق من عدم استخدامه لوسيط آخر في القسم.')
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                                if (blank($value)) {
                                    return;
                                }
                                $country = $get('phone_country') ?: 'EG';
                                try {
                                    app(CustomerPhoneNormalizer::class)->normalize((string) $value, (string) $country);
                                } catch (ValidationException $e) {
                                    $fail(collect($e->errors())->flatten()->first());
                                }
                            },
                        ]),
                    TextInput::make('phone_country')
                        ->label('رمز الدولة')
                        ->default('EG')
                        ->length(2)
                        ->helperText('رمز الدولة الدولي، مثل EG لمصر.')
                        ->visible(fn (Get $get): bool => filled($get('phone')))
                        ->required(fn (Get $get): bool => filled($get('phone')))
                        ->dehydrated(fn (Get $get): bool => filled($get('phone')))
                        ->rules([
                            fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                                if (filled($value) && ! PhoneNumber::isValidCountry(strtoupper(trim((string) $value)))) {
                                    $fail('رمز الدولة المحدد غير صالح (مثال: EG لمصر).');
                                }
                            },
                        ]),
                    Checkbox::make('reviewed_similar_names')
                        ->label('راجعت تنبيه تشابه الاسم وأؤكد أن هذا وسيط مختلف.')
                        ->helperText($editing ? 'يظهر التنبيه عند حفظ اسم قد يتشابه مع وسيط آخر.' : 'يظهر التنبيه عند إضافة اسم قد يتشابه مع وسيط آخر.')
                        ->columnSpanFull(),
                ]),
        ];
    }
}
