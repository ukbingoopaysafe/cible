<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandExperienceTest extends TestCase
{
    public function test_the_public_entry_point_uses_the_cible_brand_experience(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Cible Real Estate')
            ->assertSee('dir="rtl"', false)
            ->assertSee(route('brand.logo'), false)
            ->assertSee(route('brand.icon'), false);
    }

    public function test_brand_assets_are_served_from_the_project_assets(): void
    {
        $this->get(route('brand.logo'))
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml');

        $this->get(route('brand.icon'))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->get(route('brand.pattern'))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }
}
