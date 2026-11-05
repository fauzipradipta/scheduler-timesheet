<?php

namespace App\Rules;

use App\Support\Timesheets\TimesheetLayout;
use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;
use ZipArchive;

/**
 * Accepts a workbook only when the export can fill it: a first worksheet that
 * carries every row the attendance log and the profile write to, and a style
 * table the weekend shading can extend. Everything else about the workbook -
 * its logo, fonts, totals and any columns it adds - is the template author's
 * own business.
 */
class FollowsTimesheetLayout implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be an Excel workbook.');

            return;
        }

        $zip = new ZipArchive;

        if ($zip->open((string) $value->getRealPath()) !== true) {
            $fail('The :attribute could not be opened as an Excel workbook.');

            return;
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        $zip->close();

        if ($sheet === false || $styles === false) {
            $fail('The :attribute needs a first worksheet and a style table.');

            return;
        }

        $stylesDocument = new DOMDocument;

        if (! @$stylesDocument->loadXML($styles)
            || $stylesDocument->getElementsByTagName('cellXfs')->length === 0
            || $stylesDocument->getElementsByTagName('fills')->length === 0) {
            $fail('The :attribute has no cell formats to build on.');

            return;
        }

        $sheetDocument = new DOMDocument;

        if (! @$sheetDocument->loadXML($sheet)) {
            $fail('The :attribute has a first worksheet that could not be read.');

            return;
        }

        $xpath = new DOMXPath($sheetDocument);
        $xpath->registerNamespace('x', TimesheetLayout::SPREADSHEET_NAMESPACE);

        $missing = array_values(array_filter(
            TimesheetLayout::requiredRows(),
            function (int $row) use ($xpath): bool {
                $rows = $xpath->query('//x:sheetData/x:row[@r="'.$row.'"]');

                return $rows === false || $rows->length === 0;
            },
        ));

        if ($missing !== []) {
            $profileCells = array_values(TimesheetLayout::PROFILE_CELLS);

            $fail(sprintf(
                'The :attribute does not follow the timesheet layout: %s %s missing. Keep the employee details in %s to %s, the period in %s, one day per row from row %d to %d, and the signature name and dates in rows %d and %d.',
                count($missing) === 1 ? 'row '.$missing[0] : 'rows '.implode(', ', $missing),
                count($missing) === 1 ? 'is' : 'are',
                reset($profileCells),
                end($profileCells),
                TimesheetLayout::PERIOD_CELL,
                TimesheetLayout::FIRST_DATA_ROW,
                TimesheetLayout::LAST_DATA_ROW,
                TimesheetLayout::rowOf(TimesheetLayout::SIGNATURE_NAME_CELL),
                TimesheetLayout::rowOf(TimesheetLayout::SIGNATURE_DATE_CELLS[0]),
            ));
        }
    }
}
