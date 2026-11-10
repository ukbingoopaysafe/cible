<div
    data-datetime-locale="{{ $dateTimePickerMetadata['locale'] }}"
    data-datetime-format="{{ $dateTimePickerMetadata['format'] }}"
    data-datetime-display-format="{{ $dateTimePickerMetadata['displayFormat'] }}"
    data-datetime-timezone="{{ $dateTimePickerMetadata['timezone'] }}"
    data-datetime-native="{{ $dateTimePickerMetadata['native'] ? 'true' : 'false' }}"
    data-date-locale="{{ $datePickerMetadata['locale'] }}"
    data-date-format="{{ $datePickerMetadata['format'] }}"
    data-date-display-format="{{ $datePickerMetadata['displayFormat'] }}"
    data-date-native="{{ $datePickerMetadata['native'] ? 'true' : 'false' }}"
>
    {{ $this->getSchema('form') }}
</div>
