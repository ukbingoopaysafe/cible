<?php

namespace Tests\Unit;

use App\Support\PhoneCountries;
use Tests\TestCase;

class PhoneCountriesTest extends TestCase
{
    public function test_options_are_alphabetical_with_flag_name_and_dial_code_and_end_with_other(): void
    {
        $options = PhoneCountries::options();

        $this->assertSame('DZ', array_key_first($options));
        $this->assertSame('🌐 Other', $options['ZZ']);
        $this->assertSame('ZZ', array_key_last($options));
        $this->assertSame('<img src="https://flagcdn.com/24x18/eg.png" alt="" aria-hidden="true"> Egypt (+20)', $options['EG']);
        $this->assertSame('<img src="https://flagcdn.com/24x18/sa.png" alt="" aria-hidden="true"> Saudi Arabia (+966)', $options['SA']);
        $this->assertSame('<img src="https://flagcdn.com/24x18/ae.png" alt="" aria-hidden="true"> UAE (+971)', $options['AE']);
        $this->assertSame('<img src="https://flagcdn.com/24x18/us.png" alt="" aria-hidden="true"> USA/Canada (+1)', $options['US']);
        $this->assertSame('<img src="https://flagcdn.com/24x18/dz.png" alt="" aria-hidden="true"> Algeria (+213)', $options['DZ']);

        $countryNames = array_map(
            fn (string $label): string => preg_replace('/^<img [^>]+> (.+) \(\+\d+\)$/', '$1', $label),
            array_slice($options, 0, -1),
        );

        $sortedNames = $countryNames;
        natcasesort($sortedNames);

        $this->assertSame(array_values($sortedNames), array_values($countryNames));
    }

    public function test_national_number_is_formatted_for_the_selected_country(): void
    {
        $this->assertSame('01012345678', PhoneCountries::nationalNumber('+201012345678', 'EG'));
        $this->assertSame('0512345678', PhoneCountries::nationalNumber('+966512345678', 'SA'));
        $this->assertSame('2015550123', PhoneCountries::nationalNumber('+12015550123', 'US'));
    }

    public function test_national_number_keeps_international_format_for_other_countries(): void
    {
        $this->assertSame('+4915112345678', PhoneCountries::nationalNumber('+4915112345678', 'DE'));
        $this->assertSame('+4915112345678', PhoneCountries::nationalNumber('+4915112345678', 'ZZ'));
    }

    public function test_unknown_countries_are_mapped_to_the_other_option(): void
    {
        $this->assertSame('EG', PhoneCountries::selectable(null));
        $this->assertSame('SA', PhoneCountries::selectable('sa'));
        $this->assertSame('ZZ', PhoneCountries::selectable('DE'));
        $this->assertSame('Other', PhoneCountries::display('ZZ'));
        $this->assertSame('EG', PhoneCountries::display('EG'));
        $this->assertNull(PhoneCountries::display(null));
    }

    public function test_examples_are_compact_and_country_specific(): void
    {
        $this->assertSame('01001234567', PhoneCountries::example('EG'));
        $this->assertSame('0512345678', PhoneCountries::example('SA'));
        $this->assertSame('0501234567', PhoneCountries::example('AE'));
        $this->assertNull(PhoneCountries::example('ZZ'));
        $this->assertNull(PhoneCountries::example('DE'));
    }
}
