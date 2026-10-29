@props([
    'position' => null,
])

@php
    use Filament\Actions\Action;
    use Filament\Enums\UserMenuPosition;
    use Filament\Support\Facades\FilamentView;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\ComponentAttributeBag;
    use Filament\View\PanelsIconAlias;
    use Filament\View\PanelsRenderHook;
    use Illuminate\Support\Arr;

    $user = filament()->auth()->user();

    $userName = filament()->getUserName($user);

    $items = $this->getUserMenuItems();

    $itemsBeforeAndAfterThemeSwitcher = collect($items)
        ->groupBy(fn (Action $item): bool => $item->getSort() < 0, preserveKeys: true)
        ->all();
    $itemsBeforeThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[true] ?? collect();
    $itemsAfterThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[false] ?? collect();

    $hasProfileHeader = $itemsBeforeThemeSwitcher->has('profile') &&
        blank(($item = Arr::first($itemsBeforeThemeSwitcher))->getUrl()) &&
        (! $item->hasAction());

    if ($itemsBeforeThemeSwitcher->has('profile')) {
        $itemsBeforeThemeSwitcher = $itemsBeforeThemeSwitcher->prepend($itemsBeforeThemeSwitcher->pull('profile'), 'profile');
    }

    $multiGroupAfterTheme = $this->hasMultipleUserMenuItemGroups();
    $afterThemeItemGroups = $multiGroupAfterTheme ? $this->getUserMenuItemGroupsAfterTheme() : [];

    $position ??= filament()->getUserMenuPosition();

    $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
    $isRtl = __('filament-panels::layout.direction') === 'rtl';

    $placement = match ($position) {
        UserMenuPosition::Topbar => $isRtl ? 'bottom-start' : 'bottom-end',
        default => $isRtl ? 'top-start' : 'top-end',
    };
@endphp

{{ FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE) }}

<x-filament::dropdown
    :placement="$placement"
    :flip="false"
    :shift="true"
    :teleport="$position === UserMenuPosition::Topbar"
    width="fi-user-menu-dropdown-panel"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-user-menu'])
    "
>
    <x-slot name="trigger">
        @if ($position === UserMenuPosition::Topbar)
            <button
                aria-label="{{ filled($userName) ? $userName : __('filament-panels::layout.actions.open_user_menu.label') }}"
                type="button"
                class="fi-user-menu-trigger app-user-menu"
            >
                <x-filament-panels::avatar.user :user="$user" loading="lazy" />

                <span class="app-user-name hidden sm:inline text-[13px] font-bold text-slate-800 dark:text-slate-100 max-w-[12rem] truncate">
                    {{ $userName }}
                </span>

                <x-filament::icon
                    :icon="\Filament\Support\Icons\Heroicon::ChevronDown"
                    class="h-4 w-4 text-slate-400 dark:text-slate-400"
                />
            </button>
        @else
            <button
                aria-label="{{ filled($userName) ? $userName : __('filament-panels::layout.actions.open_user_menu.label') }}"
                type="button"
                class="fi-user-menu-trigger"
            >
                <x-filament-panels::avatar.user :user="$user" loading="lazy" />

                <span
                    @if ($isSidebarCollapsibleOnDesktop)
                        x-show="$store.sidebar.isOpen"
                    @endif
                    class="fi-user-menu-trigger-text"
                >
                    {{ $userName }}
                </span>

                {{
                    \Filament\Support\generate_icon_html(Heroicon::ChevronUp, alias: PanelsIconAlias::USER_MENU_TOGGLE_BUTTON, attributes: new ComponentAttributeBag([
                        'x-show' => $isSidebarCollapsibleOnDesktop ? '$store.sidebar.isOpen' : null,
                    ]))
                }}
            </button>
        @endif
    </x-slot>

    @if ($hasProfileHeader)
        @php
            $item = $itemsBeforeThemeSwitcher['profile'];
            $itemColor = $item->getColor();
            $itemIcon = $item->getIcon();

            unset($itemsBeforeThemeSwitcher['profile']);
        @endphp

        {{ FilamentView::renderHook(PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

        <x-filament::dropdown.header :color="$itemColor" :icon="$itemIcon">
            {{ $item->getLabel() }}
        </x-filament::dropdown.header>

        {{ FilamentView::renderHook(PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
    @endif

    @if ($itemsBeforeThemeSwitcher->isNotEmpty())
        <x-filament::dropdown.list>
            @foreach ($itemsBeforeThemeSwitcher as $item)
                {{ $item }}
            @endforeach
        </x-filament::dropdown.list>
    @endif

    @if (filament()->hasThemeSwitcher())
        <div class="fi-dropdown-list fi-user-menu-theme-switcher-ctn">
            <div class="fi-dropdown-header fi-user-menu-theme-header">
                <span class="fi-dropdown-header-label">
                    {{ __('filament-panels::layout.actions.theme_switcher.label') }}
                </span>
            </div>

            <x-filament-panels::theme-switcher />
        </div>
    @endif

    @if ($itemsAfterThemeSwitcher->isNotEmpty())
        @if ($multiGroupAfterTheme)
            @foreach ($afterThemeItemGroups as $group)
                <x-filament::dropdown.list>
                    @foreach ($group as $item)
                        {{ $item }}
                    @endforeach
                </x-filament::dropdown.list>
            @endforeach
        @else
            <x-filament::dropdown.list>
                @foreach ($itemsAfterThemeSwitcher as $item)
                    {{ $item }}
                @endforeach
            </x-filament::dropdown.list>
        @endif
    @endif
</x-filament::dropdown>

{{ FilamentView::renderHook(PanelsRenderHook::USER_MENU_AFTER) }}

