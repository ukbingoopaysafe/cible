<div class="space-y-6 text-sm">
    <section>
        <h3 class="mb-3 text-base font-semibold">الترشيحات المسجلة</h3>
        @forelse ($record->recommendations()->with('inspections')->latest()->get() as $recommendation)
            <article class="mb-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <strong>{{ $recommendation->unit_code ?: 'وحدة دون كود' }} — {{ $recommendation->unit_type ?: 'النوع غير محدد' }}</strong>
                    <span class="rounded bg-gray-100 px-2 py-1 dark:bg-gray-800">
                        {{ ['proposed' => 'مقترحة', 'shared' => 'أُرسلت للعميل', 'selected' => 'اختارها العميل', 'rejected' => 'رفضها العميل', 'withdrawn' => 'سُحبت'][$recommendation->status] ?? $recommendation->status }}
                    </span>
                </div>
                <p class="mt-2 text-gray-600 dark:text-gray-300">
                    {{ $recommendation->location_description ?: 'لا يوجد وصف للموقع' }}
                    @if ($recommendation->unit_area) · {{ number_format((float) $recommendation->unit_area, 2) }} م² @endif
                    @if ($recommendation->asking_price) · {{ number_format((float) $recommendation->asking_price, 2) }} {{ $recommendation->currency }} @endif
                    @if ($recommendation->listing_code) · Listing {{ $recommendation->listing_code }} @endif
                </p>
                @if ($recommendation->notes)<p class="mt-2 whitespace-pre-line">{{ $recommendation->notes }}</p>@endif

                @if ($recommendation->inspections->isNotEmpty())
                    <div class="mt-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                        <strong>الفحص الميداني</strong>
                        @foreach ($recommendation->inspections->sortByDesc('inspected_at') as $inspection)
                            <p class="mt-1">{{ $inspection->inspected_at->format('d/m/Y H:i') }} — {{ $inspection->field_representative_name }} — {{ ['matches' => 'مطابقة للصور', 'partly_matches' => 'مطابقة جزئيًا', 'does_not_match' => 'غير مطابقة'][$inspection->photo_match] ?? $inspection->photo_match }}</p>
                            @if ($inspection->observed_defects)<p class="text-gray-600 dark:text-gray-300">العيوب: {{ $inspection->observed_defects }}</p>@endif
                            @if ($inspection->notes)<p class="text-gray-600 dark:text-gray-300">{{ $inspection->notes }}</p>@endif
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-warning-600">لم يُسجل الفحص الميداني بعد.</p>
                @endif
            </article>
        @empty
            <p class="text-gray-500">لا توجد ترشيحات مسجلة لهذا الطلب.</p>
        @endforelse
    </section>

    <section>
        <h3 class="mb-3 text-base font-semibold">معاينات العميل</h3>
        @forelse ($record->viewings()->with('recommendation')->orderByDesc('scheduled_at')->get() as $viewing)
            <article class="mb-2 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <strong>{{ $viewing->scheduled_at->format('d/m/Y H:i') }}</strong>
                @if ($viewing->recommendation) — {{ $viewing->recommendation->unit_code ?: 'وحدة دون كود' }} @endif
                @if ($viewing->field_representative_name) — المندوب: {{ $viewing->field_representative_name }} @endif
                <p class="mt-1">النتيجة: {{ ['interested' => 'مهتم', 'not_interested' => 'غير مهتم', 'second_viewing' => 'يرغب بمعاينة أخرى', 'negotiating' => 'بدأ التفاوض', 'cancelled' => 'أُلغيت'][$viewing->result] ?? 'لم تُسجل بعد' }}</p>
                @if ($viewing->customer_feedback)<p class="mt-1">رأي العميل: {{ $viewing->customer_feedback }}</p>@endif
                @if ($viewing->rejection_reason)<p class="mt-1">سبب عدم الاهتمام: {{ $viewing->rejection_reason }}</p>@endif
            </article>
        @empty
            <p class="text-gray-500">لا توجد معاينات مسجلة لهذا الطلب.</p>
        @endforelse
    </section>
</div>
