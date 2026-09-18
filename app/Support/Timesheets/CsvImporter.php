<?php

namespace App\Support\Timesheets;

use App\Models\Attendance;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reads a timesheet written as comma separated rows, which is the shape a
 * spreadsheet hands out when it is saved as CSV.
 *
 * @phpstan-import-type Entry from Attendance
 */
class CsvImporter
{
    /**
     * The header the rows are expected to carry, which is also the order the
     * columns are read in.
     *
     * @var list<string>
     */
    private const COLUMNS = ['Date', 'Clock In', 'Clock Out', 'Duration', 'Activity / Remark'];

    private const MAX_ROWS = 500;

    /**
     * Read every data row of a CSV timesheet, ignoring the header and total rows.
     *
     * @return list<Entry>
     *
     * @throws ValidationException
     */
    public function import(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The timesheet could not be read.']);
        }

        $entries = [];
        $number = 0;

        try {
            while (($row = fgetcsv($handle, escape: '')) !== false) {
                $number++;
                $row[0] = ltrim((string) ($row[0] ?? ''), "\xEF\xBB\xBF");

                if ($this->isSkippableRow($row, $number)) {
                    continue;
                }

                if (count($entries) === self::MAX_ROWS) {
                    throw ValidationException::withMessages([
                        'file' => 'The timesheet may not have more than '.self::MAX_ROWS.' entries.',
                    ]);
                }

                $entries[] = $this->entryFromRow($row, $number);
            }
        } finally {
            fclose($handle);
        }

        return $entries;
    }

    /**
     * Skip the header row, the total row, and blank lines.
     *
     * @param  list<string|null>  $row
     */
    private function isSkippableRow(array $row, int $number): bool
    {
        $date = trim((string) $row[0]);

        return ($number === 1 && $date === self::COLUMNS[0])
            || $date === 'Total'
            || implode('', array_map(fn (?string $value): string => trim((string) $value), $row)) === '';
    }

    /**
     * @param  list<string|null>  $row
     * @return Entry
     *
     * @throws ValidationException
     */
    private function entryFromRow(array $row, int $number): array
    {
        $clockedInAt = $this->parseMoment(trim((string) ($row[0] ?? '')), trim((string) ($row[1] ?? '')), $number);
        $clockedOut = trim((string) ($row[2] ?? ''));
        $clockedOutAt = $clockedOut !== ''
            ? $this->parseMoment(trim((string) ($row[0] ?? '')), $clockedOut, $number)
            : null;

        if ($clockedOutAt !== null && $clockedOutAt->lessThan($clockedInAt)) {
            $clockedOutAt = $clockedOutAt->addDay();
        }

        $description = trim((string) ($row[4] ?? ''));

        if (mb_strlen($description) > 1000) {
            throw ValidationException::withMessages([
                'file' => "Row {$number} has a description longer than 1000 characters.",
            ]);
        }

        return [
            'id' => (string) Str::uuid(),
            'clockedInAt' => $clockedInAt->toIso8601String(),
            'clockedOutAt' => $clockedOutAt?->toIso8601String(),
            'status' => TimesheetLayout::PRESENT,
            'description' => $description,
        ];
    }

    /**
     * @throws ValidationException
     */
    private function parseMoment(string $date, string $time, int $number): Carbon
    {
        try {
            $moment = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}")->seconds(0);
        } catch (InvalidFormatException) {
            $moment = null;
        }

        if ($moment === null || $moment->format('Y-m-d H:i') !== "{$date} {$time}") {
            throw ValidationException::withMessages([
                'file' => "Row {$number} needs a date as YYYY-MM-DD and a time as HH:MM.",
            ]);
        }

        return $moment;
    }
}
