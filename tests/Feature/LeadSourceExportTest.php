<?php

namespace Tests\Feature;

use App\Exports\RentalRequestExporter;
use App\Exports\ResaleRequestExporter;
use App\Models\RentalRequest;
use App\Models\ResaleRequest;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class LeadSourceExportTest extends TestCase
{
    public function test_rental_and_resale_exports_include_referrer_name_unknown_and_historical_blank(): void
    {
        foreach ([
            [RentalRequestExporter::class, RentalRequest::class],
            [ResaleRequestExporter::class, ResaleRequest::class],
        ] as [$exporterClass, $requestClass]) {
            $column = collect($exporterClass::getColumns())->first(
                fn (ExportColumn $column): bool => $column->getName() === 'lead_source_referrer_export',
            );

            $this->assertNotNull($column);
            $this->assertSame('الشخص المُحيل', $column->getLabel());

            $this->assertSame('رئيس مجلس الإدارة', $this->exportedReferrer($column, $requestClass, [
                'lead_source_referrer_name' => 'رئيس مجلس الإدارة',
                'lead_source_referrer_unknown' => null,
            ]));
            $this->assertSame('غير معروف', $this->exportedReferrer($column, $requestClass, [
                'lead_source_referrer_name' => null,
                'lead_source_referrer_unknown' => true,
            ]));
            $this->assertSame('', $this->exportedReferrer($column, $requestClass, [
                'lead_source_referrer_name' => null,
                'lead_source_referrer_unknown' => null,
            ]));
        }
    }

    /** @param class-string<Model> $requestClass */
    private function exportedReferrer(ExportColumn $column, string $requestClass, array $attributes): mixed
    {
        $request = new $requestClass($attributes);
        $state = ($column->getGetStateUsingCallback())($request);

        return $column->formatState($state);
    }
}
