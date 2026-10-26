<?php

namespace Tests\Unit;

use App\Exports\Concerns\FormatsSpreadsheetData;
use PHPUnit\Framework\TestCase;

class SpreadsheetFormulaSafetyTest extends TestCase
{
    public function test_untrusted_text_that_could_be_a_spreadsheet_formula_is_escaped(): void
    {
        $formatter = new class
        {
            use FormatsSpreadsheetData {
                safeText as public;
            }
        };

        $this->assertSame("'=1+1", $formatter::safeText('=1+1'));
        $this->assertSame("'@SUM(A1:A2)", $formatter::safeText('@SUM(A1:A2)'));
        $this->assertSame('عميل طبيعي', $formatter::safeText('عميل طبيعي'));
        $this->assertNull($formatter::safeText(null));
    }
}
