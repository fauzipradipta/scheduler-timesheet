<?php

namespace App\Http\Controllers;

use Carbon\Exceptions\InvalidFormatException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * @phpstan-type Entry array{id: string, clockedInAt: string, clockedOutAt: string|null, status: string, description: string}
 */
class AttendanceController extends Controller
{
    private const SESSION_KEY = 'attendance.entries';

    /**
     * The template's own legend, mapped to the column each status is counted in.
     *
     * @var array<string, string>
     */
    private const STATUSES = [
        'P' => 'E',
        'S' => 'F',
        'BT' => 'G',
        'PM' => 'H',
        'V' => 'I',
        'X' => 'J',
    ];

    private const PRESENT = 'P';

    /**
     * @var list<string>
     */
    private const COLUMNS = ['Date', 'Clock In', 'Clock Out', 'Duration', 'Activity / Remark'];

    private const MAX_ROWS = 500;

    /**
     * Rows 11 to 41 of the template hold one day each, and the columns below are
     * the only ones the attendance log can speak to. Everything else in the
     * workbook - the logo, the header block, the totals and the signatures -
     * is left exactly as the template author wrote it.
     */
    private const FIRST_DATA_ROW = 11;

    private const LAST_DATA_ROW = 41;

    private const DATE_COLUMN = 'A';

    private const START_COLUMN = 'B';

    private const END_COLUMN = 'C';

    private const TOTAL_COLUMN = 'D';

    private const REMARK_COLUMN = 'K';

    /**
     * Excel counts days from 1899-12-30, which is 25569 days before the Unix epoch.
     */
    private const EXCEL_EPOCH_OFFSET = 25569;

    private const SECONDS_PER_DAY = 86400;

    private const SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function index(Request $request): Response
    {
        $entries = $this->entries($request);

        return Inertia::render('attendance', [
            'entries' => $entries,
            'activeEntry' => $this->activeEntry($entries),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:clock-in,clock-out'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $description = trim((string) ($validated['description'] ?? ''));

        $entries = $validated['action'] === 'clock-in'
            ? $this->clockIn($this->entries($request), $description)
            : $this->clockOut($this->entries($request), $description);

        $request->session()->put(self::SESSION_KEY, $entries);

        return to_route('attendance.index');
    }

    /**
     * Record a day the user picked from the calendar rather than clocked live.
     *
     * @throws ValidationException
     */
    public function storeEntry(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'startedAt' => ['required_if:status,'.self::PRESENT, 'nullable', 'date_format:H:i'],
            'endedAt' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $entries = $this->entries($request);
        $present = $validated['status'] === self::PRESENT;

        /** Only a present day carries hours; the rest just mark the day. */
        $clockedInAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['date'].' '.($present ? $validated['startedAt'] : '00:00'),
        )->seconds(0);

        $clockedOutAt = $present && isset($validated['endedAt'])
            ? Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['endedAt'])->seconds(0)
            : null;

        /** A shift that ends before it starts ran past midnight. */
        if ($clockedOutAt !== null && $clockedOutAt->lessThanOrEqualTo($clockedInAt)) {
            $clockedOutAt = $clockedOutAt->addDay();
        }

        if ($present && $clockedOutAt === null && $this->activeEntry($entries) !== null) {
            throw ValidationException::withMessages([
                'endedAt' => 'Give this day an end time, because another entry is still running.',
            ]);
        }

        $entries[] = [
            'id' => (string) Str::uuid(),
            'clockedInAt' => $clockedInAt->toIso8601String(),
            'clockedOutAt' => $clockedOutAt?->toIso8601String(),
            'status' => $validated['status'],
            'description' => trim((string) ($validated['description'] ?? '')),
        ];

        $request->session()->put(self::SESSION_KEY, $this->newestFirst($entries));

        return to_route('attendance.index');
    }

    /**
     * Drop a single day from the log.
     */
    public function destroyEntry(Request $request, string $entry): RedirectResponse
    {
        $remaining = array_filter(
            $this->entries($request),
            fn (array $stored): bool => $stored['id'] !== $entry,
        );

        $request->session()->put(self::SESSION_KEY, array_values($remaining));

        return to_route('attendance.index');
    }

    /**
     * Hand back a copy of the timesheet template with the attendance columns filled in.
     */
    public function download(Request $request): BinaryFileResponse
    {
        $path = $this->fill($this->entries($request));

        return response()
            ->download($path, 'timesheet-'.now()->format('Y-m').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * Replace the stored entries with the rows of an uploaded timesheet.
     *
     * @throws ValidationException
     */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,csv,txt', 'max:2048'],
        ]);

        $file = $request->file('file');

        $entries = $this->newestFirst($file->getClientOriginalExtension() === 'xlsx'
            ? $this->parseSpreadsheet($file->getRealPath())
            : $this->parseCsv($file->getRealPath()));

        $running = array_filter(
            $entries,
            fn (array $entry): bool => $entry['clockedOutAt'] === null && $entry['status'] === self::PRESENT,
        );

        if (count($running) > 1) {
            throw ValidationException::withMessages([
                'file' => 'Only one entry may be left without a clock out time.',
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $entries);

        return to_route('attendance.index');
    }

    /**
     * Copy the template and write each day's hours into the cells the template
     * already laid out, leaving days without attendance untouched.
     *
     * @param  list<Entry>  $entries
     * @return string the path of the filled copy
     */
    private function fill(array $entries): string
    {
        $template = resource_path('templates/timesheet.xlsx');

        if (! is_file($template)) {
            throw new RuntimeException("The timesheet template is missing from {$template}.");
        }

        $path = tempnam(sys_get_temp_dir(), 'timesheet');

        if ($path === false || ! copy($template, $path)) {
            throw new RuntimeException('The timesheet template could not be copied.');
        }

        $zip = $this->open($path);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheet === false) {
            throw new RuntimeException('The timesheet template has no first worksheet.');
        }

        $document = new DOMDocument;
        $document->loadXML($sheet);
        $xpath = $this->xpath($document);

        $byDate = $this->groupByDate($entries);

        foreach (range(self::FIRST_DATA_ROW, self::LAST_DATA_ROW) as $row) {
            $serial = $this->numberAt($xpath, self::DATE_COLUMN.$row);

            if ($serial === null) {
                continue;
            }

            $day = $byDate[$this->dateFromSerial($serial)] ?? [];

            if ($day === []) {
                continue;
            }

            $this->fillRow($document, $xpath, $row, $day);
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', (string) $document->saveXML());

        /** The cached results of the template's COUNTA totals are stale once we add days. */
        $zip->deleteName('xl/calcChain.xml');
        $this->forceRecalculation($zip);
        $zip->close();

        return $path;
    }

    /**
     * A day may hold several clock ins, so the row spans the first start to the last end.
     *
     * @param  non-empty-list<Entry>  $day
     */
    private function fillRow(DOMDocument $document, DOMXPath $xpath, int $row, array $day): void
    {
        usort($day, fn (array $first, array $second): int => strcmp($first['clockedInAt'], $second['clockedInAt']));

        $worked = array_values(array_filter($day, fn (array $entry): bool => $entry['status'] === self::PRESENT));

        if ($worked !== []) {
            $this->setNumber($document, $xpath, self::START_COLUMN.$row, $this->fractionOfDay(Carbon::parse($worked[0]['clockedInAt'])));

            $ends = array_filter(array_column($worked, 'clockedOutAt'));

            if ($ends !== []) {
                $this->setNumber($document, $xpath, self::END_COLUMN.$row, $this->fractionOfDay(Carbon::parse(max($ends))));
            }

            $seconds = array_sum(array_map($this->durationInSeconds(...), $worked));

            if ($seconds > 0) {
                $this->setNumber($document, $xpath, self::TOTAL_COLUMN.$row, $seconds / self::SECONDS_PER_DAY);
            }
        }

        /** Each status is counted in its own column, so mark the last one claimed for the day. */
        foreach (array_unique(array_column($day, 'status')) as $status) {
            $this->setText($document, $xpath, self::STATUSES[$status].$row, $status);
        }

        $remark = implode('; ', array_unique(array_filter(array_map(
            fn (array $entry): string => trim($entry['description']),
            $day,
        ))));

        if ($remark !== '') {
            $this->setText($document, $xpath, self::REMARK_COLUMN.$row, $remark);
        }
    }

    /**
     * Read the attendance columns back out of a filled template.
     *
     * @return list<Entry>
     *
     * @throws ValidationException
     */
    private function parseSpreadsheet(string $path): array
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

        foreach (range(self::FIRST_DATA_ROW, self::LAST_DATA_ROW) as $row) {
            $serial = $this->numberAt($xpath, self::DATE_COLUMN.$row);
            $start = $this->numberAt($xpath, self::START_COLUMN.$row);
            $status = $this->statusAt($xpath, $row, $strings);

            if ($serial === null || ($start === null && $status === null)) {
                continue;
            }

            $date = Carbon::parse($this->dateFromSerial($serial));
            $clockedInAt = $date->copy()->addSeconds($start === null ? 0 : $this->secondsOfDay($start));

            $end = $start === null ? null : $this->numberAt($xpath, self::END_COLUMN.$row);
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
                'status' => $status ?? self::PRESENT,
                'description' => $this->textAt($xpath, self::REMARK_COLUMN.$row, $strings),
            ];
        }

        return $entries;
    }

    /**
     * Read every data row of a CSV timesheet, ignoring the header and total rows.
     *
     * @return list<Entry>
     *
     * @throws ValidationException
     */
    private function parseCsv(string $path): array
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
            'status' => self::PRESENT,
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

    /**
     * The page and the template both read the log newest first.
     *
     * @param  list<Entry>  $entries
     * @return list<Entry>
     */
    private function newestFirst(array $entries): array
    {
        usort($entries, fn (array $first, array $second): int => strcmp($second['clockedInAt'], $first['clockedInAt']));

        return $entries;
    }

    /**
     * @param  list<Entry>  $entries
     * @return array<string, non-empty-list<Entry>>
     */
    private function groupByDate(array $entries): array
    {
        $byDate = [];

        foreach ($entries as $entry) {
            $byDate[Carbon::parse($entry['clockedInAt'])->format('Y-m-d')][] = $entry;
        }

        return $byDate;
    }

    /**
     * An entry still running counts as zero, since its end time is unknown.
     *
     * @param  Entry  $entry
     */
    private function durationInSeconds(array $entry): int
    {
        if ($entry['clockedOutAt'] === null) {
            return 0;
        }

        return (int) Carbon::parse($entry['clockedInAt'])
            ->diffInSeconds(Carbon::parse($entry['clockedOutAt']));
    }

    private function fractionOfDay(Carbon $moment): float
    {
        return (($moment->hour * 3600) + ($moment->minute * 60) + $moment->second) / self::SECONDS_PER_DAY;
    }

    private function secondsOfDay(float $fraction): int
    {
        return (int) round($fraction * self::SECONDS_PER_DAY);
    }

    private function dateFromSerial(float $serial): string
    {
        return Carbon::createFromTimestampUTC((int) (floor($serial) - self::EXCEL_EPOCH_OFFSET) * self::SECONDS_PER_DAY)
            ->format('Y-m-d');
    }

    /**
     * Tell Excel to recalculate the template's own totals when the file is opened.
     */
    private function forceRecalculation(ZipArchive $zip): void
    {
        $workbook = $zip->getFromName('xl/workbook.xml');

        if ($workbook === false) {
            return;
        }

        $document = new DOMDocument;
        $document->loadXML($workbook);

        $properties = $document->getElementsByTagNameNS(self::SPREADSHEET_NAMESPACE, 'calcPr')->item(0);

        if (! $properties instanceof DOMElement) {
            return;
        }

        $properties->setAttribute('fullCalcOnLoad', '1');

        $zip->addFromString('xl/workbook.xml', (string) $document->saveXML());
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

        foreach ($document->getElementsByTagNameNS(self::SPREADSHEET_NAMESPACE, 'si') as $item) {
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

        $value = $cell->getElementsByTagNameNS(self::SPREADSHEET_NAMESPACE, 'v')->item(0);

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
        foreach (self::STATUSES as $status => $column) {
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
            $index = $cell->getElementsByTagNameNS(self::SPREADSHEET_NAMESPACE, 'v')->item(0);

            return $index === null ? '' : ($strings[(int) $index->textContent] ?? '');
        }

        return trim($cell->textContent);
    }

    private function setNumber(DOMDocument $document, DOMXPath $xpath, string $reference, float $value): void
    {
        $cell = $this->cell($document, $xpath, $reference);
        $cell->removeAttribute('t');

        $this->replaceValue($document, $cell, 'v', (string) round($value, 10));
    }

    private function setText(DOMDocument $document, DOMXPath $xpath, string $reference, string $value): void
    {
        $cell = $this->cell($document, $xpath, $reference);
        $cell->setAttribute('t', 'inlineStr');

        while ($cell->firstChild !== null) {
            $cell->removeChild($cell->firstChild);
        }

        $inline = $document->createElementNS(self::SPREADSHEET_NAMESPACE, 'is');
        $inline->appendChild($document->createElementNS(self::SPREADSHEET_NAMESPACE, 't', htmlspecialchars($value, ENT_XML1)));
        $cell->appendChild($inline);
    }

    private function replaceValue(DOMDocument $document, DOMElement $cell, string $tag, string $value): void
    {
        while ($cell->firstChild !== null) {
            $cell->removeChild($cell->firstChild);
        }

        $cell->appendChild($document->createElementNS(self::SPREADSHEET_NAMESPACE, $tag, $value));
    }

    /**
     * Find the cell, or add it in column order when the template left it out.
     */
    private function cell(DOMDocument $document, DOMXPath $xpath, string $reference): DOMElement
    {
        $existing = $this->find($xpath, '//x:c[@r="'.$reference.'"]');

        if ($existing !== null) {
            return $existing;
        }

        if (preg_match('/^([A-Z]+)(\d+)$/', $reference, $matches) !== 1) {
            throw new RuntimeException("{$reference} is not a cell reference.");
        }

        $row = $this->find($xpath, '//x:row[@r="'.$matches[2].'"]');

        if ($row === null) {
            throw new RuntimeException("The timesheet template has no row {$matches[2]}.");
        }

        $cell = $document->createElementNS(self::SPREADSHEET_NAMESPACE, 'c');
        $cell->setAttribute('r', $reference);

        foreach ($row->getElementsByTagNameNS(self::SPREADSHEET_NAMESPACE, 'c') as $sibling) {
            if (preg_match('/^([A-Z]+)/', $sibling->getAttribute('r'), $siblingColumn) !== 1) {
                continue;
            }

            if ($this->columnIndex($siblingColumn[1]) > $this->columnIndex($matches[1])) {
                $row->insertBefore($cell, $sibling);

                return $cell;
            }
        }

        $row->appendChild($cell);

        return $cell;
    }

    private function columnIndex(string $column): int
    {
        $index = 0;

        foreach (str_split($column) as $letter) {
            $index = ($index * 26) + (ord($letter) - ord('A') + 1);
        }

        return $index;
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
        $xpath->registerNamespace('x', self::SPREADSHEET_NAMESPACE);

        return $xpath;
    }

    private function open(string $path): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("The workbook at {$path} could not be opened.");
        }

        return $zip;
    }

    /**
     * Open a new entry, newest first.
     *
     * @param  list<Entry>  $entries
     * @return list<Entry>
     *
     * @throws ValidationException
     */
    private function clockIn(array $entries, string $description): array
    {
        if ($this->activeEntry($entries) !== null) {
            throw ValidationException::withMessages([
                'action' => 'You are already clocked in.',
            ]);
        }

        array_unshift($entries, [
            'id' => (string) Str::uuid(),
            'clockedInAt' => now()->toIso8601String(),
            'clockedOutAt' => null,
            'status' => self::PRESENT,
            'description' => $description,
        ]);

        return $entries;
    }

    /**
     * Close the open entry, keeping its original description when no new one is written.
     *
     * @param  list<Entry>  $entries
     * @return list<Entry>
     *
     * @throws ValidationException
     */
    private function clockOut(array $entries, string $description): array
    {
        $activeEntry = $this->activeEntry($entries);

        if ($activeEntry === null) {
            throw ValidationException::withMessages([
                'action' => 'You are not clocked in.',
            ]);
        }

        return array_map(function (array $entry) use ($activeEntry, $description): array {
            if ($entry['id'] !== $activeEntry['id']) {
                return $entry;
            }

            return [
                ...$entry,
                'clockedOutAt' => now()->toIso8601String(),
                'description' => $description !== '' ? $description : $entry['description'],
            ];
        }, $entries);
    }

    /**
     * Entries logged before statuses existed are read back as present days.
     *
     * @return list<Entry>
     */
    private function entries(Request $request): array
    {
        return array_map(fn (array $entry): array => [
            'id' => (string) $entry['id'],
            'clockedInAt' => (string) $entry['clockedInAt'],
            'clockedOutAt' => isset($entry['clockedOutAt']) ? (string) $entry['clockedOutAt'] : null,
            'status' => (string) ($entry['status'] ?? self::PRESENT),
            'description' => (string) ($entry['description'] ?? ''),
        ], array_values((array) $request->session()->get(self::SESSION_KEY, [])));
    }

    /**
     * Only a present day can be running; a sick or vacation day simply has no hours.
     *
     * @param  list<Entry>  $entries
     * @return Entry|null
     */
    private function activeEntry(array $entries): ?array
    {
        foreach ($entries as $entry) {
            if ($entry['clockedOutAt'] === null && $entry['status'] === self::PRESENT) {
                return $entry;
            }
        }

        return null;
    }
}
