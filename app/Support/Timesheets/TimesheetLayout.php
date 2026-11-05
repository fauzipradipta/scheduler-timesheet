<?php

namespace App\Support\Timesheets;

/**
 * Where the timesheet template keeps the things the attendance log speaks to.
 *
 * The reader and the writer have to agree on every one of these, so they are
 * named once here rather than copied into both.
 */
final class TimesheetLayout
{
    /**
     * The template's own legend, mapped to the column each status is counted in.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'P' => 'E',
        'S' => 'F',
        'BT' => 'G',
        'PM' => 'H',
        'V' => 'I',
        'X' => 'J',
    ];

    public const PRESENT = 'P';

    /**
     * Rows 11 to 41 of the template hold one day each, and the columns below are
     * the only ones the attendance log can speak to. Everything else in the
     * workbook - the logo, the header block, the totals and the signatures -
     * is left exactly as the template author wrote it.
     */
    public const FIRST_DATA_ROW = 11;

    public const LAST_DATA_ROW = 41;

    public const DATE_COLUMN = 'A';

    public const START_COLUMN = 'B';

    public const END_COLUMN = 'C';

    public const TOTAL_COLUMN = 'D';

    public const REMARK_COLUMN = 'K';

    /**
     * The template's own period label, which it formats from a date serial.
     */
    public const PERIOD_CELL = 'B8';

    /**
     * The signature block's date lines, which the template writes as plain text.
     *
     * @var list<string>
     */
    public const SIGNATURE_DATE_CELLS = ['B55', 'F55', 'J55'];

    /**
     * The employee block at the top of the sheet, keyed by the profile field
     * each cell is written from. The template prints each value after a colon.
     *
     * @var array<string, string>
     */
    public const PROFILE_CELLS = [
        'project_name' => 'B4',
        'division' => 'B5',
        'name' => 'B6',
        'ntt_id' => 'B7',
    ];

    /**
     * The employee's own signature line, which carries their name in brackets.
     */
    public const SIGNATURE_NAME_CELL = 'B54';

    /**
     * The two rows the column headings sit on. A heading the template author
     * added to the right of the remark column names a column the user fills.
     *
     * @var list<int>
     */
    public const HEADER_ROWS = [9, 10];

    /**
     * Columns the export never writes to beyond this point are the template's
     * own additions; the ones before it are the fixed layout.
     */
    public const LAST_FIXED_COLUMN = self::REMARK_COLUMN;

    /**
     * The column index of a letter such as "K", counting "A" as 1.
     */
    public static function columnIndex(string $column): int
    {
        $index = 0;

        foreach (str_split($column) as $letter) {
            $index = ($index * 26) + (ord($letter) - ord('A') + 1);
        }

        return $index;
    }

    /**
     * The row number of a cell reference such as "B8".
     */
    public static function rowOf(string $reference): int
    {
        return (int) preg_replace('/^[A-Z]+/', '', $reference);
    }

    /**
     * Every worksheet row the export writes to, which a template has to carry.
     *
     * @return list<int>
     */
    public static function requiredRows(): array
    {
        $rows = array_unique([
            ...array_map(self::rowOf(...), array_values(self::PROFILE_CELLS)),
            self::rowOf(self::PERIOD_CELL),
            ...range(self::FIRST_DATA_ROW, self::LAST_DATA_ROW),
            self::rowOf(self::SIGNATURE_NAME_CELL),
            ...array_map(self::rowOf(...), self::SIGNATURE_DATE_CELLS),
        ]);

        sort($rows);

        return $rows;
    }

    /**
     * Excel counts days from 1899-12-30, which is 25569 days before the Unix epoch.
     */
    public const EXCEL_EPOCH_OFFSET = 25569;

    public const SECONDS_PER_DAY = 86400;

    public const SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
}
