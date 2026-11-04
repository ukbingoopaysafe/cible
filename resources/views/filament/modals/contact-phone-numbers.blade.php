<div class="space-y-3">
    @if ($phones->isEmpty())
        <p class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">لا توجد أرقام هاتف مسجلة.</p>
    @else
        @foreach ($phones as $phone)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <span class="fi-badge fi-size-sm fi-color-{{ $phone->is_primary ? 'primary' : 'gray' }}">
                        {{ $phone->is_primary ? 'أساسي' : 'إضافي' }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\PhoneCountries::display($phone->phone_country) }}</span>
                </div>

                <div class="flex items-center gap-4" dir="ltr">
                    <a class="font-mono text-sm text-primary-600 hover:underline dark:text-primary-400" href="tel:{{ $phone->phone_e164 }}">
                        {{ $phone->phone_e164 }}
                    </a>
                    <a class="text-sm text-success-600 hover:underline dark:text-success-400" href="https://wa.me/{{ substr($phone->phone_e164, 1) }}" target="_blank" rel="noopener noreferrer">
                        WhatsApp
                    </a>
                </div>
            </div>
        @endforeach
    @endif
</div>
