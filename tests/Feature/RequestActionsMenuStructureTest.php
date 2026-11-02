<?php

namespace Tests\Feature;

use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Filament\Resources\ResaleRequests\ResaleRequestResource;
use App\Filament\Support\SectionRequestResource;
use Filament\Actions\ActionGroup;
use Filament\Tables\Table;
use Tests\TestCase;

class RequestActionsMenuStructureTest extends TestCase
{
    public function test_request_actions_menu_has_four_logical_categories_and_twenty_one_actions(): void
    {
        $group = RentalRequestResource::getRequestActionsGroup();

        $this->assertInstanceOf(ActionGroup::class, $group);
        $subGroups = $group->getActions();

        $this->assertCount(4, $subGroups, 'Expected exactly 4 categories in the request actions group.');

        // Category 1: العميل والمتابعة
        $this->assertInstanceOf(ActionGroup::class, $subGroups[0]);
        $this->assertSame('العميل والمتابعة', $subGroups[0]->getLabel());
        $this->assertFalse($subGroups[0]->hasDropdown(), 'Subgroup should not create nested dropdown.');
        $cat1Actions = array_map(fn ($action) => $action->getName(), $subGroups[0]->getActions());
        $this->assertSame(['edit', 'linkCustomer', 'setFollowUp', 'contactHistory', 'changeStage'], $cat1Actions);

        // Category 2: نقل المسؤولية
        $this->assertInstanceOf(ActionGroup::class, $subGroups[1]);
        $this->assertSame('نقل المسؤولية', $subGroups[1]->getLabel());
        $this->assertFalse($subGroups[1]->hasDropdown());
        $cat2Actions = array_map(fn ($action) => $action->getName(), $subGroups[1]->getActions());
        $this->assertSame(['requestReassignment', 'directReassignment', 'approveReassignment', 'rejectReassignment', 'cancelReassignment'], $cat2Actions);

        // Category 3: الترشيحات والمعاينات
        $this->assertInstanceOf(ActionGroup::class, $subGroups[2]);
        $this->assertSame('الترشيحات والمعاينات', $subGroups[2]->getLabel());
        $this->assertFalse($subGroups[2]->hasDropdown());
        $cat3Actions = array_map(fn ($action) => $action->getName(), $subGroups[2]->getActions());
        $this->assertSame([
            'createRecommendation',
            'changeRecommendationStatus',
            'scheduleViewing',
            'recordViewingResult',
            'recordFieldInspection',
            'viewPropertyHistory',
        ], $cat3Actions);

        // Category 4: الصفقة والإغلاق
        $this->assertInstanceOf(ActionGroup::class, $subGroups[3]);
        $this->assertSame('الصفقة والإغلاق', $subGroups[3]->getLabel());
        $this->assertFalse($subGroups[3]->hasDropdown());
        $cat4Actions = array_map(fn ($action) => $action->getName(), $subGroups[3]->getActions());
        $this->assertSame(['createDeal', 'viewDeal', 'pause', 'resume', 'close'], $cat4Actions);

        // Verify total flat actions count in the group is exactly 21
        $flatActions = $group->getFlatActions();
        $this->assertCount(21, $flatActions, 'Expected exactly 21 flat actions in the dropdown group.');

        // The admin theme attaches a heading to each list using this attribute.
        // It must be present on every action so headings follow hidden groups.
        $categories = array_map(
            fn ($action): ?string => $action->getExtraAttributes()['data-action-category'] ?? null,
            $flatActions,
        );

        $this->assertNotContains(null, $categories, 'Every request action must declare its category heading.');
        $this->assertSame(
            ['customer', 'reassignment', 'recommendations', 'deal'],
            array_values(array_unique($categories)),
            'Request action categories must remain grouped in dropdown order.',
        );
    }

    public function test_rental_and_resale_tables_register_all_actions(): void
    {
        $expectedActionNames = [
            'view',
            'recordContact',
            'edit',
            'linkCustomer',
            'setFollowUp',
            'contactHistory',
            'changeStage',
            'requestReassignment',
            'directReassignment',
            'approveReassignment',
            'rejectReassignment',
            'cancelReassignment',
            'createRecommendation',
            'changeRecommendationStatus',
            'scheduleViewing',
            'recordViewingResult',
            'recordFieldInspection',
            'viewPropertyHistory',
            'createDeal',
            'viewDeal',
            'pause',
            'resume',
            'close',
        ];

        // Verify on rental requests
        $rentalTable = RentalRequestResource::table(new Table(new \Filament\Resources\Pages\ListRecords));
        $rentalFlat = $rentalTable->getFlatActions();
        foreach ($expectedActionNames as $actionName) {
            $this->assertArrayHasKey($actionName, $rentalFlat, "Action [{$actionName}] missing in Rental table.");
        }

        // Verify on resale requests
        $resaleTable = ResaleRequestResource::table(new Table(new \Filament\Resources\Pages\ListRecords));
        $resaleFlat = $resaleTable->getFlatActions();
        foreach ($expectedActionNames as $actionName) {
            $this->assertArrayHasKey($actionName, $resaleFlat, "Action [{$actionName}] missing in Resale table.");
        }
    }
}
