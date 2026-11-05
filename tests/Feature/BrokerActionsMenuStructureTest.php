<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Filament\Resources\RentalBrokers\RentalBrokerResource;
use App\Filament\Resources\ResaleBrokers\ResaleBrokerResource;
use Filament\Actions\ActionGroup;
use Tests\TestCase;

class BrokerActionsMenuStructureTest extends TestCase
{
    public function test_broker_actions_menu_has_two_logical_categories(): void
    {
        $group = RentalBrokerResource::getBrokerActionsGroup();

        $this->assertInstanceOf(ActionGroup::class, $group);
        $subGroups = $group->getActions();

        $this->assertCount(2, $subGroups, 'Expected exactly 2 categories in the broker actions group.');

        $this->assertSame('بيانات الوسيط', $subGroups[0]->getLabel());
        $this->assertFalse($subGroups[0]->hasDropdown(), 'Subgroup should not create nested dropdown.');
        $this->assertSame(
            ['viewContactPhoneNumbers', 'editBroker'],
            array_map(fn ($action) => $action->getName(), $subGroups[0]->getActions()),
        );

        $this->assertSame('الحذف', $subGroups[1]->getLabel());
        $this->assertFalse($subGroups[1]->hasDropdown());
        $this->assertSame(
            ['deleteBroker'],
            array_map(fn ($action) => $action->getName(), $subGroups[1]->getActions()),
        );

        $flatActions = $group->getFlatActions();
        $this->assertCount(3, $flatActions, 'Expected phone viewing, editing, and deletion actions in the dropdown group.');

        $categories = array_map(
            fn ($action): ?string => $action->getExtraAttributes()['data-action-category'] ?? null,
            $flatActions,
        );

        $this->assertNotContains(null, $categories, 'Every broker action must declare its category heading.');
        $this->assertSame(
            ['broker-profile', 'broker-delete'],
            array_values(array_unique($categories)),
            'Broker action categories must remain grouped in dropdown order.',
        );
    }

    public function test_rental_and_resale_brokers_share_the_same_action_structure(): void
    {
        $this->assertSame(Department::Rental, RentalBrokerResource::department());
        $this->assertSame(Department::Resale, ResaleBrokerResource::department());

        $rentalNames = array_keys(RentalBrokerResource::getBrokerActionsGroup()->getFlatActions());
        $resaleNames = array_keys(ResaleBrokerResource::getBrokerActionsGroup()->getFlatActions());

        $this->assertSame($rentalNames, $resaleNames);
    }
}
