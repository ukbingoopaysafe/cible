<?php

namespace App\Support;

use LogicException;

final class TwelveHourDateTimePickerView
{
    private const PANEL_MARKER = 'data-cible-date-time-picker-panel';

    public static function decorate(
        string $html,
        string $locale,
        string $fieldId,
        int $hoursStep = 1,
        int $minutesStep = 1,
        bool $hasTime = true,
        bool $hasDate = true,
    ): string {
        $pickerLocale = preg_match('/^en(?:-|$)/i', $locale) === 1 ? 'en' : 'ar';

        if ($hasTime) {
            $rowCount = 0;
            $html = preg_replace_callback(
                '/<div class="fi-fo-date-time-picker-time-inputs">([\s\S]*?)<\/div>/',
                static function (array $matches) use ($fieldId, $hoursStep, $minutesStep, $pickerLocale): string {
                    $controls = $matches[1];
                    $hours = max(1, $hoursStep);
                    $minutes = max(1, $minutesStep);

                    $hourCount = 0;
                    $controls = preg_replace_callback(
                        '/<input\b(?=[^>]*\bx-model\.debounce="hour")[^>]*\/>/',
                        static fn (): string => self::hourControl($pickerLocale, $hours, $fieldId),
                        $controls,
                        1,
                        $hourCount,
                    ) ?? $controls;

                    $minuteCount = 0;
                    $controls = preg_replace_callback(
                        '/<input\b(?=[^>]*\bx-model\.debounce="minute")[^>]*\/>/',
                        static fn (): string => self::minuteControl($pickerLocale, $minutes, $fieldId),
                        $controls,
                        1,
                        $minuteCount,
                    ) ?? $controls;

                    if (($hourCount !== 1) || ($minuteCount !== 1)) {
                        throw new LogicException('The installed Filament date/time picker time controls changed; expected hour and minute inputs were not found.');
                    }

                    return '<div' . self::attributes([
                        'class' => 'fi-fo-date-time-picker-time-inputs',
                        'dir' => 'ltr',
                        'x-data' => "{ activeTimeMenu: null, hourDraft: '', minuteDraft: '', suppressMenuFocus: false }",
                        'x-init' => "hourDraft = String((Number(hour) % 12) || 12).padStart(2, '0'); minuteDraft = String(Number(minute)).padStart(2, '0')",
                        'x-effect' => "hourDraft = String((Number(hour) % 12) || 12).padStart(2, '0'); minuteDraft = String(Number(minute)).padStart(2, '0')",
                        'x-on:focusout' => '$nextTick(() => { if (! $el.contains(document.activeElement)) activeTimeMenu = null })',
                        'x-on:click.outside' => 'activeTimeMenu = null',
                    ]) . '>'
                        . $controls
                        . self::periodControl($pickerLocale)
                        . '</div>';
                },
                $html,
                1,
                $rowCount,
            ) ?? $html;

            if ($rowCount !== 1) {
                throw new LogicException('The installed Filament date/time picker time row changed; the 12-hour controls were not rendered.');
            }
        }

        if ($hasDate) {
            $panelCount = 0;
            $html = preg_replace_callback(
                '/<div\b(?=[^>]*\bx-ref="panel")[^>]*>/',
                static fn (array $matches): string => substr($matches[0], 0, -1) . ' ' . self::PANEL_MARKER . '>',
                $html,
                1,
                $panelCount,
            ) ?? $html;

            if ($panelCount !== 1) {
                throw new LogicException('The installed Filament date picker panel changed; the top-layer behavior was not attached.');
            }
        }

        return $html;
    }

    private static function hourControl(string $locale, int $step, string $fieldId): string
    {
        $label = $locale === 'en' ? 'Hour' : 'الساعة';
        $chooseLabel = $locale === 'en' ? 'Choose hour' : 'اختر الساعة';
        $invalidMessage = $locale === 'en' ? 'Choose an hour that matches the configured interval.' : 'اختر ساعة متوافقة مع الفاصل المحدد.';
        $inputId = e($fieldId . '-hour');
        $listId = e($fieldId . '-hour-options');
        $options = '';

        foreach (range(1, 12) as $hour) {
            $formattedHour = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $hour24 = '((' . $hour . ' % 12) + (Number(hour) >= 12 ? 12 : 0))';
            $options .= '<button' . self::attributes([
                'type' => 'button',
                'role' => 'option',
                'aria-selected' => 'false',
                'class' => 'fi-fo-date-time-picker-time-option',
                'x-bind:disabled' => '(' . $hour24 . ' % ' . $step . ') !== 0',
                'x-bind:aria-selected' => '((Number(hour) % 12) || 12) === ' . $hour,
                'x-on:mousedown.prevent' => 'true',
                'x-on:keydown.arrow-down.prevent' => 'const options = [...$el.parentElement.querySelectorAll(\'[role=option]:not(:disabled)\')]; options[options.indexOf($el) + 1]?.focus()',
                'x-on:keydown.arrow-up.prevent' => 'const options = [...$el.parentElement.querySelectorAll(\'[role=option]:not(:disabled)\')]; options[options.indexOf($el) - 1]?.focus()',
                'x-on:keydown.escape.prevent.stop' => 'suppressMenuFocus = true; activeTimeMenu = null; $refs.hourInput.focus(); $nextTick(() => suppressMenuFocus = false)',
                'x-on:click.stop' => 'hour = ' . $hour24 . '; hourDraft = \'' . $formattedHour . '\'; suppressMenuFocus = true; activeTimeMenu = null; $refs.hourInput.focus(); $nextTick(() => suppressMenuFocus = false)',
            ]) . '>' . $formattedHour . '</button>';
        }

        $inputChange = 'const raw = $event.target.value.trim(); const value = Number(raw); const periodOffset = Number(hour) >= 12 ? 12 : 0; const canonicalHour = (value % 12) + periodOffset; if (raw === \'\' || !Number.isInteger(value) || value < 1 || value > 12 || (canonicalHour % ' . $step . ') !== 0) { $event.target.setCustomValidity(' . json_encode($invalidMessage, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) . '); $event.target.reportValidity(); hourDraft = String((Number(hour) % 12) || 12).padStart(2, \'0\'); return; } $event.target.setCustomValidity(\'\'); hour = canonicalHour;';
        $toggleLabel = e($chooseLabel);

        return '<div class="fi-fo-date-time-picker-time-control fi-fo-date-time-picker-hour-control">'
            . '<input' . self::attributes([
                'id' => $inputId,
                'x-ref' => 'hourInput',
                'class' => 'fi-fo-date-time-picker-time-input',
                'type' => 'text',
                'maxlength' => '2',
                'pattern' => '(?:0?[1-9]|1[0-2])',
                'inputmode' => 'numeric',
                'autocomplete' => 'off',
                'role' => 'combobox',
                'aria-haspopup' => 'listbox',
                'aria-controls' => $listId,
                'aria-label' => $label,
                'x-bind:value' => 'hourDraft',
                'x-bind:aria-expanded' => "(activeTimeMenu === 'hour').toString()",
                'x-on:focus' => "if (! suppressMenuFocus) activeTimeMenu = 'hour'",
                'x-on:click' => "activeTimeMenu = 'hour'",
                'x-on:input' => '$event.target.setCustomValidity(\'\'); hourDraft = $event.target.value',
                'x-on:change' => $inputChange,
                'x-on:blur' => 'checkTimeInputValidity($event)',
                'x-on:invalid' => 'timeInputInvalid($event)',
                'x-on:keydown.arrow-down.prevent' => '$refs.hourOptions.querySelector(\'[role=option]:not(:disabled)\')?.focus()',
                'x-on:keydown.enter.prevent' => '$event.target.dispatchEvent(new Event(\'change\', { bubbles: true }))',
                'x-on:keydown.escape.prevent.stop' => 'activeTimeMenu = null',
            ]) . ' />'
            . '<button' . self::attributes([
                'type' => 'button',
                'class' => 'fi-fo-date-time-picker-time-menu-toggle',
                'aria-label' => $toggleLabel,
                'aria-controls' => $listId,
                'x-bind:aria-expanded' => "(activeTimeMenu === 'hour').toString()",
                'x-on:click.stop' => "activeTimeMenu = activeTimeMenu === 'hour' ? null : 'hour'",
            ]) . '>' . self::chevron() . '</button>'
            . '<div' . self::attributes([
                'x-ref' => 'hourOptions',
                'id' => $listId,
                'role' => 'listbox',
                'aria-label' => $toggleLabel,
                'class' => 'fi-fo-date-time-picker-time-options',
                'x-cloak' => '',
                'x-show' => "activeTimeMenu === 'hour'",
            ]) . '>' . $options . '</div></div>';
    }

    private static function minuteControl(string $locale, int $step, string $fieldId): string
    {
        $label = $locale === 'en' ? 'Minute' : 'الدقيقة';
        $chooseLabel = $locale === 'en' ? 'Choose minute' : 'اختر الدقيقة';
        $invalidMessage = $locale === 'en' ? 'Enter a minute within the configured interval.' : 'أدخل دقيقة ضمن الفاصل المحدد.';
        $inputId = e($fieldId . '-minute');
        $listId = e($fieldId . '-minute-options');
        $options = '';

        foreach (range(0, 59) as $minute) {
            $formattedMinute = str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
            $options .= '<button' . self::attributes([
                'type' => 'button',
                'role' => 'option',
                'aria-selected' => 'false',
                'class' => 'fi-fo-date-time-picker-time-option',
                'x-bind:disabled' => '(' . $minute . ' % ' . $step . ') !== 0',
                'x-bind:aria-selected' => 'Number(minute) === ' . $minute,
                'x-on:mousedown.prevent' => 'true',
                'x-on:keydown.arrow-down.prevent' => 'const options = [...$el.parentElement.querySelectorAll(\'[role=option]:not(:disabled)\')]; options[options.indexOf($el) + 1]?.focus()',
                'x-on:keydown.arrow-up.prevent' => 'const options = [...$el.parentElement.querySelectorAll(\'[role=option]:not(:disabled)\')]; options[options.indexOf($el) - 1]?.focus()',
                'x-on:keydown.escape.prevent.stop' => 'suppressMenuFocus = true; activeTimeMenu = null; $refs.minuteInput.focus(); $nextTick(() => suppressMenuFocus = false)',
                'x-on:click.stop' => 'minute = ' . $minute . '; minuteDraft = \'' . $formattedMinute . '\'; suppressMenuFocus = true; activeTimeMenu = null; $refs.minuteInput.focus(); $nextTick(() => suppressMenuFocus = false)',
            ]) . '>' . $formattedMinute . '</button>';
        }

        $inputChange = 'const raw = $event.target.value.trim(); const value = Number(raw); if (raw === \'\' || !Number.isInteger(value) || value < 0 || value > 59 || (value % ' . $step . ') !== 0) { $event.target.setCustomValidity(' . json_encode($invalidMessage, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) . '); $event.target.reportValidity(); minuteDraft = String(Number(minute)).padStart(2, \'0\'); return; } $event.target.setCustomValidity(\'\'); minute = value;';
        $toggleLabel = e($chooseLabel);

        return '<div class="fi-fo-date-time-picker-time-control fi-fo-date-time-picker-minute-control">'
            . '<input' . self::attributes([
                'id' => $inputId,
                'x-ref' => 'minuteInput',
                'class' => 'fi-fo-date-time-picker-time-input',
                'type' => 'text',
                'maxlength' => '2',
                'pattern' => '[0-5]?[0-9]',
                'inputmode' => 'numeric',
                'autocomplete' => 'off',
                'role' => 'combobox',
                'aria-haspopup' => 'listbox',
                'aria-controls' => $listId,
                'aria-label' => $label,
                'x-bind:value' => 'minuteDraft',
                'x-bind:aria-expanded' => "(activeTimeMenu === 'minute').toString()",
                'x-on:focus' => "if (! suppressMenuFocus) activeTimeMenu = 'minute'",
                'x-on:click' => "activeTimeMenu = 'minute'",
                'x-on:input' => '$event.target.setCustomValidity(\'\'); minuteDraft = $event.target.value',
                'x-on:change' => $inputChange,
                'x-on:blur' => 'checkTimeInputValidity($event)',
                'x-on:invalid' => 'timeInputInvalid($event)',
                'x-on:keydown.arrow-down.prevent' => '$refs.minuteOptions.querySelector(\'[role=option]:not(:disabled)\')?.focus()',
                'x-on:keydown.enter.prevent' => '$event.target.dispatchEvent(new Event(\'change\', { bubbles: true }))',
                'x-on:keydown.escape.prevent.stop' => 'activeTimeMenu = null',
            ]) . ' />'
            . '<button' . self::attributes([
                'type' => 'button',
                'class' => 'fi-fo-date-time-picker-time-menu-toggle',
                'aria-label' => $toggleLabel,
                'aria-controls' => $listId,
                'x-bind:aria-expanded' => "(activeTimeMenu === 'minute').toString()",
                'x-on:click.stop' => "activeTimeMenu = activeTimeMenu === 'minute' ? null : 'minute'",
            ]) . '>' . self::chevron() . '</button>'
            . '<div' . self::attributes([
                'x-ref' => 'minuteOptions',
                'id' => $listId,
                'role' => 'listbox',
                'aria-label' => $toggleLabel,
                'class' => 'fi-fo-date-time-picker-time-options',
                'x-cloak' => '',
                'x-show' => "activeTimeMenu === 'minute'",
            ]) . '>' . $options . '</div></div>';
    }

    private static function periodControl(string $locale): string
    {
        $legend = $locale === 'en' ? 'AM or PM' : 'الفترة';
        $am = $locale === 'en' ? 'AM' : 'ص';
        $pm = $locale === 'en' ? 'PM' : 'م';

        return '<div class="fi-fo-date-time-picker-period" dir="ltr" role="group" aria-label="' . e($legend) . '">'
            . '<button type="button" class="fi-fo-date-time-picker-period-option" aria-pressed="false" x-bind:aria-pressed="(Number(hour) < 12).toString()" x-on:click.stop="hour = Number(hour) % 12; activeTimeMenu = null">' . e($am) . '</button>'
            . '<button type="button" class="fi-fo-date-time-picker-period-option" aria-pressed="false" x-bind:aria-pressed="(Number(hour) >= 12).toString()" x-on:click.stop="hour = (Number(hour) % 12) + 12; activeTimeMenu = null">' . e($pm) . '</button>'
            . '</div>';
    }

    private static function chevron(): string
    {
        return '<svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="fi-fo-date-time-picker-time-menu-icon"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>';
    }

    private static function attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if ($value === '') {
                $html .= ' ' . $name;

                continue;
            }

            $html .= ' ' . $name . '="' . e((string) $value) . '"';
        }

        return $html;
    }
}
