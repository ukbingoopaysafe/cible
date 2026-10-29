@php
    use Filament\Enums\DatabaseNotificationsPosition;
    use Filament\Enums\GlobalSearchPosition;
    use Filament\Enums\UserMenuPosition;
    use Filament\Livewire\GlobalSearch;
    use Filament\Support\Facades\FilamentView;
    use Filament\Support\Icons\Heroicon;
    use Filament\View\PanelsIconAlias;
    use Filament\View\PanelsRenderHook;
@endphp

<div
    x-data="filamentSidebarBackground"
    x-bind:inert="$store.sidebar.isOpenMobile && ! hasOpenModal"
    x-bind:class="{ 'fi-topbar-sidebar-open': $store.sidebar.isOpen }"
    class="fi-topbar-ctn"
>
    @php
        $isRtl = __('filament-panels::layout.direction') === 'rtl';
        $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
        $isSidebarFullyCollapsibleOnDesktop = filament()->isSidebarFullyCollapsibleOnDesktop();
        $hasTopNavigation = filament()->hasTopNavigation();
        $hasNavigation = filament()->hasNavigation();
        $hasTenancy = filament()->hasTenancy();
    @endphp

    <nav
        aria-label="{{ __('filament-panels::layout.topbar.label') }}"
        class="fi-topbar"
    >
        {{ FilamentView::renderHook(PanelsRenderHook::TOPBAR_START) }}

        @if ($hasNavigation)
            <x-filament::icon-button
                color="gray"
                :icon="Heroicon::OutlinedBars3"
                :icon-alias="PanelsIconAlias::TOPBAR_OPEN_SIDEBAR_BUTTON"
                icon-size="lg"
                :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                x-cloak
                x-data="{}"
                aria-controls="fi-main-sidebar"
                x-bind:aria-expanded="$store.sidebar.isOpen"
                x-on:click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open($event.currentTarget)"
                class="fi-topbar-sidebar-toggle-btn"
            />
        @endif

        <div class="fi-topbar-start">
            {{ FilamentView::renderHook(PanelsRenderHook::TOPBAR_LOGO_BEFORE) }}

            <div class="lg:hidden">
                @if ($homeUrl = filament()->getHomeUrl())
                    <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
                        <x-filament-panels::logo />
                    </a>
                @else
                    <x-filament-panels::logo />
                @endif
            </div>

            {{ FilamentView::renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER) }}
        </div>

        @if ($hasTopNavigation || (! $hasNavigation))
            @if ($hasTenancy && filament()->hasTenantMenu())
                <x-filament-panels::tenant-menu teleport />
            @endif

            @if ($hasNavigation)
                @php
                    $navigation = filament()->getNavigation();
                @endphp

                <ul class="fi-topbar-nav-groups">
                    @foreach ($navigation as $group)
                        @php
                            $groupLabel = $group->getLabel();
                            $groupExtraTopbarAttributeBag = $group->getExtraTopbarAttributeBag();
                            $isGroupActive = $group->isActive();
                            $groupIcon = $group->getIcon();
                        @endphp

                        @if ($groupLabel)
                            <x-filament::dropdown
                                placement="bottom-start"
                                teleport
                                :attributes="\Filament\Support\prepare_inherited_attributes($groupExtraTopbarAttributeBag)"
                            >
                                <x-slot name="trigger">
                                    <x-filament-panels::topbar.item
                                        :active="$isGroupActive"
                                        :icon="$groupIcon"
                                    >
                                        {{ $groupLabel }}
                                    </x-filament-panels::topbar.item>
                                </x-slot>

                                @php
                                    $lists = [];

                                    foreach ($group->getItems() as $item) {
                                        if ($childItems = $item->getChildItems()) {
                                            $lists[] = [
                                                $item,
                                                ...$childItems,
                                            ];
                                            $lists[] = [];

                                            continue;
                                        }

                                        if (empty($lists)) {
                                            $lists[] = [$item];

                                            continue;
                                        }

                                        $lists[count($lists) - 1][] = $item;
                                    }

                                    if (empty($lists[count($lists) - 1])) {
                                        array_pop($lists);
                                    }
                                @endphp

                                @foreach ($lists as $list)
                                    <x-filament::dropdown.list>
                                        @foreach ($list as $item)
                                            @php
                                                $itemIsActive = $item->isActive();
                                                $itemBadge = $item->getBadge();
                                                $itemBadgeColor = $item->getBadgeColor();
                                                $itemBadgeTooltip = $item->getBadgeTooltip();
                                                $itemUrl = $item->getUrl();
                                                $itemIcon = $item->getIcon();
                                                $itemActiveIcon = $item->getActiveIcon();
                                                $itemShouldOpenUrlInNewTab = $item->shouldOpenUrlInNewTab();
                                                $itemExtraTopbarAttributeBag = $item->getExtraTopbarAttributeBag();
                                            @endphp

                                            <x-filament-panels::topbar.item
                                                :active="$itemIsActive"
                                                :active-icon="$itemActiveIcon"
                                                :badge="$itemBadge"
                                                :badge-color="$itemBadgeColor"
                                                :badge-tooltip="$itemBadgeTooltip"
                                                :icon="$itemIcon"
                                                :should-open-url-in-new-tab="$itemShouldOpenUrlInNewTab"
                                                :url="$itemUrl"
                                                :attributes="\Filament\Support\prepare_inherited_attributes($itemExtraTopbarAttributeBag)"
                                            >
                                                {{ $item->getLabel() }}
                                            </x-filament-panels::topbar.item>
                                        @endforeach
                                    </x-filament::dropdown.list>
                                @endforeach
                            </x-filament::dropdown>
                        @else
                            @foreach ($group->getItems() as $item)
                                @php
                                    $itemIsActive = $item->isActive();
                                    $itemBadge = $item->getBadge();
                                    $itemBadgeColor = $item->getBadgeColor();
                                    $itemBadgeTooltip = $item->getBadgeTooltip();
                                    $itemUrl = $item->getUrl();
                                    $itemIcon = $item->getIcon();
                                    $itemActiveIcon = $item->getActiveIcon();
                                    $itemShouldOpenUrlInNewTab = $item->shouldOpenUrlInNewTab();
                                    $itemExtraTopbarAttributeBag = $item->getExtraTopbarAttributeBag();
                                @endphp

                                <x-filament-panels::topbar.item
                                    :active="$itemIsActive"
                                    :active-icon="$itemActiveIcon"
                                    :badge="$itemBadge"
                                    :badge-color="$itemBadgeColor"
                                    :badge-tooltip="$itemBadgeTooltip"
                                    :icon="$itemIcon"
                                    :should-open-url-in-new-tab="$itemShouldOpenUrlInNewTab"
                                    :url="$itemUrl"
                                    :attributes="\Filament\Support\prepare_inherited_attributes($itemExtraTopbarAttributeBag)"
                                >
                                    {{ $item->getLabel() }}
                                </x-filament-panels::topbar.item>
                            @endforeach
                        @endif
                    @endforeach
                </ul>
            @endif
        @endif

        <div
            @if ($hasTenancy)
                x-persist="topbar.end.panel-{{ filament()->getId() }}.tenant-{{ filament()->getTenant()?->getKey() }}"
            @else
                x-persist="topbar.end.panel-{{ filament()->getId() }}"
            @endif
            class="fi-topbar-end"
        >
            {{ FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE) }}

            @if (filament()->isGlobalSearchEnabled() && filament()->getGlobalSearchPosition() === GlobalSearchPosition::Topbar)
                @livewire(GlobalSearch::class)
            @endif

            {{ FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER) }}

            @if (filament()->auth()->check())
                @if (filament()->hasDatabaseNotifications() && filament()->getDatabaseNotificationsPosition() === DatabaseNotificationsPosition::Topbar)
                    @livewire(filament()->getDatabaseNotificationsLivewireComponent(), [
                        'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                    ])
                @endif

                @if (filament()->hasUserMenu() && filament()->getUserMenuPosition() === UserMenuPosition::Topbar)
                    <x-filament-panels::user-menu />
                @endif
            @endif
        </div>

        {{ FilamentView::renderHook(PanelsRenderHook::TOPBAR_END) }}
    </nav>

    <x-filament-actions::modals />
</div>

