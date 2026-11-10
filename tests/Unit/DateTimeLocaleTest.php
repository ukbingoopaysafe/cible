<?php

namespace Tests\Unit;

use App\Support\DateTimeLocale;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DateTimeLocaleTest extends TestCase
{
    #[DataProvider('acceptLanguageCases')]
    public function test_it_uses_only_the_first_accept_language_range(
        ?string $header,
        string $language,
        string $tag,
        string $pickerLocale,
    ): void {
        $locale = DateTimeLocale::fromAcceptLanguage($header);

        $this->assertSame($language, $locale->getLanguage());
        $this->assertSame($tag, $locale->getRegionalTag());
        $this->assertSame($pickerLocale, $locale->getPickerLocale());
        $this->assertSame('ar', config('app.locale'));
    }

    public static function acceptLanguageCases(): array
    {
        return [
            'English US ignores q and later ranges' => ['en-US;q=0.2, ar-EG;q=1', 'en', 'en-US', 'en'],
            'English GB retains region' => ['EN_gb, ar', 'en', 'en-GB', 'en'],
            'Arabic Egypt retains region' => ['ar-EG, en-US;q=0.9', 'ar', 'ar-EG', 'ar'],
            'bare English expands using ICU likely region' => ['en', 'en', 'en-US', 'en'],
            'bare Arabic expands using ICU likely region' => ['ar', 'ar', 'ar-EG', 'ar'],
            'unsupported first range ignores supported second range' => ['fr-FR, en-US', 'ar', 'ar-EG', 'ar'],
            'malformed first range ignores supported second range' => ['en--US, ar-EG', 'ar', 'ar-EG', 'ar'],
            'missing header falls back to Arabic' => [null, 'ar', 'ar-EG', 'ar'],
            'empty first range falls back to Arabic' => [', en-US', 'ar', 'ar-EG', 'ar'],
            'underscore normalizes to hyphen' => ['ar_EG', 'ar', 'ar-EG', 'ar'],
        ];
    }

    public function test_scoped_locale_is_resolved_again_for_each_request(): void
    {
        app()->instance('request', Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'en-GB, ar-EG;q=0.9',
        ]));
        app()->forgetScopedInstances();
        $this->assertSame('en-GB', app(DateTimeLocale::class)->getRegionalTag());

        app()->instance('request', Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'fr-FR, en-US',
        ]));
        app()->forgetScopedInstances();
        $this->assertSame('ar-EG', app(DateTimeLocale::class)->getRegionalTag());
        $this->assertSame('ar', config('app.locale'));
    }
}
