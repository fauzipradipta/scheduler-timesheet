<?php

namespace App\Support\Timesheets;

use App\Models\Attendance;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Reads the attendance columns back out of a filled copy of the timesheet
 * template, which is the shape the download hands out.
 *
 * @phpstan-import-type Entry from Attendance
 */
class TemplateImporter
{
    /**
     * @return list<Entry>
     *
     * @throws ValidationException
     */
    public function import(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'The timesheet could not be opened.']);
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $strings = $this->sharedStrings($zip);
        $zip->close();

        if ($sheet === false) {
            throw ValidationException::withMessages(['file' => 'The timesheet has no first worksheet.']);
        }

        $document = new DOMDocument;
        $document->loadXML($sheet);
        $xpath = $this->xpath($document);

        $entries = [];

        foreach (range(TimesheetLayout::FIRST_DATA_ROW, TimesheetLayout::LAST_DATA_ROW) as $row) {
            $serial = $this->numberAt($xpath, TimesheetLayout::DATE_COLUMN.$row);
            $start = $this->numberAt($xpath, TimesheetLayout::START_COLUMN.$row);
            $status = $this->statusAt($xpath, $row, $strings);

            if ($serial === null || ($start === null && $status === null)) {
                continue;
            }

            $date = Carbon::parse($this->dateFromSerial($serial));
            $clockedInAt = $date->copy()->addSeconds($start === null ? 0 : $this->secondsOfDay($start));

            $end = $start === null ? null : $this->numberAt($xpath, TimesheetLayout::END_COLUMN.$row);
            $clockedOutAt = $end === null
                ? null
                : $date->copy()->addSeconds($this->secondsOfDay($end));

            if ($clockedOutAt !== null && $clockedOutAt->lessThan($clockedInAt)) {
                $clockedOutAt = $clockedOutAt->addDay();
            }

            $entries[] = [
                'id' => (string) Str::uuid(),
                'clockedInAt' => $clockedInAt->toIso8601String(),
                'clockedOutAt' => $clockedOutAt?->toIso8601String(),
                'status' => $status ?? TimesheetLayout::PRESENT,
                'description' => $this->textAt($xpath, TimesheetLayout::REMARK_COLUMN.$row, $strings),
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = new DOMDocument;
        $document->loadXML($xml);

        $strings = [];

        foreach ($document->getElementsByTagNameNS(TimesheetLayout::SPREADSHEET_NAMESPACE, 'si') as $item) {
            $strings[] = $item->textContent;
        }

        return $strings;
    }

    private function numberAt(DOMXPath $xpath, string $reference): ?float
    {
        $cell = $this->find($xpath, '//x:c[@r="'.$reference.'"]');

        if ($cell === null || $cell->getAttribute('t') === 's') {
            return null;
        }

        $value = $cell->getElementsByTagNameNS(TimesheetLayout::SPREADSHEET_NAMESPACE, 'v')->item(0);

        if ($value === null || ! is_numeric($value->textContent)) {
            return null;
        }

        return (float) $value->textContent;
    }

    /**
     * Whichever status column carries a mark is the status claimed for that day.
     *
     * @param  array<int, string>  $strings
     */
    private function statusAt(DOMXPath $xpath, int $row, array $strings): ?string
    {
        foreach (TimesheetLayout::STATUSES as $status => $column) {
            if ($this->textAt($xpath, $column.$row, $strings) !== '') {
                return $status;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $strings
     */
    private function textAt(DOMXPath $xpath, string $reference, array $strings): string
    {
        $cell = $this->find($xpath, '//x:c[@r="'.$reference.'"]');

        if ($cell === null) {
            return '';
        }

        if ($cell->getAttribute('t') === 's') {
            $index = $cell->getElementsByTagNameNS(TimesheetLayout::SPREADSHEET_NAMESPACE, 'v')->item(0);

            return $index === null ? '' : ($strings[(int) $index->textContent] ?? '');
        }

        return trim($cell->textContent);
    }

    private function dateFromSerial(float $serial): string
    {
        return Carbon::createFromTimestampUTC(
            (int) (floor($serial) - TimesheetLayout::EXCEL_EPOCH_OFFSET) * TimesheetLayout::SECONDS_PER_DAY,
        )->format('Y-m-d');
    }

    private function secondsOfDay(float $fraction): int
    {
        return (int) round($fraction * TimesheetLayout::SECONDS_PER_DAY);
    }

    private function find(DOMXPath $xpath, string $expression): ?DOMElement
    {
        $nodes = $xpath->query($expression);

        if ($nodes === false) {
            return null;
        }

        $node = $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function xpath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', TimesheetLayout::SPREADSHEET_NAMESPACE);

        return $xpath;
    }
}
