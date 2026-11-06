<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Support\HolidayCalendar;
use App\Support\Timesheets\CsvImporter;
use App\Support\Timesheets\TemplateImporter;
use App\Support\Timesheets\TimesheetLayout;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * @phpstan-type Entry array{id: string, clockedInAt: string, clockedOutAt: string|null, status: string, description: string, extra: array<string, string>}
 *
 * @phpstan-import-type Holiday from HolidayCalendar
 */
class AttendanceController extends Controller
{
    /**
     * The light grey weekends are painted with: Excel's white darkened by a
     * quarter. A template that already carries it keeps its own fill; one that
     * does not is given this fill.
     */
    private const WEEKEND_GREY = 'FFD9D9D9';

    /**
     * Excel reserves the first fill of every workbook for "no fill".
     */
    private const PLAIN_FILL = 0;

    /**
     * The values a day carries for the columns the user's template adds,
     * keyed by each column's heading.
     *
     * @var array<string, list<string>>
     */
    private const EXTRA_RULES = [
        'extra' => ['nullable', 'array'],
        'extra.*' => ['nullable', 'string', 'max:255'],
    ];

    /**
     * The table body the weekend shading spans. The date column is left out
     * because it carries its own grey on every row.
     *
     * @var list<string>
     */
    private const SHADED_COLUMNS = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];

    /**
     * Cell formats already cloned for a fill, keyed "source:fill", so a workbook
     * gains one new format per format it actually repaints rather than one per cell.
     *
     * @var array<string, int>
     */
    private array $styleVariants = [];

    public function index(Request $request, HolidayCalendar $calendar): Response
    {
        $entries = $this->entries($request);
        $year = (int) now()->year;

        return Inertia::render('attendance', [
            'entries' => $entries,
            'activeEntry' => $this->activeEntry($entries),
            'holidays' => $calendar->forYear($year),
            'holidayYear' => $year,
            'timesheetTemplate' => $request->user()->timesheetTemplatePath() !== null
                ? $request->user()->timesheet_template_name
                : null,
            'templateColumns' => array_column($request->user()->timesheetTemplateColumns(), 'label'),
        ]);
    }

    /**
     * The days off of one year, which the calendar pulls as it is paged past
     * the year the page was loaded with.
     *
     * @throws ValidationException
     */
    public function holidays(Request $request, HolidayCalendar $calendar): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:1970', 'max:2200'],
        ]);

        return response()->json($calendar->forYear((int) $validated['year']));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:clock-in,clock-out'],
            'description' => ['nullable', 'string', 'max:1000'],
            ...self::EXTRA_RULES,
        ]);

        $description = trim((string) ($validated['description'] ?? ''));
        $extra = $this->extraValues($request, $validated['extra'] ?? []);

        if ($validated['action'] === 'clock-in') {
            $this->clockIn($request, $description, $extra);
        } else {
            $this->clockOut($request, $description, $extra);
        }

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
            'status' => ['required', Rule::in(array_keys(TimesheetLayout::STATUSES))],
            'startedAt' => ['required_if:status,'.TimesheetLayout::PRESENT, 'nullable', 'date_format:H:i'],
            'endedAt' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:1000'],
            ...self::EXTRA_RULES,
        ]);

        $present = $validated['status'] === TimesheetLayout::PRESENT;

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

        if ($present && $clockedOutAt === null && $this->openEntry($request) !== null) {
            throw ValidationException::withMessages([
                'endedAt' => 'Give this day an end time, because another entry is still running.',
            ]);
        }

        $request->user()->attendances()->create([
            'clocked_in_at' => $clockedInAt,
            'clocked_out_at' => $clockedOutAt,
            'status' => $validated['status'],
            'description' => trim((string) ($validated['description'] ?? '')),
            'extra' => $this->extraValues($request, $validated['extra'] ?? []),
        ]);

        return to_route('attendance.index');
    }

    /**
     * Drop a single day from the log.
     */
    public function destroyEntry(Request $request, string $entry): RedirectResponse
    {
        $request->user()->attendances()->whereKey($entry)->delete();

        return to_route('attendance.index');
    }

    /**
     * Hand back a copy of the user's timesheet template with the attendance columns filled in.
     *
     * @throws ValidationException
     */
    public function download(Request $request, HolidayCalendar $calendar): BinaryFileResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $template = $request->user()->timesheetTemplatePath();

        if ($template === null) {
            throw ValidationException::withMessages([
                'template' => 'Upload a timesheet template before downloading a timesheet.',
            ]);
        }

        $month = Carbon::createFromFormat('Y-m', $validated['month'] ?? now()->format('Y-m'))->startOfMonth();

        $path = $this->fill($request->user(), $template, $this->entries($request), $month, $calendar->forYear((int) $month->year));

        return response()
            ->download($path, 'timesheet-'.$month->format('Y-m').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * Merge the rows of an uploaded timesheet into the log. Every day the file
     * carries is rewritten from the file; every day it leaves out is kept as it
     * stands, because a timesheet covers one month and must not take the rest
     * of the log down with it.
     *
     * @throws ValidationException
     */
    public function upload(Request $request, TemplateImporter $template, CsvImporter $csv): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,csv,txt', 'max:2048'],
        ]);

        $file = $request->file('file');

        $entries = $this->newestFirst($file->getClientOriginalExtension() === 'xlsx'
            ? $template->import($file->getRealPath())
            : $csv->import($file->getRealPath()));

        if ($entries === []) {
            throw ValidationException::withMessages([
                'file' => 'The timesheet has no attendance rows to import.',
            ]);
        }

        $days = array_keys($this->groupByDate($entries));

        $this->guardOneOpenEntry($request, $entries, $days);

        /** An upload rewrites whole days, so a half written import must not survive. */
        DB::transaction(function () use ($request, $entries, $days): void {
            $request->user()->attendances()
                ->where(fn (Builder $query): Builder => $this->onAnyDay($query, $days))
                ->delete();

            $request->user()->attendances()->createMany(array_map(fn (array $entry): array => [
                'clocked_in_at' => Carbon::parse($entry['clockedInAt']),
                'clocked_out_at' => $entry['clockedOutAt'] !== null ? Carbon::parse($entry['clockedOutAt']) : null,
                'status' => $entry['status'],
                'description' => $entry['description'],
                'extra' => $entry['extra'],
            ], $entries));
        });

        return to_route('attendance.index');
    }

    /**
     * Only one day may be left running at a time. The file's own rows are
     * counted together with the running days it does not cover, since a merge
     * leaves those in place.
     *
     * @param  list<Entry>  $entries
     * @param  list<string>  $days
     *
     * @throws ValidationException
     */
    private function guardOneOpenEntry(Request $request, array $entries, array $days): void
    {
        $uploaded = count(array_filter(
            $entries,
            fn (array $entry): bool => $entry['clockedOutAt'] === null && $entry['status'] === TimesheetLayout::PRESENT,
        ));

        if ($uploaded > 1) {
            throw ValidationException::withMessages([
                'file' => 'Only one entry may be left without a clock out time.',
            ]);
        }

        $kept = $request->user()->attendances()
            ->whereNull('clocked_out_at')
            ->where('status', TimesheetLayout::PRESENT)
            ->whereNot(fn (Builder $query): Builder => $this->onAnyDay($query, $days))
            ->count();

        if ($uploaded + $kept > 1) {
            throw ValidationException::withMessages([
                'file' => 'Another day is still running. Close it before importing a timesheet that leaves an entry open.',
            ]);
        }
    }

    /**
     * Narrow a query to the entries clocked into on any of the given days. The
     * days are matched as ranges rather than with a date function so the
     * user and clock in index still carries the lookup.
     *
     * @param  Builder<Attendance>  $query
     * @param  list<string>  $days
     * @return Builder<Attendance>
     */
    private function onAnyDay(Builder $query, array $days): Builder
    {
        foreach ($days as $day) {
            $start = Carbon::createFromFormat('Y-m-d', $day)->startOfDay();

            $query->orWhereBetween('clocked_in_at', [$start, $start->copy()->endOfDay()]);
        }

        return $query;
    }

    /**
     * Copy the template and write the requested month into it: the period label,
     * the date column, and each day's hours. Days without attendance keep the
     * empty cells the template author laid out.
     *
     * @param  list<Entry>  $entries
     * @param  array<string, Holiday>  $holidays
     * @return string the path of the filled copy
     */
    private function fill(User $user, string $template, array $entries, Carbon $month, array $holidays): string
    {
        $path = tempnam(sys_get_temp_dir(), 'timesheet');

        if ($path === false || ! copy($template, $path)) {
            throw new RuntimeException('The timesheet template could not be copied.');
        }

        $zip = $this->open($path);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheet === false) {
            throw new RuntimeException('The timesheet template has no first worksheet.');
        }

        $stylesXml = $zip->getFromName('xl/styles.xml');

        if ($stylesXml === false) {
            throw new RuntimeException('The timesheet template has no styles.');
        }

        $document = new DOMDocument;
        $document->loadXML($sheet);
        $xpath = $this->xpath($document);

        $styles = new DOMDocument;
        $styles->loadXML($stylesXml);
        $cellXfs = $styles->getElementsByTagName('cellXfs')->item(0);

        if (! $cellXfs instanceof DOMElement) {
            throw new RuntimeException('The timesheet template has no cell formats.');
        }

        /** The clones are only meaningful for this workbook's style table. */
        $this->styleVariants = [];

        $weekendFill = $this->weekendFill($styles);

        $byDate = $this->groupByDate($entries);

        /** The template writes its own period heading from a date serial. */
        $this->setNumber($document, $xpath, TimesheetLayout::PERIOD_CELL, $this->serialFromDate($month));

        $this->fillProfile($document, $xpath, $user);

        $columns = $user->timesheetTemplateColumns();

        /** A sheet is signed on the day it is taken, whichever month it covers. */
        foreach (TimesheetLayout::SIGNATURE_DATE_CELLS as $reference) {
            $this->setText($document, $xpath, $reference, 'DATE: '.now()->format('d/m/Y'));
        }

        $daysInMonth = $month->daysInMonth;

        foreach (range(TimesheetLayout::FIRST_DATA_ROW, TimesheetLayout::LAST_DATA_ROW) as $index => $row) {
            $dayOfMonth = $index + 1;

            /** The template holds 31 day rows, so a shorter month leaves spares to clear. */
            if ($dayOfMonth > $daysInMonth) {
                $this->clearRow($xpath, $row, $columns);
                $this->shadeRow($xpath, $cellXfs, $row, self::PLAIN_FILL);

                continue;
            }

            $date = $month->copy()->setDay($dayOfMonth);
            $key = $date->format('Y-m-d');
            $holiday = $holidays[$key] ?? null;

            $this->setNumber($document, $xpath, TimesheetLayout::DATE_COLUMN.$row, $this->serialFromDate($date));

            /**
             * The template's own shading was painted for the month it shipped with,
             * so every row is repainted from the requested month's calendar. A
             * holiday is a day off like a weekend, so it takes the same grey.
             */
            $this->shadeRow(
                $xpath,
                $cellXfs,
                $row,
                $date->isWeekend() || $holiday !== null ? $weekendFill : self::PLAIN_FILL,
            );

            $day = $byDate[$key] ?? [];

            if ($day === []) {
                /** A day nobody worked can still say why it was off. */
                if ($holiday !== null) {
                    $this->setText($document, $xpath, TimesheetLayout::REMARK_COLUMN.$row, $holiday['name']);
                }

                continue;
            }

            $this->fillRow($document, $xpath, $row, $day, $columns);
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', (string) $document->saveXML());
        $zip->addFromString('xl/styles.xml', (string) $styles->saveXML());

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
     * @param  list<array{column: string, label: string}>  $columns
     */
    private function fillRow(DOMDocument $document, DOMXPath $xpath, int $row, array $day, array $columns): void
    {
        usort($day, fn (array $first, array $second): int => strcmp($first['clockedInAt'], $second['clockedInAt']));

        $worked = array_values(array_filter($day, fn (array $entry): bool => $entry['status'] === TimesheetLayout::PRESENT));

        if ($worked !== []) {
            $this->setNumber($document, $xpath, TimesheetLayout::START_COLUMN.$row, $this->fractionOfDay(Carbon::parse($worked[0]['clockedInAt'])));

            $ends = array_filter(array_column($worked, 'clockedOutAt'));

            if ($ends !== []) {
                $this->setNumber($document, $xpath, TimesheetLayout::END_COLUMN.$row, $this->fractionOfDay(Carbon::parse(max($ends))));
            }

            $seconds = array_sum(array_map($this->durationInSeconds(...), $worked));

            if ($seconds > 0) {
                $this->setNumber($document, $xpath, TimesheetLayout::TOTAL_COLUMN.$row, $seconds / TimesheetLayout::SECONDS_PER_DAY);
            }
        }

        /** Each status is counted in its own column, so mark the last one claimed for the day. */
        foreach (array_unique(array_column($day, 'status')) as $status) {
            $this->setText($document, $xpath, TimesheetLayout::STATUSES[$status].$row, $status);
        }

        $remark = implode('; ', array_unique(array_filter(array_map(
            fn (array $entry): string => trim($entry['description']),
            $day,
        ))));

        if ($remark !== '') {
            $this->setText($document, $xpath, TimesheetLayout::REMARK_COLUMN.$row, $remark);
        }

        /** A column the template added reads like the remark: every distinct value of the day. */
        foreach ($columns as $column) {
            $value = implode('; ', array_unique(array_filter(array_map(
                fn (array $entry): string => trim($entry['extra'][$column['label']] ?? ''),
                $day,
            ))));

            if ($value !== '') {
                $this->setText($document, $xpath, $column['column'].$row, $value);
            }
        }
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
        return (($moment->hour * 3600) + ($moment->minute * 60) + $moment->second) / TimesheetLayout::SECONDS_PER_DAY;
    }

    /**
     * The inverse of the importer's own serial reading, so a written date reads
     * back as the same day.
     */
    private function serialFromDate(Carbon $date): float
    {
        $midnight = Carbon::createFromFormat('Y-m-d', $date->format('Y-m-d'), 'UTC')->startOfDay();

        return $midnight->getTimestamp() / TimesheetLayout::SECONDS_PER_DAY + TimesheetLayout::EXCEL_EPOCH_OFFSET;
    }

    /**
     * The index of the weekend grey in the workbook's fill table. A light grey
     * the template already uses is reused, so a template painted by hand keeps
     * its own shade; otherwise the grey is added to the end of the table.
     */
    private function weekendFill(DOMDocument $styles): int
    {
        $fills = $styles->getElementsByTagName('fills')->item(0);

        if (! $fills instanceof DOMElement) {
            throw new RuntimeException('The timesheet template has no fill table.');
        }

        $entries = $fills->getElementsByTagName('fill');

        foreach ($entries as $index => $fill) {
            $pattern = $fill->getElementsByTagName('patternFill')->item(0);
            $color = $pattern?->getElementsByTagName('fgColor')->item(0);

            if (! $pattern instanceof DOMElement || ! $color instanceof DOMElement || $pattern->getAttribute('patternType') !== 'solid') {
                continue;
            }

            $quarterDarkerWhite = $color->getAttribute('theme') === '0'
                && abs((float) $color->getAttribute('tint') + 0.25) < 0.01;

            if ($quarterDarkerWhite || strtoupper($color->getAttribute('rgb')) === self::WEEKEND_GREY) {
                return $index;
            }
        }

        /** The list is live, so its length before the append is the new fill's index. */
        $index = $entries->length;

        $namespace = TimesheetLayout::SPREADSHEET_NAMESPACE;

        $pattern = $styles->createElementNS($namespace, 'patternFill');
        $pattern->setAttribute('patternType', 'solid');

        $color = $styles->createElementNS($namespace, 'fgColor');
        $color->setAttribute('rgb', self::WEEKEND_GREY);
        $pattern->appendChild($color);

        $background = $styles->createElementNS($namespace, 'bgColor');
        $background->setAttribute('indexed', '64');
        $pattern->appendChild($background);

        $fill = $styles->createElementNS($namespace, 'fill');
        $fill->appendChild($pattern);
        $fills->appendChild($fill);
        $fills->setAttribute('count', (string) ($index + 1));

        return $index;
    }

    /**
     * Repaint a day row's background, keeping every other part of each cell's
     * format - its borders, fonts and number formats - exactly as it was.
     */
    private function shadeRow(DOMXPath $xpath, DOMElement $cellXfs, int $row, int $fillId): void
    {
        foreach (self::SHADED_COLUMNS as $column) {
            $cell = $this->find($xpath, '//x:c[@r="'.$column.$row.'"]');

            if ($cell === null) {
                continue;
            }

            $cell->setAttribute('s', (string) $this->variantOf($cellXfs, (int) $cell->getAttribute('s'), $fillId));
        }
    }

    /**
     * The index of a cell format that matches the given one but paints the given
     * fill, adding it to the workbook's format table the first time it is asked for.
     */
    private function variantOf(DOMElement $cellXfs, int $source, int $fillId): int
    {
        $key = $source.':'.$fillId;

        if (isset($this->styleVariants[$key])) {
            return $this->styleVariants[$key];
        }

        $formats = $cellXfs->getElementsByTagName('xf');
        $origin = $formats->item($source);

        if (! $origin instanceof DOMElement) {
            return $source;
        }

        if ((int) $origin->getAttribute('fillId') === $fillId) {
            return $this->styleVariants[$key] = $source;
        }

        /** The list is live, so its length before the clone is the clone's own index. */
        $index = $formats->length;

        $clone = $origin->cloneNode(true);

        if (! $clone instanceof DOMElement) {
            return $source;
        }

        $clone->setAttribute('fillId', (string) $fillId);
        $clone->setAttribute('applyFill', '1');

        $cellXfs->appendChild($clone);
        $cellXfs->setAttribute('count', (string) ($index + 1));

        return $this->styleVariants[$key] = $index;
    }

    /**
     * Blank every cell the attendance log writes to, so the template's spare
     * rows do not keep a day that the requested month does not have.
     *
     * @param  list<array{column: string, label: string}>  $extraColumns
     */
    private function clearRow(DOMXPath $xpath, int $row, array $extraColumns): void
    {
        $columns = [
            TimesheetLayout::DATE_COLUMN,
            TimesheetLayout::START_COLUMN,
            TimesheetLayout::END_COLUMN,
            TimesheetLayout::TOTAL_COLUMN,
            ...array_values(TimesheetLayout::STATUSES),
            TimesheetLayout::REMARK_COLUMN,
            ...array_column($extraColumns, 'column'),
        ];

        foreach ($columns as $column) {
            $cell = $this->find($xpath, '//x:c[@r="'.$column.$row.'"]');

            if ($cell === null) {
                continue;
            }

            /** Dropping the value leaves the cell's own borders and formats alone. */
            $cell->removeAttribute('t');

            while ($cell->firstChild !== null) {
                $cell->removeChild($cell->firstChild);
            }
        }
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

        $properties = $document->getElementsByTagNameNS(TimesheetLayout::SPREADSHEET_NAMESPACE, 'calcPr')->item(0);

        if (! $properties instanceof DOMElement) {
            return;
        }

        $properties->setAttribute('fullCalcOnLoad', '1');

        $zip->addFromString('xl/workbook.xml', (string) $document->saveXML());
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

        $inline = $document->createElementNS(TimesheetLayout::SPREADSHEET_NAMESPACE, 'is');
        $inline->appendChild($document->createElementNS(TimesheetLayout::SPREADSHEET_NAMESPACE, 't', htmlspecialchars($value, ENT_XML1)));
        $cell->appendChild($inline);
    }

    private function replaceValue(DOMDocument $document, DOMElement $cell, string $tag, string $value): void
    {
        while ($cell->firstChild !== null) {
            $cell->removeChild($cell->firstChild);
        }

        $cell->appendChild($document->createElementNS(TimesheetLayout::SPREADSHEET_NAMESPACE, $tag, $value));
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

        $cell = $document->createElementNS(TimesheetLayout::SPREADSHEET_NAMESPACE, 'c');
        $cell->setAttribute('r', $reference);

        foreach ($row->getElementsByTagNameNS(TimesheetLayout::SPREADSHEET_NAMESPACE, 'c') as $sibling) {
            if (preg_match('/^([A-Z]+)/', $sibling->getAttribute('r'), $siblingColumn) !== 1) {
                continue;
            }

            if (TimesheetLayout::columnIndex($siblingColumn[1]) > TimesheetLayout::columnIndex($matches[1])) {
                $row->insertBefore($cell, $sibling);

                return $cell;
            }
        }

        $row->appendChild($cell);

        return $cell;
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

    private function open(string $path): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("The workbook at {$path} could not be opened.");
        }

        return $zip;
    }

    /**
     * Keep only the values for columns the user's template actually has, so a
     * stale form cannot store a heading the export would never write.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<string, string>
     */
    private function extraValues(Request $request, array $values): array
    {
        $extra = [];

        foreach (array_column($request->user()->timesheetTemplateColumns(), 'label') as $label) {
            $value = trim((string) ($values[$label] ?? ''));

            if ($value !== '') {
                $extra[$label] = $value;
            }
        }

        return $extra;
    }

    /**
     * Write the employee block and the employee's signature name from their
     * profile. A detail the profile leaves blank keeps the template's own text.
     */
    private function fillProfile(DOMDocument $document, DOMXPath $xpath, User $user): void
    {
        foreach (TimesheetLayout::PROFILE_CELLS as $field => $reference) {
            $value = trim((string) $user->getAttribute($field));

            if ($value !== '') {
                $this->setText($document, $xpath, $reference, ': '.$value);
            }
        }

        if (trim($user->name) !== '') {
            $this->setText($document, $xpath, TimesheetLayout::SIGNATURE_NAME_CELL, '('.trim($user->name).')');
        }
    }

    /**
     * Open a new entry for the signed in user.
     *
     * @param  array<string, string>  $extra
     *
     * @throws ValidationException
     */
    private function clockIn(Request $request, string $description, array $extra): void
    {
        if ($this->openEntry($request) !== null) {
            throw ValidationException::withMessages([
                'action' => 'You are already clocked in.',
            ]);
        }

        $request->user()->attendances()->create([
            'clocked_in_at' => now(),
            'clocked_out_at' => null,
            'status' => TimesheetLayout::PRESENT,
            'description' => $description,
            'extra' => $extra,
        ]);
    }

    /**
     * Close the open entry, keeping its original description when no new one is written.
     *
     * @param  array<string, string>  $extra
     *
     * @throws ValidationException
     */
    private function clockOut(Request $request, string $description, array $extra): void
    {
        $openEntry = $this->openEntry($request);

        if ($openEntry === null) {
            throw ValidationException::withMessages([
                'action' => 'You are not clocked in.',
            ]);
        }

        $openEntry->update([
            'clocked_out_at' => now(),
            'description' => $description !== '' ? $description : $openEntry->description,
            /** Like the description, a value left blank on the way out keeps the one given on the way in. */
            'extra' => [...($openEntry->extra ?? []), ...$extra],
        ]);
    }

    /**
     * The signed in user's days, newest first.
     *
     * @return list<Entry>
     */
    private function entries(Request $request): array
    {
        return array_values(
            $request->user()
                ->attendances()
                ->orderByDesc('clocked_in_at')
                ->get()
                ->map(fn (Attendance $attendance): array => $attendance->toEntry())
                ->all()
        );
    }

    /**
     * The row the user is currently clocked into, if any. Only a present day can
     * be running; a sick or vacation day simply has no hours.
     */
    private function openEntry(Request $request): ?Attendance
    {
        return $request->user()
            ->attendances()
            ->whereNull('clocked_out_at')
            ->where('status', TimesheetLayout::PRESENT)
            ->latest('clocked_in_at')
            ->first();
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
            if ($entry['clockedOutAt'] === null && $entry['status'] === TimesheetLayout::PRESENT) {
                return $entry;
            }
        }

        return null;
    }
}
