<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminIpAllowlistTest extends TestCase
{
    public function test_empty_allowlist_does_not_restrict_admin_login(): void
    {
        config(['security.admin_allowed_ips' => []]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.12'])
            ->get('/admin/login')
            ->assertOk();
    }

    public function test_matching_cidr_can_access_admin_login(): void
    {
        config(['security.admin_allowed_ips' => ['198.51.100.0/24']]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.12'])
            ->get('/admin/login')
            ->assertOk();
    }

    public function test_non_matching_ip_is_forbidden_from_admin_login(): void
    {
        config(['security.admin_allowed_ips' => ['198.51.100.0/24']]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.12'])
            ->get('/admin/login')
            ->assertForbidden();
    }
}
