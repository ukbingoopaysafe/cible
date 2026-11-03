<?php

namespace Tests\Unit;

use App\Services\CustomerPhoneNormalizer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerPhoneNormalizerTest extends TestCase
{
    public function test_local_egyptian_mobile_is_normalized_to_e164(): void
    {
        $phone = (new CustomerPhoneNormalizer)->normalize('01012345678', 'EG');

        $this->assertSame('+201012345678', $phone['phone_e164']);
        $this->assertSame('EG', $phone['phone_country']);
    }

    public function test_saudi_and_uae_mobiles_follow_their_own_rules(): void
    {
        $saudi = (new CustomerPhoneNormalizer)->normalize('0512345678', 'SA');
        $emirati = (new CustomerPhoneNormalizer)->normalize('0501234567', 'AE');

        $this->assertSame('+966512345678', $saudi['phone_e164']);
        $this->assertSame('SA', $saudi['phone_country']);
        $this->assertSame('+971501234567', $emirati['phone_e164']);
        $this->assertSame('AE', $emirati['phone_country']);
    }

    public function test_us_selection_accepts_canadian_numbers_and_stores_the_selected_country(): void
    {
        $phone = (new CustomerPhoneNormalizer)->normalize('5062345678', 'US');

        $this->assertSame('+15062345678', $phone['phone_e164']);
        $this->assertSame('US', $phone['phone_country']);
    }

    public function test_other_option_requires_an_international_prefix(): void
    {
        $this->expectException(ValidationException::class);

        (new CustomerPhoneNormalizer)->normalize('0151123456789', 'ZZ');
    }

    public function test_other_option_detects_and_stores_the_number_country(): void
    {
        $phone = (new CustomerPhoneNormalizer)->normalize('+4915112345678', 'ZZ');

        $this->assertSame('+4915112345678', $phone['phone_e164']);
        $this->assertSame('DE', $phone['phone_country']);
    }

    public function test_invalid_number_length_is_rejected_for_the_selected_country(): void
    {
        try {
            (new CustomerPhoneNormalizer)->normalize('051234', 'SA');
            $this->fail('Expected an invalid phone number to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('phone', $exception->errors());
        }
    }

    public function test_country_mismatch_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        (new CustomerPhoneNormalizer)->normalize('+12025550123', 'EG');
    }

    public function test_unsupported_country_code_is_rejected(): void
    {
        try {
            (new CustomerPhoneNormalizer)->normalize('01012345678', 'Ej');
            $this->fail('Expected an unsupported country code to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('phone_country', $exception->errors());
        }
    }
}
