<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\User;
use App\Services\CustomerSpreadsheetImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class CustomerSpreadsheetImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_import_creates_valid_rows_and_reports_invalid_row_numbers(): void
    {
        $employee = User::factory()->create(['is_company_manager' => false]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'cible-import-');
        $path = $temporaryPath.'.xlsx';
        @unlink($temporaryPath);
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['اسم العميل', 'رقم الهاتف', 'رمز الدولة', 'الجنسية']));
        $writer->addRow(Row::fromValues(['عميل صالح', '01012345678', 'EG', 'مصري']));
        $writer->addRow(Row::fromValues(['عميل برقم غير صالح', '123', 'EG', '']));
        $writer->close();

        try {
            $reader = new Reader;
            $reader->open($path);
            $sheets = $reader->getSheetIterator();
            $this->assertTrue($sheets->valid());
            $rows = $sheets->current()->getRowIterator();
            $rows->rewind();
            $this->assertTrue($rows->valid());
            $this->assertSame('اسم العميل', $rows->current()->getCells()[0]->getValue());
            $reader->close();

            $result = app(CustomerSpreadsheetImportService::class)->import($employee, Department::Rental, $path);

            $this->assertSame(1, $result['successful']);
            $this->assertSame(1, count($result['failed']));
            $this->assertSame(3, $result['failed'][0]['row']);
            $this->assertDatabaseCount('rental_customers', 1);
            $this->assertDatabaseCount('resale_customers', 0);
        } finally {
            @unlink($path);
        }
    }
}
