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

    public function test_country_mismatch_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        (new CustomerPhoneNormalizer)->normalize('+12025550123', 'EG');
    }
}
