<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandExperienceTest extends TestCase
{
    public function test_the_root_url_redirects_to_the_admin_login_page(): void
    {
        $this->get('/')
            ->assertRedirect('/admin/login')
            ->assertHeader('Location', '/admin/login');
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
