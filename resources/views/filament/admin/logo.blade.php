<div
    x-data="{}"
    class="flex items-center gap-3.5 select-none transition-all duration-200"
    dir="rtl"
>
    <div class="relative flex-shrink-0 flex items-center justify-center">
        <img
            src="{{ asset('images/brand/icon-tight.png') }}"
            alt="Cible Real Estate"
            class="h-10 w-10 object-contain drop-shadow-xs transition-transform duration-300 hover:scale-105"
            loading="eager"
        />
    </div>

    <div
        x-show="$store.sidebar.isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-x-2"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 -translate-x-2"
        class="flex flex-col text-right leading-none overflow-hidden"
    >
        <span class="font-['beIN'] text-[21px] font-bold tracking-tight text-[#183F3C] dark:text-[#C4A369] drop-shadow-xs leading-snug">
            سيبل العقارية
        </span>
        <span class="font-['Jost'] text-[11px] font-semibold uppercase tracking-[0.22em] text-[#C4A369] dark:text-[#E0D3BC] mt-0.5">
            Cible Real Estate
        </span>
    </div>
</div>
