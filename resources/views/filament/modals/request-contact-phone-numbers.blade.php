<div class="space-y-6">
    @foreach ($sections as $section)
        @php($phones = collect($section['phones'] ?? []))

        <section class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $section['label'] }}</h3>

            @if ($phones->isEmpty())
                <p class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">لا توجد أرقام هاتف مسجلة.</p>
            @else
                @foreach ($phones as $phone)
                    @php($number = data_get($phone, 'phone_e164'))

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-white/10">
                        <div class="flex items-center gap-3">
                            <span class="fi-badge fi-size-sm fi-color-{{ data_get($phone, 'is_primary') ? 'primary' : 'gray' }}">
                                {{ data_get($phone, 'is_primary') ? 'أساسي' : 'إضافي' }}
                            </span>
                            @if ($country = data_get($phone, 'phone_country'))
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\PhoneCountries::display($country) }}</span>
                            @endif
                        </div>

                        @if (filled($number))
                            <div class="flex items-center gap-4" dir="ltr">
                                <a class="font-mono text-sm text-primary-600 hover:underline dark:text-primary-400" href="tel:{{ $number }}">
                                    {{ $number }}
                                </a>
                                <a class="text-sm text-success-600 hover:underline dark:text-success-400" href="https://wa.me/{{ substr($number, 1) }}" target="_blank" rel="noopener noreferrer">
                                    WhatsApp
                                </a>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </section>
    @endforeach
</div>
