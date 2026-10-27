<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\Area;
use App\Models\User;
use App\Services\AreaArchiveService;
use App\Services\AreaService;
use App\Services\StaffManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class UnifiedAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_unified_area_and_it_syncs_to_legacy_tables(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(AreaService::class);

        $area = $service->create($manager, [
            'governorate' => 'القاهرة',
            'name' => 'الشروق الجديدة',
        ]);

        $this->assertInstanceOf(Area::class, $area);
        $this->assertDatabaseHas('areas', [
            'governorate' => 'القاهرة',
            'name' => 'الشروق الجديدة',
        ]);
        $this->assertDatabaseHas('rental_areas', [
            'governorate' => 'القاهرة',
            'name' => 'الشروق الجديدة',
        ]);
        $this->assertDatabaseHas('resale_areas', [
            'governorate' => 'القاهرة',
            'name' => 'الشروق الجديدة',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'subject_type' => Area::class,
            'subject_id' => $area->id,
            'event_key' => 'area.created',
        ]);
    }

    public function test_staff_with_permission_can_create_unified_area_while_others_are_forbidden(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $staffService = app(StaffManagementService::class);

        $permittedUser = $staffService->createEmployee($manager, [
            'name' => 'موظف مخول',
            'email' => 'permitted@example.test',
            'password' => 'secret-password-123',
            'departments' => [Department::Rental->value],
            'can_add_rental_areas' => true,
        ]);

        $forbiddenUser = $staffService->createEmployee($manager, [
            'name' => 'موظف غير مخول',
            'email' => 'forbidden@example.test',
            'password' => 'secret-password-123',
            'departments' => [Department::Resale->value],
            'can_add_rental_areas' => false,
        ]);

        $service = app(AreaService::class);

        $area = $service->create($permittedUser, [
            'governorate' => 'الجيزة',
            'name' => 'منطقة تجريبية فريدة 1',
        ]);
        $this->assertInstanceOf(Area::class, $area);

        try {
            $service->create($forbiddenUser, [
                'governorate' => 'الجيزة',
                'name' => 'منطقة مرفوضة فريدة',
            ]);
            $this->fail('Unauthorized user should not create area.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_duplicate_area_name_in_same_governorate_is_rejected(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(AreaService::class);

        $service->create($manager, [
            'governorate' => 'القاهرة',
            'name' => 'منطقة مكررة فريدة',
        ]);

        $this->expectException(ValidationException::class);

        $service->create($manager, [
            'governorate' => 'القاهرة',
            'name' => 'منطقة مكررة فريدة',
        ]);
    }

    public function test_same_area_name_in_different_governorate_is_allowed(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(AreaService::class);

        $cairo = $service->create($manager, [
            'governorate' => 'القاهرة',
            'name' => 'منطقة مشتركة فريدة',
        ]);

        $giza = $service->create($manager, [
            'governorate' => 'الجيزة',
            'name' => 'منطقة مشتركة فريدة',
        ]);

        $this->assertNotSame($cairo->id, $giza->id);
    }

    public function test_manager_can_archive_and_restore_unified_area_syncing_across_tables(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $areaService = app(AreaService::class);
        $archiveService = app(AreaArchiveService::class);

        $area = $areaService->create($manager, [
            'governorate' => 'القاهرة',
            'name' => 'المعادي منطقة فريدة',
        ]);

        $archiveService->archiveUnified($manager, $area->id);

        $this->assertSoftDeleted('areas', ['id' => $area->id]);
        $this->assertSoftDeleted('rental_areas', ['governorate' => 'القاهرة', 'name' => 'المعادي منطقة فريدة']);
        $this->assertSoftDeleted('resale_areas', ['governorate' => 'القاهرة', 'name' => 'المعادي منطقة فريدة']);

        $this->assertDatabaseHas('audit_events', [
            'subject_type' => Area::class,
            'subject_id' => $area->id,
            'event_key' => 'area.archived',
        ]);

        $archiveService->restoreUnified($manager, $area->id);

        $this->assertNotSoftDeleted('areas', ['id' => $area->id]);
        $this->assertNotSoftDeleted('rental_areas', ['governorate' => 'القاهرة', 'name' => 'المعادي منطقة فريدة']);
        $this->assertNotSoftDeleted('resale_areas', ['governorate' => 'القاهرة', 'name' => 'المعادي منطقة فريدة']);

        $this->assertDatabaseHas('audit_events', [
            'subject_type' => Area::class,
            'subject_id' => $area->id,
            'event_key' => 'area.restored',
        ]);
    }

    public function test_filament_areas_resource_accessible_for_manager_and_permitted_staff(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $permitted = User::factory()->create(['is_company_manager' => false, 'can_add_rental_areas' => true]);
        $permitted->departmentMemberships()->create(['department' => Department::Rental]);

        $unauthorized = User::factory()->create(['is_company_manager' => false, 'can_add_rental_areas' => false]);
        $unauthorized->departmentMemberships()->create(['department' => Department::Rental]);

        $this->actingAs($manager);
        $this->get('/admin/areas')->assertOk();

        $this->actingAs($permitted);
        $this->get('/admin/areas')->assertOk();

        $this->actingAs($unauthorized);
        $this->get('/admin/areas')->assertForbidden();
    }
}
