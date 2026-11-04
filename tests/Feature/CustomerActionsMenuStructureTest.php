<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Filament\Resources\RentalCustomers\RentalCustomerResource;
use App\Filament\Resources\ResaleCustomers\ResaleCustomerResource;
use Filament\Actions\ActionGroup;
use Tests\TestCase;

class CustomerActionsMenuStructureTest extends TestCase
{
    public function test_customer_actions_menu_has_three_logical_categories(): void
    {
        $group = RentalCustomerResource::getCustomerActionsGroup();

        $this->assertInstanceOf(ActionGroup::class, $group);
        $subGroups = $group->getActions();

        $this->assertCount(3, $subGroups, 'Expected exactly 3 categories in the customer actions group.');

        $this->assertSame('بيانات العميل', $subGroups[0]->getLabel());
        $this->assertFalse($subGroups[0]->hasDropdown(), 'Subgroup should not create nested dropdown.');
        $this->assertSame(
            ['viewContactPhoneNumbers', 'editCustomer', 'createCustomerRequest'],
            array_map(fn ($action) => $action->getName(), $subGroups[0]->getActions()),
        );

        $this->assertSame('متابعة العميل', $subGroups[1]->getLabel());
        $this->assertFalse($subGroups[1]->hasDropdown());
        $this->assertSame(
            ['startCustomerFollowUp', 'recordCustomerFollowUpActivity', 'customerFollowUpHistory'],
            array_map(fn ($action) => $action->getName(), $subGroups[1]->getActions()),
        );

        $this->assertSame('الأرشفة', $subGroups[2]->getLabel());
        $this->assertFalse($subGroups[2]->hasDropdown());
        $this->assertSame(
            ['archiveCustomer', 'restoreCustomer'],
            array_map(fn ($action) => $action->getName(), $subGroups[2]->getActions()),
        );

        $flatActions = $group->getFlatActions();
        $this->assertCount(8, $flatActions, 'Expected exactly 8 flat actions in the dropdown group.');

        $categories = array_map(
            fn ($action): ?string => $action->getExtraAttributes()['data-action-category'] ?? null,
            $flatActions,
        );

        $this->assertNotContains(null, $categories, 'Every customer action must declare its category heading.');
        $this->assertSame(
            ['profile', 'customer-follow-up', 'archive'],
            array_values(array_unique($categories)),
            'Customer action categories must remain grouped in dropdown order.',
        );
    }

    public function test_rental_and_resale_customers_share_the_same_action_structure(): void
    {
        $this->assertSame(Department::Rental, RentalCustomerResource::department());
        $this->assertSame(Department::Resale, ResaleCustomerResource::department());

        $rentalNames = array_keys(RentalCustomerResource::getCustomerActionsGroup()->getFlatActions());
        $resaleNames = array_keys(ResaleCustomerResource::getCustomerActionsGroup()->getFlatActions());

        $this->assertSame($rentalNames, $resaleNames);
    }
}
