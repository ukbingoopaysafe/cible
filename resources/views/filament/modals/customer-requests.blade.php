<div class="space-y-4">
    @if ($requests->isEmpty())
        <p class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">لا توجد طلبات مرتبطة بهذا العميل في هذا القسم.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead>
                    <tr class="text-right text-gray-600 dark:text-gray-300">
                        <th class="whitespace-nowrap px-3 py-2">الطلب</th>
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
                            $statusColor = $requestResource::getStatusColor($request->status);
                            $stageValue = $request->stage?->value ?? (string) $request->stage;
                            $stageLabel = $stageOptions[$stageValue] ?? '—';
                            $stageColor = $requestResource::getStageColor($request->stage);
                        @endphp
                        <tr>
                            <td class="px-3 py-2">
                                <a class="font-semibold text-primary-600 hover:underline" href="{{ $requestResource::getUrl('view', ['record' => $request]) }}">
                                    <span dir="ltr">{{ \App\Support\RecordCode::request($requestResource::department(), $request->getKey()) }}</span>
                                </a>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $request->created_at?->format('d/m/Y') }}</span>
                            </td>
                            <td class="px-3 py-2">{{ $request->area?->governorate }} — {{ $request->area?->name }}</td>
                            <td class="px-3 py-2">
                                <span class="fi-badge fi-size-sm fi-color-{{ $stageColor }}">{{ $stageLabel }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="fi-badge fi-size-sm fi-color-{{ $statusColor }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $request->next_follow_up_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $request->owner?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
