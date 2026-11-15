<?php

namespace App\Enums;

enum LeadSourceType: string
{
    case Awareness = 'awareness';
    case PersonReferral = 'person_referral';
    case Other = 'other';
    case LegacyContactMethod = 'legacy_contact_method';
    case UnclassifiedLegacy = 'unclassified_legacy';

    public function label(): string
    {
        return match ($this) {
            self::Awareness => 'مصدر تعرّف',
            self::PersonReferral => 'إحالة من شخص',
            self::Other => 'أخرى',
            self::LegacyContactMethod => 'وسيلة تواصل تاريخية',
            self::UnclassifiedLegacy => 'مصدر قديم غير مصنف',
        };
    }

    /** @return array<string, string> */
    public static function managerCreatableOptions(): array
    {
        return [
            self::Awareness->value => self::Awareness->label(),
            self::PersonReferral->value => self::PersonReferral->label(),
        ];
    }

    public function isSelectableForNewRequests(): bool
    {
        return in_array($this, [self::Awareness, self::PersonReferral, self::Other], true);
    }
}
