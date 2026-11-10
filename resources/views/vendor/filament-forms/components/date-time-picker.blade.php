@php
    $pickerHtml = $field->toEmbeddedHtml();

    $pickerHtml = \App\Support\TwelveHourDateTimePickerView::decorate(
        $pickerHtml,
        $field->getLocale(),
        $field->getId(),
        $field->getHoursStep(),
        $field->getMinutesStep(),
        $field->hasTime(),
        $field->hasDate(),
    );
@endphp

{!! $pickerHtml !!}
