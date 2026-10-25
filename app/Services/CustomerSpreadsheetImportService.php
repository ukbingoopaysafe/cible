<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class CustomerSpreadsheetImportService
{
    private const MAX_FILE_KILOBYTES = 10240;

    private const MAX_ROWS = 5000;

    private const HEADERS = ['اسم العميل', 'رقم الهاتف', 'رمز الدولة', 'الجنسية'];

    public function __construct(private readonly CustomerService $customers) {}

    /**
     * @return array{successful: int, failed: array<int, array{row: int, data: array<int, string>, error: string}>}
     */
    public function import(User $actor, Department $department, string $path): array
    {
        if (! $actor->canWorkInDepartment($department)) {
            abort(403);
        }

        if (! is_file($path) || filesize($path) > self::MAX_FILE_KILOBYTES * 1024) {
            throw ValidationException::withMessages(['file' => 'حجم الملف يتجاوز الحد المسموح (10 ميجابايت).']);
        }

        $reader = new Reader;
        try {
            $reader->open($path);
            $sheets = $reader->getSheetIterator();
            if (! $sheets->valid()) {
                throw ValidationException::withMessages(['file' => 'ملف Excel لا يحتوي على ورقة بيانات.']);
            }

            $rows = $sheets->current()->getRowIterator();
            $rows->rewind();
            if (! $rows->valid()) {
                throw ValidationException::withMessages(['file' => 'الملف فارغ.']);
            }

            $header = $this->rowValues($rows->current());
            if (array_slice($header, 0, count(self::HEADERS)) !== self::HEADERS) {
                throw ValidationException::withMessages(['file' => 'ترتيب أعمدة الملف غير صحيح. استخدم القالب: '.implode('، ', self::HEADERS)]);
            }

            $successful = 0;
            $failed = [];
            $rowNumber = 1;
            $importRows = [];
            $rows->next();

            while ($rows->valid()) {
                $rowNumber++;
                $values = array_pad(array_slice($this->rowValues($rows->current()), 0, count(self::HEADERS)), count(self::HEADERS), '');
                $rows->next();

                if (count($importRows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'الملف يتجاوز الحد الأقصى البالغ 5,000 صف بيانات.']);
                }

                $importRows[] = ['row' => $rowNumber, 'data' => $values];
            }

            foreach ($importRows as $importRow) {
                $values = $importRow['data'];
                if (count(array_filter($values, static fn (string $value): bool => trim($value) !== '')) === 0) {
                    continue;
                }

                try {
                    $this->customers->create($actor, $department, [
                        'name' => $values[0],
                        'phone' => $values[1],
                        'phone_country' => $values[2] !== '' ? $values[2] : 'EG',
                        'nationality' => $values[3] !== '' ? $values[3] : null,
                    ]);
                    $successful++;
                } catch (Throwable $exception) {
                    $message = $exception instanceof ValidationException
                        ? collect($exception->errors())->flatten()->implode('، ')
                        : 'تعذر حفظ الصف بسبب خطأ في البيانات أو قاعدة البيانات.';

                    $failed[] = ['row' => $importRow['row'], 'data' => $values, 'error' => $message];
                }
            }

            return ['successful' => $successful, 'failed' => $failed];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'تعذر قراءة الملف. تأكد أنه ملف XLSX صالح وبالقالب المعتمد.']);
        } finally {
            $reader->close();
        }
    }

    /** @return array<int, string> */
    private function rowValues(\OpenSpout\Common\Entity\Row $row): array
    {
        return array_map(static function ($cell): string {
            $value = $cell->getValue();
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            return $value === null ? '' : (string) $value;
        }, $row->getCells());
    }
}
