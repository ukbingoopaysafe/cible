@php
    $before = $changeRequest->before_values ?? [];
    $after = $changeRequest->after_values ?? [];
    $fields = [
        'name' => 'الاسم',
        'nationality' => 'الجنسية',
        'phones' => 'أرقام الهاتف',
    ];
    $formatValue = function (string $key, mixed $value): string {
        if ($key === 'phones') {
            return collect(is_array($value) ? $value : [])
                ->map(function (array $phone): string {
                    $kind = (bool) ($phone['is_primary'] ?? false) ? 'أساسي' : 'إضافي';
                    $country = \App\Support\PhoneCountries::display((string) ($phone['phone_country'] ?? ''));
                    $number = (string) ($phone['phone_e164'] ?? '');

                    return trim($kind.' — '.($country ? $country.' — ' : '').$number);
                })
                ->filter()
                ->implode("\n") ?: '—';
        }

        return filled($value) ? (string) $value : '—';
    };
@endphp

<div dir="rtl" class="space-y-4">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-gray-600 dark:text-gray-300">
        <span>السجل: <strong class="font-semibold text-gray-950 dark:text-white">{{ $changeRequest->subject?->name ?? 'السجل غير متاح' }}</strong></span>
        <span>مقدم الطلب: <strong class="font-semibold text-gray-950 dark:text-white">{{ $changeRequest->requester?->name ?? '—' }}</strong></span>
        <span>الحالة: <strong class="font-semibold text-gray-950 dark:text-white">{{ $changeRequest->status->label() }}</strong></span>
    </div>

    @if ($changeRequest->status === \App\Enums\ContactChangeRequestStatus::Rejected && filled($changeRequest->reason))
        <div role="note" class="rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-400/20 dark:bg-danger-400/10 dark:text-danger-200">
            <strong>سبب الرفض:</strong> {{ $changeRequest->reason }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <section aria-labelledby="contact-change-before" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
            <h3 id="contact-change-before" class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">البيانات الحالية وقت الطلب</h3>
            <dl class="space-y-3 text-sm">
                @foreach ($fields as $key => $label)
                    @continue($key === 'nationality' && ! array_key_exists($key, $before))
                    <div>
                        <dt class="font-medium text-gray-600 dark:text-gray-300">{{ $label }}</dt>
                        <dd class="mt-1 whitespace-pre-line break-words text-gray-950 dark:text-white">{{ $formatValue($key, $before[$key] ?? null) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section aria-labelledby="contact-change-after" class="rounded-lg border border-primary-200 bg-primary-50/40 p-4 dark:border-primary-400/20 dark:bg-primary-400/5">
            <h3 id="contact-change-after" class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">البيانات المطلوبة</h3>
            <dl class="space-y-3 text-sm">
                @foreach ($fields as $key => $label)
                    @continue($key === 'nationality' && ! array_key_exists($key, $after))
                    <div>
                        <dt class="font-medium text-gray-600 dark:text-gray-300">{{ $label }}</dt>
                        <dd class="mt-1 whitespace-pre-line break-words text-gray-950 dark:text-white">{{ $formatValue($key, $after[$key] ?? null) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    @if ($changeRequest->reviewed_similar_names)
        <p class="text-sm text-warning-700 dark:text-warning-300">أكد مقدم الطلب أن الاسم قد يشبه سجلًا آخر.</p>
    @endif

    @if (filled($similarNameLabels ?? []))
        <div role="note" class="rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-400/20 dark:bg-warning-400/10 dark:text-warning-200">
            <strong>أسماء مشابهة حاليًا:</strong> {{ implode('، ', $similarNameLabels) }}
        </div>
    @endif
</div>
