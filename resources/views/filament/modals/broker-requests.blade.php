<div class="space-y-4">
    @if ($requests->isEmpty())
        <p class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">لا توجد طلبات مرتبطة بهذا الوسيط.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead>
                    <tr class="text-right text-gray-600 dark:text-gray-300">
                        <th class="whitespace-nowrap px-3 py-2">الطلب</th>
                        <th class="whitespace-nowrap px-3 py-2">العميل</th>
                        <th class="whitespace-nowrap px-3 py-2">المنطقة</th>
                        <th class="whitespace-nowrap px-3 py-2">المرحلة</th>
                        <th class="whitespace-nowrap px-3 py-2">الحالة</th>
                        <th class="whitespace-nowrap px-3 py-2">المتابعة القادمة</th>
                        <th class="whitespace-nowrap px-3 py-2">المسؤول</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($requests as $request)
                        @php
                            $statusValue = $request->status?->value ?? (string) $request->status;
                            $statusLabel = match ($statusValue) {
                                'active' => 'نشط',
                                'paused' => 'متوقف مؤقتًا',
                                'closed' => 'مغلق',
                                default => '—',
                            };
                            $stageValue = $request->stage?->value ?? (string) $request->stage;
                            $stageLabel = match ($stageValue) {
                                'unit_search' => 'بحث عن وحدات',
                                'recommendation' => 'ترشيح',
                                'viewing' => 'معاينة',
                                'negotiation' => 'تفاوض',
                                default => '—',
                            };
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-3 py-3 font-semibold">{{ \App\Support\RecordCode::request($department, $request->id) }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $request->customer?->name ?? 'بدون عميل مسجل' }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $request->area?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $stageLabel }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $statusLabel }}</td>
                            <td class="whitespace-nowrap px-3 py-3">
                                @if ($request->next_follow_up_at)
                                    <bdi dir="ltr" class="bidi-isolate">{{ app(\App\Support\DateTimePresenter::class)->formatDateTime($request->next_follow_up_at) }}</bdi>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $request->owner?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
