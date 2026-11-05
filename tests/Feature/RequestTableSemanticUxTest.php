<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\RentalRequestStage;
use App\Enums\RequestStatus;
use App\Enums\ResaleRequestStage;
use App\Filament\Resources\RentalRequests\Pages\ListRentalRequests;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Filament\Resources\ResaleRequests\Pages\ListResaleRequests;
use App\Filament\Resources\ResaleRequests\ResaleRequestResource;
use App\Filament\Support\SectionRequestResource;
use App\Models\RentalArea;
use App\Models\RentalCustomer;
use App\Models\RentalRequest;
use App\Models\ResaleArea;
use App\Models\ResaleCustomer;
use App\Models\ResaleRequest;
use App\Models\User;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RequestTableSemanticUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_badges_have_expected_semantic_colors(): void
    {
        $this->assertSame('success', SectionRequestResource::getStatusColor(RequestStatus::Active));
        $this->assertSame('warning', SectionRequestResource::getStatusColor(RequestStatus::Paused));
        $this->assertSame('gray', SectionRequestResource::getStatusColor(RequestStatus::Closed));

        // Test with string values
        $this->assertSame('success', SectionRequestResource::getStatusColor('active'));
        $this->assertSame('warning', SectionRequestResource::getStatusColor('paused'));
        $this->assertSame('gray', SectionRequestResource::getStatusColor('closed'));
    }

    public function test_stage_badges_have_distinct_meaningful_colors(): void
    {
        $stages = [
            'intake' => 'gray',
            'qualification' => 'info',
            'unit_search' => 'primary',
            'recommendation' => 'primary',
            'viewing' => 'warning',
            'negotiation' => 'success',
            // Contract signed stages (both English and Arabic)
            'contract_signed' => 'success',
            'signed' => 'success',
            'contract' => 'success',
            'تم توقيع العقد' => 'success',
            // Cancelled stages (both English and Arabic)
            'cancelled' => 'gray',
            'canceled' => 'gray',
            'ملغي' => 'gray',
            // Arabic stage labels
            'استقبال' => 'gray',
            'تأهيل' => 'info',
            'بحث عن وحدات' => 'primary',
            'ترشيح' => 'primary',
            'عرض / ترشيح' => 'primary',
            'معاينة' => 'warning',
            'تفاوض' => 'success',
        ];

        foreach ($stages as $stageKey => $expectedColor) {
            $this->assertSame($expectedColor, SectionRequestResource::getStageColor($stageKey));
            $rentalStage = RentalRequestStage::tryFrom($stageKey);
            if ($rentalStage) {
                $this->assertSame($expectedColor, SectionRequestResource::getStageColor($rentalStage));
            }
            $resaleStage = ResaleRequestStage::tryFrom($stageKey);
            if ($resaleStage) {
                $this->assertSame($expectedColor, SectionRequestResource::getStageColor($resaleStage));
            }
        }
    }

    public function test_follow_up_overdue_and_today_boundaries_for_active_requests(): void
    {
        Carbon::setTestNow('2026-11-01 12:00:00');

        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = RentalCustomer::query()->forceCreate([
            'name' => 'عميل تجريبي',
        ]);
        $area = RentalArea::query()->firstOrCreate(
            ['governorate' => 'القاهرة', 'name' => 'منطقة تجريبية للتاريخ']
        );

        // 1. Overdue: Active with past datetime
        $overdueRequest = RentalRequest::query()->forceCreate([
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'area_id' => $area->id,
            'contact_party' => 'customer',
            'status' => RequestStatus::Active,
            'stage' => RentalRequestStage::Intake,
            'next_follow_up_at' => Carbon::parse('2026-11-01 10:00:00'),
        ]);

        $this->assertSame('danger', SectionRequestResource::getFollowUpColor($overdueRequest));
        $this->assertSame('heroicon-m-exclamation-triangle', SectionRequestResource::getFollowUpIcon($overdueRequest));
        $this->assertSame('موعد المتابعة متأخر', SectionRequestResource::getFollowUpTooltip($overdueRequest));

        // 2. Due Today: Active with today's future datetime
        $todayRequest = RentalRequest::query()->forceCreate([
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'area_id' => $area->id,
            'contact_party' => 'customer',
            'status' => RequestStatus::Active,
            'stage' => RentalRequestStage::Intake,
            'next_follow_up_at' => Carbon::parse('2026-11-01 16:00:00'),
        ]);

        $this->assertSame('warning', SectionRequestResource::getFollowUpColor($todayRequest));
        $this->assertSame('heroicon-m-clock', SectionRequestResource::getFollowUpIcon($todayRequest));
        $this->assertSame('متابعة مستحقة اليوم', SectionRequestResource::getFollowUpTooltip($todayRequest));

        // 3. Future: Active with datetime after today
        $futureRequest = RentalRequest::query()->forceCreate([
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'area_id' => $area->id,
            'contact_party' => 'customer',
            'status' => RequestStatus::Active,
            'stage' => RentalRequestStage::Intake,
            'next_follow_up_at' => Carbon::parse('2026-11-05 14:00:00'),
        ]);

        $this->assertSame('gray', SectionRequestResource::getFollowUpColor($futureRequest));
        $this->assertNull(SectionRequestResource::getFollowUpIcon($futureRequest));
        $this->assertSame('متابعة قادمة', SectionRequestResource::getFollowUpTooltip($futureRequest));

        // 4. Missing date: null follow-up
        $nullDateRequest = RentalRequest::query()->forceCreate([
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'area_id' => $area->id,
            'contact_party' => 'customer',
            'status' => RequestStatus::Active,
            'stage' => RentalRequestStage::Intake,
            'next_follow_up_at' => null,
        ]);

        $this->assertSame('gray', SectionRequestResource::getFollowUpColor($nullDateRequest));
        $this->assertNull(SectionRequestResource::getFollowUpIcon($nullDateRequest));
        $this->assertNull(SectionRequestResource::getFollowUpTooltip($nullDateRequest));
    }

    public function test_closed_and_paused_requests_are_not_flagged_as_overdue(): void
    {
        Carbon::setTestNow('2026-11-01 12:00:00');

        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = ResaleCustomer::query()->forceCreate([
            'name' => 'عميل بيع تجريبي',
        ]);
        $area = ResaleArea::query()->firstOrCreate(
            ['governorate' => 'الجيزة', 'name' => 'منطقة بيع تجريبية']
        );

        // Closed request with past follow-up date must be neutral gray
        $closedRequest = ResaleRequest::query()->forceCreate([
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'area_id' => $area->id,
            'contact_party' => 'customer',
            'status' => RequestStatus::Closed,
            'stage' => ResaleRequestStage::Negotiation,
            'next_follow_up_at' => Carbon::parse('2026-10-20 10:00:00'),
        ]);

        $this->assertSame('gray', SectionRequestResource::getFollowUpColor($closedRequest));
        $this->assertNull(SectionRequestResource::getFollowUpIcon($closedRequest));
        $this->assertNull(SectionRequestResource::getFollowUpTooltip($closedRequest));

        // Paused request with past follow-up date must be neutral gray
        $pausedRequest = ResaleRequest::query()->forceCreate([
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'area_id' => $area->id,
            'contact_party' => 'customer',
            'status' => RequestStatus::Paused,
            'stage' => ResaleRequestStage::Viewing,
            'next_follow_up_at' => Carbon::parse('2026-10-25 10:00:00'),
        ]);

        $this->assertSame('gray', SectionRequestResource::getFollowUpColor($pausedRequest));
        $this->assertNull(SectionRequestResource::getFollowUpIcon($pausedRequest));
        $this->assertNull(SectionRequestResource::getFollowUpTooltip($pausedRequest));
    }

    public function test_customer_phone_number_formatting_preserves_value_with_ltr_isolation(): void
    {
        $rawPhone = '+201012345678';
        $formatted = SectionRequestResource::formatPhoneForDisplay($rawPhone);

        $this->assertStringContainsString($rawPhone, (string) $formatted);
        $this->assertStringContainsString('dir="ltr"', (string) $formatted);
    }

    public function test_tables_have_descriptive_search_placeholder_in_both_departments(): void
    {
        $expectedPlaceholder = 'بحث بالكود، العميل، الهاتف، أو المسؤول...';

        $rentalTable = RentalRequestResource::table(new \Filament\Tables\Table(new \App\Filament\Resources\RentalRequests\Pages\ListRentalRequests));
        $this->assertSame($expectedPlaceholder, $rentalTable->getSearchPlaceholder());

        $resaleTable = ResaleRequestResource::table(new \Filament\Tables\Table(new \App\Filament\Resources\ResaleRequests\Pages\ListResaleRequests));
        $this->assertSame($expectedPlaceholder, $resaleTable->getSearchPlaceholder());
    }
}
