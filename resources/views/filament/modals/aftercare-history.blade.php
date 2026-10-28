<div class="space-y-4">
    @if (! $aftercare)
        <p class="text-sm text-gray-600">لا يوجد سجل ما بعد الصفقة لهذا العميل.</p>
    @else
        <div class="rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-900">
            <p><strong>المسؤول:</strong> {{ $aftercare->owner?->name ?? '—' }}</p>
            <p><strong>المتابعة القادمة:</strong> {{ $aftercare->next_follow_up_at?->format('d/m/Y H:i') ?? 'غير محددة' }}</p>
        </div>

        <div class="space-y-3">
            @forelse ($aftercare->activities as $activity)
                <article class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex flex-wrap justify-between gap-2 text-sm">
                        <strong>{{ $activity->channel->label() }} — {{ $activity->occurred_at->format('d/m/Y H:i') }}</strong>
                        <span>{{ $activity->actor?->name ?? '—' }}</span>
                    </div>
                    @if ($activity->deal)
                        <p class="mt-2 text-sm text-gray-600">الصفقة: {{ number_format((float) $activity->deal->agreed_total_value, 2) }} {{ $activity->deal->currency }}</p>
                    @endif
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $activity->result }}</p>
                    @if ($activity->notes)
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-600">{{ $activity->notes }}</p>
                    @endif
                    @if ($activity->referral_details)
                        <p class="mt-2 whitespace-pre-line text-sm"><strong>إحالة أو فرصة:</strong> {{ $activity->referral_details }}</p>
                    @endif
                    @if ($activity->next_follow_up_at)
                        <p class="mt-2 text-sm text-gray-600"><strong>المتابعة القادمة:</strong> {{ $activity->next_follow_up_at->format('d/m/Y H:i') }}</p>
                    @endif
                </article>
            @empty
                <p class="text-sm text-gray-600">لا توجد تفاعلات مسجلة بعد.</p>
            @endforelse
        </div>
    @endif
</div>
