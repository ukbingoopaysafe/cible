<?php

namespace Tests\Feature;

use App\Exports\RentalCustomerExporter;
use App\Exports\RentalRequestExporter;
use App\Exports\ResaleCustomerExporter;
use App\Exports\ResaleRequestExporter;
use App\Models\RentalCustomer;
use App\Support\DateTimeLocale;
use DateTimeImmutable;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use Tests\TestCase;

class DateTimeExportLocalizationTest extends TestCase
{
    public function test_timestamp_columns_are_encoded_as_cairo_canonical_csv_values(): void
    {
        $exporter = new RentalCustomerExporter(
            new Export(),
            ['created_at' => 'تاريخ التسجيل'],
            ['cible_datetime_locale' => 'en-US'],
        );
        $record = new RentalCustomer();
        $record->setRawAttributes([
            'created_at' => '2026-11-04 16:00:00',
        ]);

        $values = $exporter($record);

        $this->assertCount(1, $values);
        $this->assertSame('2026-11-04 16:00:00', $values[0]);
    }

    public function test_xlsx_rows_rehydrate_only_allowlisted_columns_as_typed_cells(): void
    {
        $exporter = new RentalCustomerExporter(
            new Export(),
            ['name' => 'اسم العميل', 'created_at' => 'تاريخ التسجيل'],
            ['cible_datetime_locale' => 'en-GB'],
        );

        $row = $exporter->makeXlsxRow(['اسم العميل', '2026-11-04 18:00:00']);
        $cells = $row->getCells();

        $this->assertInstanceOf(StringCell::class, $cells[0]);
        $this->assertInstanceOf(DateTimeCell::class, $cells[1]);
        $this->assertSame('[$-0809]d mmm yyyy hh:mm AM/PM', $cells[1]->getStyle()->getFormat());
        $this->assertSame('2026-11-04 18:00:00', $cells[1]->getValue()->format('Y-m-d H:i:s'));
    }

    public function test_null_timestamp_stays_empty_and_header_values_stay_text(): void
    {
        $exporter = new RentalCustomerExporter(
            new Export(),
            ['created_at' => 'تاريخ التسجيل'],
            ['cible_datetime_locale' => 'ar-EG'],
        );

        $row = $exporter->makeXlsxRow([null]);
        $header = $exporter->makeXlsxHeaderRow(['تاريخ التسجيل']);

        $this->assertInstanceOf(EmptyCell::class, $row->getCells()[0]);
        $this->assertInstanceOf(StringCell::class, $header->getCells()[0]);
    }

    public function test_request_exporters_allowlist_all_current_timestamp_columns(): void
    {
        foreach ([RentalRequestExporter::class, ResaleRequestExporter::class] as $exporterClass) {
            foreach (['next_follow_up_at', 'closed_at', 'created_at'] as $column) {
                $exporter = new $exporterClass(new Export(), [$column => 'تاريخ'], ['cible_datetime_locale' => 'ar-EG']);
                $row = $exporter->makeXlsxRow(['2026-11-04 18:00:00']);
                $this->assertInstanceOf(DateTimeCell::class, $row->getCells()[0], "{$exporterClass} must type {$column}.");
                $this->assertSame('[$-0C01]d mmmm yyyy hh:mm AM/PM', $row->getCells()[0]->getStyle()->getFormat());
            }
        }
    }

    public function test_timestamps_preserve_cairo_wall_time_for_datetime_objects(): void
    {
        $exporter = new RentalCustomerExporter(
            new Export(),
            ['created_at' => 'تاريخ التسجيل'],
            ['cible_datetime_locale' => 'en-US'],
        );
        $row = $exporter->makeXlsxRow(['2026-11-04 18:00:00']);
        $timestamp = $row->getCells()[0]->getValue();

        $this->assertInstanceOf(DateTimeImmutable::class, $timestamp);
        $this->assertSame('Africa/Cairo', $timestamp->getTimezone()->getName());
        $this->assertSame('2026-11-04 18:00:00', $timestamp->format('Y-m-d H:i:s'));
    }

    public function test_export_action_snapshots_locale_for_later_queued_xlsx_rows(): void
    {
        app()->instance('request', Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'en-GB, ar-EG;q=0.9',
        ]));
        app()->forgetScopedInstances();

        $options = ExportAction::make()->options(fn (): array => [
            'cible_datetime_locale' => app(DateTimeLocale::class)->getRegionalTag(),
        ])->getOptions();

        app()->instance('request', Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'ar-EG',
        ]));
        app()->forgetScopedInstances();

        $exporter = new RentalCustomerExporter(new Export(), ['created_at' => 'تاريخ التسجيل'], $options);
        $cell = $exporter->makeXlsxRow(['2026-11-04 18:00:00'])->getCells()[0];

        $this->assertSame(['cible_datetime_locale' => 'en-GB'], $options);
        $this->assertSame('[$-0809]d mmm yyyy hh:mm AM/PM', $cell->getStyle()->getFormat());
    }
}
