<div class="space-y-4">
    @if ($requests->isEmpty())
        <p class="text-sm text-gray-500">لا توجد طلبات مرتبطة بهذا العميل في هذا القسم.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead>
                    <tr class="text-right text-gray-600 dark:text-gray-300">
                        <th class="px-3 py-2">الطلب</th>
                        <th class="px-3 py-2">المنطقة</th>
                        <th class="px-3 py-2">المرحلة</th>
                        <th class="px-3 py-2">الحالة</th>
                        <th class="px-3 py-2">المتابعة القادمة</th>
                        <th class="px-3 py-2">المسؤول</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($requests as $request)
                        <tr>
                            <td class="px-3 py-2">
                                <a class="text-primary-600 hover:underline" href="{{ $requestResource::getUrl('view', ['record' => $request]) }}">
                                    #{{ $request->getKey() }} · {{ $request->created_at?->format('d/m/Y') }}
                                </a>
                            </td>
                            <td class="px-3 py-2">{{ $request->area?->governorate }} — {{ $request->area?->name }}</td>
                            <td class="px-3 py-2">{{ $stageOptions[$request->stage?->value ?? (string) $request->stage] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ match ($request->status?->value ?? (string) $request->status) { 'active' => 'نشط', 'paused' => 'متوقف مؤقتًا', 'closed' => 'مغلق', default => '—' } }}</td>
                            <td class="px-3 py-2">{{ $request->next_follow_up_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $request->owner?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
