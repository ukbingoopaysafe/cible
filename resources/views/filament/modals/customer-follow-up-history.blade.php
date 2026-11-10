<div class="space-y-4">
    @if (! $customerFollowUp)
        <p class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
            لا يوجد سجل متابعة لهذا العميل.
        </p>
    @else
        <div class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2">
            <div>
                <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">مسؤول المتابعة</span>
                <span class="mt-1 block font-semibold text-gray-900 dark:text-white">{{ $customerFollowUp->owner?->name ?? '—' }}</span>
            </div>
            <div>
                <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">المتابعة القادمة</span>
                <span class="mt-1 block font-semibold text-gray-900 dark:text-white">@if ($customerFollowUp->next_follow_up_at)<bdi dir="ltr" class="bidi-isolate">{{ app(\App\Support\DateTimePresenter::class)->formatDateTime($customerFollowUp->next_follow_up_at) }}</bdi>@else غير محددة @endif</span>
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($customerFollowUp->activities as $activity)
                <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <strong>{{ $activity->channel->label() }}</strong>
                        <span class="text-xs text-gray-500 dark:text-gray-400"><bdi dir="ltr" class="bidi-isolate">{{ app(\App\Support\DateTimePresenter::class)->formatDateTime($activity->occurred_at) }}</bdi> · {{ $activity->actor?->name ?? '—' }}</span>
                    </div>
                    @if ($activity->deal)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">الصفقة: {{ number_format((float) $activity->deal->agreed_total_value, 2) }} {{ $activity->deal->currency }}</p>
                    @endif
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $activity->result }}</p>
                    @if ($activity->notes)
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $activity->notes }}</p>
                    @endif
                    @if ($activity->referral_details)
                        <p class="mt-2 whitespace-pre-line text-sm"><strong>إحالة أو فرصة:</strong> {{ $activity->referral_details }}</p>
                    @endif
                    @if ($activity->next_follow_up_at)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300"><strong>المتابعة القادمة:</strong> <bdi dir="ltr" class="bidi-isolate">{{ app(\App\Support\DateTimePresenter::class)->formatDateTime($activity->next_follow_up_at) }}</bdi></p>
                    @endif
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">لا توجد تفاعلات مسجلة بعد.</p>
            @endforelse
        </div>
    @endif
</div>
