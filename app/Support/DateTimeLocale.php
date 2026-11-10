<?php

namespace App\Support;

use IntlDateFormatter;
use Locale;

/**
 * Request-scoped locale policy for date and time content.
 *
 * The CRM's application locale remains Arabic. This value only controls date
 * and time presentation in the current HTTP request.
 */
final readonly class DateTimeLocale
{
    private function __construct(
        private string $language,
        private string $regionalTag,
    ) {}

    public static function fromAcceptLanguage(?string $header): self
    {
        $firstRange = trim(explode(',', $header ?? '', 2)[0] ?? '');
        $firstRange = trim(explode(';', $firstRange, 2)[0] ?? '');
        $firstRange = str_replace('_', '-', $firstRange);

        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{1,8})*$/D', $firstRange) !== 1) {
            return self::arabicFallback();
        }

        $icuTag = Locale::canonicalize(str_replace('-', '_', $firstRange));
        $language = strtolower(Locale::getPrimaryLanguage($icuTag) ?: '');

        if (! in_array($language, ['ar', 'en'], true)) {
            return self::arabicFallback();
        }

        // PHP's Intl Locale wrapper does not expose uloc_addLikelySubtags().
        // Asking ICU for the formatter's actual locale applies the same likely
        // locale data without relying on a hand-maintained region table.
        $likelyFormatter = new IntlDateFormatter($icuTag, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
        $likelyTag = $likelyFormatter->getLocale(Locale::ACTUAL_LOCALE) ?: $icuTag;
        $script = Locale::getScript($icuTag) ?: null;
        $region = Locale::getRegion($icuTag) ?: Locale::getRegion($likelyTag);

        // Bare-language formatters do not expose ICU's likely region through
        // PHP's Locale wrapper on all supported PHP builds. Use the ICU
        // language defaults represented by the approved examples.
        if ($region === '') {
            $region = $language === 'en' ? 'US' : 'EG';
        }
        $parts = [$language];

        if ($script !== null) {
            $parts[] = ucfirst(strtolower($script));
        }

        if ($region !== '') {
            $parts[] = ctype_alpha($region) ? strtoupper($region) : $region;
        }

        return new self($language, implode('-', $parts));
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getRegionalTag(): string
    {
        return $this->regionalTag;
    }

    public function getPickerLocale(): string
    {
        return $this->language;
    }

    public function getIntlLocale(): string
    {
        return $this->regionalTag . '-u-ca-gregory-nu-latn';
    }

    private static function arabicFallback(): self
    {
        return new self('ar', 'ar-EG');
    }
}
