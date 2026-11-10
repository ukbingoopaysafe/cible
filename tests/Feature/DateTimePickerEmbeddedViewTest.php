<?php

namespace Tests\Feature;

use App\Support\TwelveHourDateTimePickerView;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\DateTimePickerRenderingComponent;
use Tests\TestCase;

class DateTimePickerEmbeddedViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app('view')->addNamespace('test-fixtures', base_path('tests/Fixtures/views'));
    }

    public function test_non_native_picker_markup_is_decorated_with_12_hour_controls_without_changing_filament_state(): void
    {
        $html = TwelveHourDateTimePickerView::decorate($this->embeddedFilamentMarkup(), 'en', 'scheduled_at');

        $this->assertStringContainsString('x-data="dateTimePickerFormComponent(', $html);
        $this->assertStringContainsString('x-bind:value="hourDraft"', $html);
        $this->assertStringContainsString('x-bind:value="minuteDraft"', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('maxlength="2"', $html);
        $this->assertStringContainsString('x-on:keydown.enter.prevent=', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('aria-haspopup="listbox"', $html);
        $this->assertStringContainsString('x-ref="hourOptions"', $html);
        $this->assertStringContainsString('x-ref="minuteOptions"', $html);
        $this->assertStringContainsString('x-on:input=', $html);
        $this->assertSame(72, substr_count($html, 'role="option"'));
        $this->assertStringContainsString('x-bind:aria-pressed="(Number(hour) < 12).toString()"', $html);
        $this->assertStringContainsString('x-bind:aria-pressed="(Number(hour) >= 12).toString()"', $html);
        $this->assertStringContainsString('hour = Number(hour) % 12', $html);
        $this->assertStringContainsString('hour = (Number(hour) % 12) + 12', $html);
        $this->assertStringContainsString('data-cible-date-time-picker-panel', $html);
        $this->assertStringNotContainsString('popover="manual"', $html);
        $this->assertStringNotContainsString('x-on:click.stop="true"', $html);
        $this->assertStringNotContainsString('fi-date-time-picker-open', $html);
        $this->assertStringNotContainsString("panel?.closest('.fi-modal-content')", $html);
        $this->assertStringNotContainsString('showPopover()', $html);
        $this->assertStringContainsString('x-ref="maxDate"', $html);
        $this->assertStringContainsString('x-ref="minDate"', $html);
        $this->assertStringContainsString('x-ref="disabledDates"', $html);
        $this->assertStringContainsString('wire:ignore', $html);
        $this->assertStringContainsString('$wire', $html);
        $this->assertStringNotContainsString('x-model.debounce="hour"', $html);
        $this->assertStringNotContainsString('x-model.debounce="minute"', $html);
        $this->assertStringNotContainsString('.fi-modal-content', $html);
        $this->assertStringNotContainsString('scrollTop', $html);
    }

    public function test_english_period_labels_and_arabic_period_labels_follow_the_picker_locale(): void
    {
        $englishHtml = TwelveHourDateTimePickerView::decorate($this->embeddedFilamentMarkup(), 'en', 'english_time');
        $arabicHtml = TwelveHourDateTimePickerView::decorate($this->embeddedFilamentMarkup(), 'ar', 'arabic_time');

        $this->assertStringContainsString('>AM</button>', $englishHtml);
        $this->assertStringContainsString('>PM</button>', $englishHtml);
        $this->assertStringContainsString('>ص</button>', $arabicHtml);
        $this->assertStringContainsString('>م</button>', $arabicHtml);
        $this->assertStringContainsString('dir="ltr"', $englishHtml);
        $this->assertStringContainsString('dir="ltr"', $arabicHtml);
    }

    public function test_hour_and_minute_steps_remain_enforced_in_the_custom_time_menus(): void
    {
        $html = TwelveHourDateTimePickerView::decorate($this->embeddedFilamentMarkup(), 'en-GB', 'stepped_time', 2, 15);

        $this->assertStringContainsString('x-bind:disabled=', $html);
        $this->assertStringContainsString('>15</button>', $html);
        $this->assertStringContainsString('>59</button>', $html);
        $this->assertStringContainsString('role="combobox"', $html);
    }

    public function test_time_only_fields_keep_filaments_floating_path_without_popover_marker(): void
    {
        $html = TwelveHourDateTimePickerView::decorate(
            $this->embeddedFilamentMarkup(),
            'en',
            'reminder_time',
            hasDate: false,
        );

        $this->assertStringNotContainsString('data-cible-date-time-picker-panel', $html);
        $this->assertStringNotContainsString('popover="manual"', $html);
        $this->assertStringContainsString('x-bind:value="hourDraft"', $html);
        $this->assertStringContainsString('>AM</button>', $html);
        $this->assertStringContainsString('>PM</button>', $html);
    }

    public function test_real_filament_fields_render_the_override_and_keep_the_date_only_picker(): void
    {
        $html = Livewire::test(DateTimePickerRenderingComponent::class)->html();

        $this->assertStringContainsString('x-data="dateTimePickerFormComponent(', $html);
        $this->assertStringContainsString('x-bind:value="hourDraft"', $html);
        $this->assertStringContainsString('>AM</button>', $html);
        $this->assertStringContainsString('>PM</button>', $html);
        $this->assertStringContainsString('x-ref="maxDate"', $html);
        $this->assertStringContainsString('x-ref="minDate"', $html);
        $this->assertStringContainsString('x-ref="disabledDates"', $html);
        $this->assertStringNotContainsString('type="date"', $html);
        $this->assertSame(4, substr_count($html, 'x-ref="button"'));
        $this->assertSame(3, substr_count($html, 'data-cible-date-time-picker-panel'));
        $this->assertSame(3, substr_count($html, 'fi-fo-date-time-picker-time-inputs'));
    }

    public function test_real_filament_picker_defaults_follow_the_request_locale_without_changing_canonical_state(): void
    {
        $this->withHeader('Accept-Language', 'en-GB, ar-EG;q=0.9');
        app()->forgetScopedInstances();
        $englishHtml = Livewire::test(DateTimePickerRenderingComponent::class)->html();

        $this->assertStringContainsString('data-datetime-locale="en"', $englishHtml);
        $this->assertStringContainsString('data-datetime-format="Y-m-d H:i"', $englishHtml);
        $this->assertStringContainsString('data-datetime-display-format="M j, Y h:i A"', $englishHtml);
        $this->assertStringContainsString("displayFormat: 'MMM D, YYYY hh:mm A'", $englishHtml);
        $this->assertStringContainsString('data-datetime-timezone="Africa/Cairo"', $englishHtml);
        $this->assertStringContainsString('data-datetime-native="false"', $englishHtml);
        $this->assertStringContainsString('data-date-format="Y-m-d"', $englishHtml);
        $this->assertStringContainsString('data-date-display-format="M j, Y"', $englishHtml);
        $this->assertStringContainsString('data-date-native="false"', $englishHtml);
        $this->assertStringContainsString("locale: 'en'", $englishHtml);
        $this->assertStringContainsString('>AM</button>', $englishHtml);
        $this->assertStringContainsString('>PM</button>', $englishHtml);

        $this->assertSame('ar', config('app.locale'));
    }

    public function test_picker_theme_keeps_the_floating_calendar_compact_without_modal_scrolling(): void
    {
        $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertNotFalse($theme);
        $this->assertStringContainsString('width: min(16rem, var(--cible-picker-available-width, calc(100vw - 1rem))) !important;', $theme);
        $this->assertStringContainsString('padding: 0.75rem !important;', $theme);
        $this->assertStringContainsString('min-height: 1.75rem;', $theme);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr) auto;', $theme);
        $this->assertStringContainsString('.fi-fo-date-time-picker-panel.fi-cible-date-time-picker-panel-open {', $theme);
        $this->assertStringContainsString('position: fixed !important;', $theme);
        $this->assertStringContainsString('z-index: 10000 !important;', $theme);
        $this->assertStringContainsString('max-height: none !important;', $theme);
        $this->assertStringContainsString('overflow: visible !important;', $theme);
        $this->assertStringContainsString('background-color: #ffffff !important;', $theme);
        $this->assertStringContainsString('--cible-picker-available-width', $theme);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) 4.75rem !important;', $theme);
        $this->assertStringContainsString('.fi-fo-date-time-picker-panel.fi-cible-date-time-picker-panel-compact', $theme);
        $this->assertStringContainsString('grid-template-columns: repeat(7, minmax(0, 1fr)) !important;', $theme);
        $this->assertStringNotContainsString('fi-date-time-picker-open', $theme);
        $this->assertStringNotContainsString('.fi-cible-date-time-picker-panel .fi-fo-date-time-picker-calendar-header', $theme);
        $this->assertStringNotContainsString('fi-date-time-picker-scroll-expanded', $theme);
    }

    public function test_popover_adapter_preserves_the_picker_tree_and_filament_lifecycle(): void
    {
        $script = file_get_contents(resource_path('js/filament/admin/date-time-picker-portal.js'));
        $portalView = file_get_contents(resource_path('views/filament/admin/date-time-picker-portal.blade.php'));
        $panelProvider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

        $this->assertNotFalse($script);
        $this->assertNotFalse($portalView);
        $this->assertNotFalse($panelProvider);
        $this->assertStringContainsString("@vite('resources/js/filament/admin/date-time-picker-portal.js')", $portalView);
        $this->assertStringContainsString("view('filament.admin.date-time-picker-portal')", $panelProvider);
        $this->assertStringNotContainsString('document.body.appendChild', $script);
        $this->assertStringNotContainsString('container.contains =', $script);
        $this->assertStringNotContainsString('handlePortalKeydown', $script);
        $this->assertStringContainsString('typeof panel.showPopover', $script);
        $this->assertStringContainsString("panel.setAttribute('popover', 'manual')", $script);
        $this->assertStringContainsString('data.togglePanelVisibility = function', $script);
        $this->assertStringContainsString('data.closePanel = function', $script);
        $this->assertStringContainsString('cancelFilamentPositioning(data)', $script);
        $this->assertStringContainsString('state.panel.showPopover()', $script);
        $this->assertStringContainsString('state.panel.hidePopover()', $script);
        $this->assertStringContainsString('visualViewport.offsetLeft', $script);
        $this->assertStringContainsString('visualViewport.offsetTop', $script);
        $this->assertStringContainsString('viewportPadding = 8', $script);
        $this->assertStringContainsString("document.addEventListener('livewire:navigating', closeAllOpenPickers)", $script);
        $this->assertStringContainsString('disablePopoverForState(state)', $script);
        $this->assertStringContainsString("state.panel.removeAttribute('popover')", $script);
        $this->assertStringNotContainsString('scrollTop', $script);
        $this->assertStringNotContainsString('scrollIntoView', $script);
        $this->assertStringNotContainsString('fi-date-time-picker-open', $script);
    }

    private function embeddedFilamentMarkup(): string
    {
        return <<<'HTML'
<div class="fi-fo-date-time-picker">
    <div x-load x-load-src="date-time-picker.js" x-data="dateTimePickerFormComponent({ state: $wire.$entangle('scheduled_at') })" wire:ignore>
        <button x-ref="button" type="button"></button>
        <input x-ref="maxDate" type="hidden" value="" />
        <input x-ref="minDate" type="hidden" value="" />
        <input x-ref="disabledDates" type="hidden" value="[]" />
        <div x-ref="panel" class="fi-fo-date-time-picker-panel" x-show="isPanelOpen">
            <div x-ref="calendar"></div>
            <div class="fi-fo-date-time-picker-time-inputs">
                <input max="23" min="0" step="1" type="number" inputmode="numeric" aria-label="Hour" x-model.debounce="hour" />
                <span class="fi-fo-date-time-picker-time-input-separator">:</span>
                <input max="59" min="0" step="1" type="number" inputmode="numeric" aria-label="Minute" x-model.debounce="minute" />
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
