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
     * Excel counts days from 1899-12-30, which is 25569 days before the Unix epoch.
     */
    public const EXCEL_EPOCH_OFFSET = 25569;

    public const SECONDS_PER_DAY = 86400;

    public const SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
}
