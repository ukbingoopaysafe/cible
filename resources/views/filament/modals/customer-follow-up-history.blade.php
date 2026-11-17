<div class="space-y-5">
    @php
        $cases = isset($customerFollowUps)
            ? $customerFollowUps
            : collect([$customerFollowUp ?? null])->filter();
    @endphp

    @forelse ($cases as $customerFollowUp)
        @php
            $legacyCaseHasRequests = $customerFollowUp->legacy_customer_id
                && ($customerFollowUp->customer?->requests()->exists() ?? false);
            $historyOnly = $customerFollowUp->category === null || $legacyCaseHasRequests;
        @endphp
        <section class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <div class="grid gap-3 rounded-xl bg-gray-50 p-4 text-sm dark:bg-gray-900 sm:grid-cols-2">
                <div>
                    <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">تصنيف المتابعة</span>
                    <span class="mt-1 block font-semibold text-gray-900 dark:text-white">{{ $historyOnly ? 'سجل تاريخي للقراءة فقط' : ($customerFollowUp->category?->label() ?? 'سجل تاريخي غير مصنف') }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">الطلب المرتبط</span>
                    <span class="mt-1 block font-semibold text-gray-900 dark:text-white">{{ $customerFollowUp->request_id ? \App\Support\RecordCode::request($customerFollowUp->request instanceof \App\Models\RentalRequest ? \App\Enums\Department::Rental : \App\Enums\Department::Resale, $customerFollowUp->request_id) : 'لا يوجد طلب مرتبط' }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $historyOnly ? 'المسؤول المسجل سابقًا' : 'مسؤول المتابعة' }}</span>
                    <span class="mt-1 block font-semibold text-gray-900 dark:text-white">{{ $customerFollowUp->owner?->name ?? '—' }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $historyOnly ? 'موعد محفوظ بالسجل' : 'المتابعة القادمة' }}</span>
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
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300"><strong>المتابعة التالية كما سُجلت وقتها:</strong> <bdi dir="ltr" class="bidi-isolate">{{ app(\App\Support\DateTimePresenter::class)->formatDateTime($activity->next_follow_up_at) }}</bdi></p>
                        @endif
                    </article>
                @empty
                    <p class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">لا توجد تفاعلات مسجلة لهذا السجل بعد.</p>
                @endforelse
            </div>
        </section>
    @empty
        <p class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">لا توجد سجلات متابعة لهذا العميل.</p>
    @endforelse
</div>
