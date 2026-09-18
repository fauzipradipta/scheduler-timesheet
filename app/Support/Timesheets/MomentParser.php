<?php

namespace App\Support\Timesheets;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

/**
 * Reads the dates and times people actually write in timesheets, rather than
 * the one spelling this application happens to export.
 */
class MomentParser
{
    /**
     * Written months, which a regular expression cannot take apart.
     *
     * @var list<string>
     */
    private const NAMED_DATE_FORMATS = ['d M Y', 'd F Y', 'M d Y', 'F d Y', 'd-M-Y', 'd-F-Y'];

    /**
     * A serial this side of 1970 is far more likely to be a duration or a row
     * number that wandered into the date column than a day someone worked.
     */
    private const FIRST_PLAUSIBLE_SERIAL = 25569;

    private const LAST_PLAUSIBLE_SERIAL = 80000;

    /**
     * The day a cell names, whatever notation it was written in.
     */
    public function date(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        /** A spreadsheet that kept its date as a number hands over the serial. */
        if (is_numeric($value)) {
            return $this->fromSerial((float) $value);
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $iso) === 1) {
            return $this->build((int) $iso[1], (int) $iso[2], (int) $iso[3]);
        }

        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2}|\d{4})$/', $value, $parts) === 1) {
            return $this->fromDayFirst((int) $parts[1], (int) $parts[2], (int) $parts[3]);
        }

        return $this->fromNamedMonth($value);
    }

    /**
     * How far into the day a cell's time sits, in seconds, or null when it
     * holds no time at all.
     */
    public function secondsIntoDay(string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        /** A spreadsheet keeps a time as the fraction of the day it stands at. */
        if (is_numeric($value)) {
            $fraction = (float) $value;

            return $fraction >= 0 && $fraction < 1
                ? (int) round($fraction * TimesheetLayout::SECONDS_PER_DAY)
                : null;
        }

        if (preg_match('/^(\d{1,2})[:.](\d{2})(?:[:.](\d{2}))?\s*([ap])\.?m\.?$/i', $value, $parts) === 1) {
            return $this->clockToSeconds(
                $this->hourOnATwelveHourClock((int) $parts[1], strtolower($parts[4])),
                (int) $parts[2],
                (int) ($parts[3] ?? 0),
            );
        }

        if (preg_match('/^(\d{1,2})[:.](\d{2})(?:[:.](\d{2}))?$/', $value, $parts) === 1) {
            return $this->clockToSeconds((int) $parts[1], (int) $parts[2], (int) ($parts[3] ?? 0));
        }

        /** A bare hour, as in "9 AM", is still a time. */
        if (preg_match('/^(\d{1,2})\s*([ap])\.?m\.?$/i', $value, $parts) === 1) {
            return $this->clockToSeconds(
                $this->hourOnATwelveHourClock((int) $parts[1], strtolower($parts[2])),
                0,
                0,
            );
        }

        return null;
    }

    /**
     * Some sheets keep the whole moment in one cell, so the date column has to
     * be able to give its time up as well.
     */
    public function secondsIntoDayOfDateTime(string $value): ?int
    {
        $parts = preg_split('/[ T]+/', trim($value));

        if ($parts === false || count($parts) < 2) {
            return null;
        }

        return $this->secondsIntoDay(implode(' ', array_slice($parts, 1)));
    }

    private function fromSerial(float $serial): ?Carbon
    {
        if ($serial < self::FIRST_PLAUSIBLE_SERIAL || $serial > self::LAST_PLAUSIBLE_SERIAL) {
            return null;
        }

        return Carbon::createFromTimestampUTC(
            (int) (floor($serial) - TimesheetLayout::EXCEL_EPOCH_OFFSET) * TimesheetLayout::SECONDS_PER_DAY,
        );
    }

    /**
     * Day first, as most of the world writes it, unless the first number is too
     * large to be a day and the second one is not.
     */
    private function fromDayFirst(int $day, int $month, int $year): ?Carbon
    {
        if ($year < 100) {
            $year += 2000;
        }

        if ($day > 12 && $month <= 12) {
            return $this->build($year, $month, $day);
        }

        if ($month > 12 && $day <= 12) {
            return $this->build($year, $day, $month);
        }

        return $this->build($year, $month, $day);
    }

    private function fromNamedMonth(string $value): ?Carbon
    {
        $value = (string) preg_replace('/\s+/', ' ', str_replace(',', '', $value));

        foreach (self::NAMED_DATE_FORMATS as $format) {
            try {
                $moment = Carbon::createFromFormat($format, $value);
            } catch (InvalidFormatException) {
                continue;
            }

            if ($moment !== false) {
                return $moment->startOfDay();
            }
        }

        return null;
    }

    private function build(int $year, int $month, int $day): ?Carbon
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    private function hourOnATwelveHourClock(int $hour, string $half): int
    {
        if ($hour === 12) {
            $hour = 0;
        }

        return $half === 'p' ? $hour + 12 : $hour;
    }

    private function clockToSeconds(int $hour, int $minute, int $second): ?int
    {
        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return ($hour * 3600) + ($minute * 60) + $second;
    }
}
