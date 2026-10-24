<div class="space-y-4">
    @forelse ($activities as $activity)
        <article class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <strong>{{ $activity->channel->label() }}</strong>
                <time datetime="{{ $activity->occurred_at->toIso8601String() }}">
                    {{ $activity->occurred_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                </time>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                جهة الاتصال: {{ $activity->contact_party === \App\Enums\ContactParty::Customer ? 'العميل' : 'الوسيط' }}
                — {{ $activity->contact_name_snapshot }}
                — سجّله: {{ $activity->actor?->name ?? 'حساب غير متاح' }}
            </p>
            <p class="mt-3 whitespace-pre-line">{{ $activity->result }}</p>
            @if (filled($activity->notes))
                <p class="mt-2 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $activity->notes }}</p>
            @endif
            @if (filled($activity->next_action))
                <p class="mt-2 whitespace-pre-line text-sm"><strong>الإجراء التالي:</strong> {{ $activity->next_action }}</p>
            @endif
        </article>
    @empty
        <p class="text-sm text-gray-500">لا توجد تواصلات مسجلة لهذا الطلب.</p>
    @endforelse
</div>
