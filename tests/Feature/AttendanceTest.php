<?php

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/** The attendance routes now sit behind the auth middleware. */
beforeEach(function () {
    $this->user = User::factory()->create();

    $this->actingAs($this->user);

    /** The days off are read from Google, which the suite must never call. */
    fakeHolidayFeed();
});

/**
 * Answer the holiday feed with the 2026 calendar the fixture holds. A second
 * Http::fake would only queue another stub behind this one, so the answer is
 * held in a closure the test may swap out instead.
 */
function fakeHolidayFeed(): void
{
    holidayFeedAnswers(fn () => Http::response(
        (string) file_get_contents(base_path('tests/Fixtures/holidays.ics')),
    ));

    Http::fake([
        'calendar.google.com/*' => fn () => (test()->holidayFeed)(),
    ]);
}

/**
 * Hand the holiday feed a different answer for the rest of the test.
 */
function holidayFeedAnswers(Closure $answer): void
{
    test()->holidayFeed = $answer;
}

/**
 * Put a day in the signed in user's log.
 */
function logEntry(string $clockedInAt, ?string $clockedOutAt = null, string $status = 'P', string $description = ''): Attendance
{
    return Attendance::factory()->for(test()->user)->create([
        'clocked_in_at' => Carbon::parse($clockedInAt),
        'clocked_out_at' => $clockedOutAt !== null ? Carbon::parse($clockedOutAt) : null,
        'status' => $status,
        'description' => $description,
    ]);
}

function openAttendanceEntry(string $description = 'Started the shift'): Attendance
{
    return logEntry(now()->subHour()->toIso8601String(), null, 'P', $description);
}

/**
 * Read the log back in the shape the page and the timesheet writer speak.
 *
 * @return list<array{id: string, clockedInAt: string, clockedOutAt: string|null, status: string, description: string}>
 */
function storedEntries(): array
{
    return test()->user->attendances()
        ->orderByDesc('clocked_in_at')
        ->get()
        ->map(fn (Attendance $attendance): array => $attendance->toEntry())
        ->all();
}

test('the attendance page starts with no entries', function () {
    $this->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance')
            ->where('entries', [])
            ->where('activeEntry', null)
        );
});

test('the attendance page exposes the open entry', function () {
    $entry = openAttendanceEntry();

    $this->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries', 1)
            ->where('activeEntry.id', (string) $entry->id)
            ->where('activeEntry.description', 'Started the shift')
            ->where('activeEntry.clockedOutAt', null)
        );
});

test('clocking in opens an entry with a description', function () {
    $this->post(route('attendance.store'), [
        'action' => 'clock-in',
        'description' => 'Started the shift',
    ])->assertRedirect(route('attendance.index'));

    $entries = storedEntries();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['description'])->toBe('Started the shift')
        ->and($entries[0]['clockedOutAt'])->toBeNull();
});

test('clocking out closes the open entry', function () {
    openAttendanceEntry();

    $this->post(route('attendance.store'), [
        'action' => 'clock-out',
        'description' => 'Wrapped up the report',
    ])->assertRedirect(route('attendance.index'));

    $entries = storedEntries();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['description'])->toBe('Wrapped up the report')
        ->and($entries[0]['clockedOutAt'])->not->toBeNull();
});

test('clocking out keeps the existing description when none is written', function () {
    openAttendanceEntry();

    $this->post(route('attendance.store'), ['action' => 'clock-out'])
        ->assertRedirect(route('attendance.index'));

    expect(storedEntries()[0]['description'])->toBe('Started the shift');
});

test('a user cannot clock in twice', function () {
    openAttendanceEntry();

    $this->post(route('attendance.store'), ['action' => 'clock-in'])
        ->assertSessionHasErrors('action');
});

test('a user cannot clock out without clocking in', function () {
    $this->post(route('attendance.store'), ['action' => 'clock-out'])
        ->assertSessionHasErrors('action');
});

test('the action is required', function () {
    $this->post(route('attendance.store'), ['description' => 'No action given'])
        ->assertSessionHasErrors('action');
});

test('one user cannot see or close another user\'s day', function () {
    $entry = Attendance::factory()->for(User::factory())->running()->create();

    $this->get(route('attendance.index'))
        ->assertInertia(fn ($page) => $page->where('entries', [])->where('activeEntry', null));

    $this->post(route('attendance.store'), ['action' => 'clock-out'])
        ->assertSessionHasErrors('action');

    $this->delete(route('attendance.entry.destroy', $entry->id));

    expect(Attendance::whereKey($entry->id)->exists())->toBeTrue();
});

test('a day picked from the calendar is added to the log', function () {
    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-12',
        'status' => 'P',
        'startedAt' => '08:30',
        'endedAt' => '16:45',
        'description' => 'Release prep',
    ])->assertRedirect(route('attendance.index'));

    $entries = storedEntries();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['clockedInAt'])->toStartWith('2026-08-12T08:30:00')
        ->and($entries[0]['clockedOutAt'])->toStartWith('2026-08-12T16:45:00')
        ->and($entries[0]['description'])->toBe('Release prep');
});

test('the log stays newest first when an older day is added', function () {
    logEntry('2026-08-20T09:00:00+00:00', '2026-08-20T17:00:00+00:00', 'P', 'Later day');

    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-03',
        'status' => 'P',
        'startedAt' => '09:00',
        'endedAt' => '17:00',
    ]);

    $entries = storedEntries();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['description'])->toBe('Later day');
});

test('a calendar day that ends before it starts runs past midnight', function () {
    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-12',
        'status' => 'P',
        'startedAt' => '22:00',
        'endedAt' => '06:00',
    ]);

    expect(storedEntries()[0]['clockedOutAt'])->toStartWith('2026-08-13T06:00:00');
});

test('a calendar day may be left running when nothing else is open', function () {
    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-12',
        'status' => 'P',
        'startedAt' => '09:00',
    ]);

    expect(storedEntries()[0]['clockedOutAt'])->toBeNull();
});

test('a second running day is rejected', function () {
    openAttendanceEntry();

    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-12',
        'status' => 'P',
        'startedAt' => '09:00',
    ])->assertSessionHasErrors('endedAt');
});

test('the calendar rejects a malformed date or time', function () {
    $this->post(route('attendance.entry.store'), [
        'date' => '12/08/2026',
        'status' => 'P',
        'startedAt' => '9am',
    ])->assertSessionHasErrors(['date', 'startedAt']);
});

test('the calendar rejects a status outside the legend', function () {
    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-12',
        'status' => 'Q',
        'startedAt' => '09:00',
    ])->assertSessionHasErrors('status');
});

test('a present day needs a start time', function () {
    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-12',
        'status' => 'P',
    ])->assertSessionHasErrors('startedAt');
});

test('a day off is recorded without hours', function () {
    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-12',
        'status' => 'S',
        'description' => 'Flu',
    ])->assertRedirect(route('attendance.index'));

    $entries = storedEntries();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['status'])->toBe('S')
        ->and($entries[0]['clockedOutAt'])->toBeNull()
        ->and($entries[0]['clockedInAt'])->toStartWith('2026-08-12T00:00:00');
});

test('a day off does not block clocking in', function () {
    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-12',
        'status' => 'V',
    ])->assertRedirect(route('attendance.index'));

    $this->post(route('attendance.store'), ['action' => 'clock-in'])
        ->assertSessionHasNoErrors();
});

test('the status of each day lands in its own template column', function () {
    logEntry('2026-08-05T09:00:00+00:00', '2026-08-05T17:00:00+00:00', 'P', 'Worked');
    logEntry('2026-08-06T00:00:00+00:00', null, 'S', 'Flu');
    logEntry('2026-08-07T00:00:00+00:00', null, 'BT', 'Client visit');

    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-08']))));

    /** Present lands in E, sick in F, business trip in G. */
    expect($cells['E15'])->toBe('P')
        ->and($cells['F16'])->toBe('S')
        ->and($cells['G17'])->toBe('BT')
        ->and($cells)->not->toHaveKey('E16')
        ->and($cells)->not->toHaveKey('B16')
        ->and($cells['K16'])->toBe('Flu');
});

test('a day off round trips through the template', function () {
    logEntry('2026-08-06T00:00:00+00:00', null, 'V', 'Annual leave');

    $workbook = downloaded($this->get(route('attendance.download', ['month' => '2026-08'])));

    $this->post(route('attendance.upload'), [
        'file' => UploadedFile::fake()->createWithContent('timesheet.xlsx', $workbook),
    ]);

    $entries = storedEntries();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['status'])->toBe('V')
        ->and($entries[0]['description'])->toBe('Annual leave')
        ->and($entries[0]['clockedOutAt'])->toBeNull();
});

test('a day can be removed from the calendar', function () {
    $entry = openAttendanceEntry();

    $this->delete(route('attendance.entry.destroy', $entry->id))
        ->assertRedirect(route('attendance.index'));

    expect(storedEntries())->toBe([]);
});

test('removing an unknown day leaves the log alone', function () {
    openAttendanceEntry();

    $this->delete(route('attendance.entry.destroy', 'missing'));

    expect(storedEntries())->toHaveCount(1);
});

function downloaded(TestResponse $response): string
{
    return (string) file_get_contents($response->getFile()->getPathname());
}

/**
 * Pull the raw cell values of the first worksheet out of a downloaded workbook.
 *
 * @return array<string, string>
 */
function cells(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'downloaded');
    file_put_contents($path, $contents);

    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();
    unlink($path);

    $shared = [];

    if ($sharedXml !== false) {
        $strings = new DOMDocument;
        $strings->loadXML($sharedXml);

        foreach ($strings->getElementsByTagName('si') as $item) {
            $shared[] = $item->textContent;
        }
    }

    $document = new DOMDocument;
    $document->loadXML((string) $sheet);

    $values = [];

    foreach ($document->getElementsByTagName('c') as $cell) {
        $text = trim($cell->textContent);

        if ($text === '') {
            continue;
        }

        $values[$cell->getAttribute('r')] = $cell->getAttribute('t') === 's'
            ? ($shared[(int) $text] ?? $text)
            : $text;
    }

    return $values;
}

/**
 * Resolve the background fill each cell of the first worksheet paints with.
 *
 * @return array<string, int>
 */
function fills(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'downloaded');
    file_put_contents($path, $contents);

    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $stylesXml = $zip->getFromName('xl/styles.xml');
    $zip->close();
    unlink($path);

    $styles = new DOMDocument;
    $styles->loadXML((string) $stylesXml);

    $fillOf = [];

    foreach ($styles->getElementsByTagName('cellXfs')->item(0)->getElementsByTagName('xf') as $format) {
        $fillOf[] = (int) $format->getAttribute('fillId');
    }

    $document = new DOMDocument;
    $document->loadXML((string) $sheet);

    $painted = [];

    foreach ($document->getElementsByTagName('c') as $cell) {
        $painted[$cell->getAttribute('r')] = $fillOf[(int) $cell->getAttribute('s')] ?? 0;
    }

    return $painted;
}

test('the weekend rows are shaded for the requested month', function () {
    $painted = fills(downloaded($this->get(route('attendance.download', ['month' => '2026-08']))));

    /** August 2026 opens on a Saturday, so its first two rows are the weekend. */
    expect($painted['B11'])->toBe(4)
        ->and($painted['N12'])->toBe(4)
        ->and($painted['B13'])->toBe(0);
});

test('the shading follows the month rather than the template', function () {
    $painted = fills(downloaded($this->get(route('attendance.download', ['month' => '2026-09']))));

    /** September 2026 opens on a Tuesday, so the first weekend is the 5th and 6th. */
    expect($painted['B11'])->toBe(0)
        ->and($painted['B15'])->toBe(4)
        ->and($painted['B16'])->toBe(4)
        ->and($painted['B17'])->toBe(0);
});

test('a weekday the template shaded by hand is repainted plain', function () {
    $painted = fills(downloaded($this->get(route('attendance.download', ['month' => '2026-09']))));

    /**
     * The template painted rows 27 and 35 grey though both are weekdays, and
     * September is the month that carries no Indonesian holiday of its own.
     */
    expect($painted['B27'])->toBe(0)
        ->and($painted['B35'])->toBe(0);
});

test('a national holiday is shaded like a weekend', function () {
    $painted = fills(downloaded($this->get(route('attendance.download', ['month' => '2026-08']))));

    /** Independence Day is the 17th and Maulid the 25th, both weekdays. */
    expect($painted['B27'])->toBe(4)
        ->and($painted['B35'])->toBe(4)
        ->and($painted['B28'])->toBe(0);
});

test('a cuti bersama is shaded like the holiday it bridges', function () {
    $painted = fills(downloaded($this->get(route('attendance.download', ['month' => '2026-12']))));

    /** Christmas eve is joint leave, and Christmas itself a holiday. */
    expect($painted['B34'])->toBe(4)
        ->and($painted['B35'])->toBe(4);
});

test('a day the feed only marks as celebrated is still a working day', function () {
    $painted = fills(downloaded($this->get(route('attendance.download', ['month' => '2026-12']))));

    /** New year's eve is an observance in the feed, not a day off. */
    expect($painted['B41'])->toBe(0);
});

test('a holiday nobody worked names itself in the remark column', function () {
    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-08']))));

    expect($cells['K27'])->toBe('Hari Proklamasi Kemerdekaan R.I.');
});

test('a remark written for a holiday outranks the holiday name', function () {
    logEntry('2026-08-17T09:00:00+00:00', '2026-08-17T17:00:00+00:00', 'P', 'Manned the stand');

    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-08']))));

    expect($cells['K27'])->toBe('Manned the stand');
});

test('the attendance page carries the days off of the current year', function () {
    $this->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance')
            ->where('holidayYear', (int) now()->year)
            ->has('holidays')
        );
});

test('the holiday endpoint answers for the year it is asked about', function () {
    $this->getJson(route('attendance.holidays', ['year' => 2026]))
        ->assertOk()
        ->assertJsonPath('2026-03-21.name', 'Hari Idul Fitri')
        ->assertJsonPath('2026-03-21.joint', false)
        ->assertJsonPath('2026-03-20.name', 'Cuti Bersama Idul Fitri')
        ->assertJsonPath('2026-03-20.joint', true)
        ->assertJsonMissingPath('2026-02-19');
});

test('an unconfirmed date keeps its name and says it is unconfirmed', function () {
    $this->getJson(route('attendance.holidays', ['year' => 2026]))
        ->assertOk()
        ->assertJsonPath('2026-05-31.name', 'Hari Raya Waisak')
        ->assertJsonPath('2026-05-31.tentative', true);
});

test('the holiday endpoint answers nothing for a year the feed omits', function () {
    $this->getJson(route('attendance.holidays', ['year' => 2019]))
        ->assertOk()
        ->assertExactJson([]);
});

test('the holiday endpoint needs a year it can read', function () {
    $this->getJson(route('attendance.holidays', ['year' => 'lastyear']))
        ->assertStatus(422);
});

test('a feed that is down leaves the calendar without days off', function () {
    holidayFeedAnswers(fn () => Http::response('', 503));

    $this->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('holidays', []));
});

test('a feed that is down still downloads a weekend shaded sheet', function () {
    holidayFeedAnswers(fn () => Http::response('', 503));

    $painted = fills(downloaded($this->get(route('attendance.download', ['month' => '2026-08']))));

    expect($painted['B11'])->toBe(4)
        ->and($painted['B27'])->toBe(0);
});

test('shading reuses cell formats rather than cloning one per cell', function () {
    $path = tempnam(sys_get_temp_dir(), 'downloaded');
    file_put_contents($path, downloaded($this->get(route('attendance.download', ['month' => '2026-09']))));

    $zip = new ZipArchive;
    $zip->open($path);
    $styles = $zip->getFromName('xl/styles.xml');
    $zip->close();
    unlink($path);

    $document = new DOMDocument;
    $document->loadXML((string) $styles);

    $formats = $document->getElementsByTagName('cellXfs')->item(0)->getElementsByTagName('xf')->length;

    /** 31 rows of 13 columns would be 403 clones without the format cache. */
    expect($formats)->toBeLessThan(220);
});

test('a cleared spare row is not shaded', function () {
    $painted = fills(downloaded($this->get(route('attendance.download', ['month' => '2026-09']))));

    expect($painted['B41'])->toBe(0);
});

test('shading a day keeps its borders and number format', function () {
    logEntry('2026-08-01T09:00:00+00:00', '2026-08-01T17:00:00+00:00', 'P', 'Saturday shift');

    $response = $this->get(route('attendance.download', ['month' => '2026-08']));
    $contents = downloaded($response);

    /** A shaded Saturday still reads its hours back as hours. */
    $cells = cells($contents);

    expect(round((float) $cells['B11'] * 24))->toBe(9.0)
        ->and(fills($contents)['B11'])->toBe(4);
});

test('the download fills the template row that matches the day', function () {
    logEntry('2026-08-05T09:12:00+00:00', '2026-08-05T17:03:00+00:00', 'P', 'Sprint planning');

    $response = $this->get(route('attendance.download', ['month' => '2026-08']));

    $response->assertOk()->assertDownload('timesheet-2026-08.xlsx');

    $cells = cells(downloaded($response));

    /** The fifth of August is the fifth date row of the template. */
    expect(round((float) $cells['B15'] * 24 * 60))->toBe(9.0 * 60 + 12)
        ->and(round((float) $cells['C15'] * 24 * 60))->toBe(17.0 * 60 + 3)
        ->and(round((float) $cells['D15'] * 24, 2))->toBe(7.85)
        ->and($cells['E15'])->toBe('P')
        ->and($cells['K15'])->toBe('Sprint planning');
});

test('the signature dates carry the day the sheet was taken', function () {
    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-08']))));

    $today = 'DATE: '.now()->format('d/m/Y');

    expect($cells['B55'])->toBe($today)
        ->and($cells['F55'])->toBe($today)
        ->and($cells['J55'])->toBe($today);
});

test('the signature date follows the download, not the month covered', function () {
    $this->travelTo(Carbon::parse('2026-09-15'));

    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-07']))));

    /** A July sheet taken in September is still signed in September. */
    expect($cells['B55'])->toBe('DATE: 15/09/2026');
});

test('the download keeps the template header and signature block', function () {
    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-08']))));

    expect($cells['A9'])->toBe('DATE')
        ->and($cells['A11'])->toBe('46235')
        ->and($cells['A41'])->toBe('46265')
        ->and($cells)->toHaveKey('B54');
});

test('a day clocked in another month lands in that month\'s sheet', function () {
    logEntry('2026-07-06T09:00:00+00:00', '2026-07-06T17:00:00+00:00', 'P', 'July work');

    $response = $this->get(route('attendance.download', ['month' => '2026-07']));

    $response->assertOk()->assertDownload('timesheet-2026-07.xlsx');

    $cells = cells(downloaded($response));

    /** The sixth of July is the sixth date row of the template. */
    expect(round((float) $cells['B16'] * 24))->toBe(9.0)
        ->and(round((float) $cells['C16'] * 24))->toBe(17.0)
        ->and($cells['E16'])->toBe('P')
        ->and($cells['K16'])->toBe('July work');
});

test('the period heading and date column follow the requested month', function () {
    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-07']))));

    /** The first of July 2026 as an Excel serial. */
    expect((float) $cells['B8'])->toBe(46204.0)
        ->and((float) $cells['A11'])->toBe(46204.0)
        ->and((float) $cells['A41'])->toBe(46234.0);
});

test('another month\'s days stay out of the sheet', function () {
    logEntry('2026-07-06T09:00:00+00:00', '2026-07-06T17:00:00+00:00', 'P', 'July work');

    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-08']))));

    /** Nothing in August was worked, so no row carries hours. */
    expect($cells)->not->toHaveKey('B16')
        ->and($cells)->not->toHaveKey('E16')
        ->and($cells)->not->toHaveKey('K16');
});

test('a thirty day month clears the template spare row', function () {
    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-09']))));

    /** September has 30 days, so the last of the template's 31 rows is blank. */
    expect($cells)->toHaveKey('A40')
        ->and($cells)->not->toHaveKey('A41');
});

test('february clears the template spare rows', function () {
    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-02']))));

    expect($cells)->toHaveKey('A38')
        ->and($cells)->not->toHaveKey('A39')
        ->and($cells)->not->toHaveKey('A40')
        ->and($cells)->not->toHaveKey('A41');
});

test('a spare row does not keep the previous month\'s hours', function () {
    logEntry('2026-08-31T09:00:00+00:00', '2026-08-31T17:00:00+00:00', 'P', 'Last of August');

    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-09']))));

    /** August's 31st filled row 41, which September must leave empty. */
    expect($cells)->not->toHaveKey('B41')
        ->and($cells)->not->toHaveKey('E41')
        ->and($cells)->not->toHaveKey('K41');
});

test('the download month must be well formed', function () {
    $this->get(route('attendance.download', ['month' => 'July 2026']))
        ->assertSessionHasErrors('month');
});

test('the download falls back to the current month', function () {
    $this->get(route('attendance.download'))
        ->assertOk()
        ->assertDownload('timesheet-'.now()->format('Y-m').'.xlsx');
});

test('the download leaves days without attendance empty', function () {
    logEntry('2026-08-05T09:12:00+00:00', '2026-08-05T17:03:00+00:00', 'P', 'Sprint planning');

    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-08']))));

    expect($cells)->not->toHaveKey('B16')
        ->and($cells)->not->toHaveKey('E16');
});

test('the download merges several clock ins on one day into a single row', function () {
    logEntry('2026-08-05T13:00:00+00:00', '2026-08-05T17:00:00+00:00', 'P', 'Afternoon');
    logEntry('2026-08-05T09:00:00+00:00', '2026-08-05T12:00:00+00:00', 'P', 'Morning');

    $cells = cells(downloaded($this->get(route('attendance.download', ['month' => '2026-08']))));

    expect(round((float) $cells['B15'] * 24))->toBe(9.0)
        ->and(round((float) $cells['C15'] * 24))->toBe(17.0)
        ->and(round((float) $cells['D15'] * 24, 2))->toBe(7.0)
        ->and($cells['K15'])->toBe('Morning; Afternoon');
});

test('a downloaded template can be uploaded again', function () {
    logEntry('2026-08-05T09:12:00+00:00', '2026-08-05T17:03:00+00:00', 'P', 'Sprint planning');

    $original = storedEntries();

    $workbook = downloaded($this->get(route('attendance.download', ['month' => '2026-08'])));

    $this->post(route('attendance.upload'), [
        'file' => UploadedFile::fake()->createWithContent('timesheet.xlsx', $workbook),
    ])->assertRedirect(route('attendance.index'));

    $uploaded = storedEntries();

    expect($uploaded)->toHaveCount(1)
        ->and($uploaded[0]['clockedInAt'])->toBe($original[0]['clockedInAt'])
        ->and($uploaded[0]['clockedOutAt'])->toBe($original[0]['clockedOutAt'])
        ->and($uploaded[0]['description'])->toBe($original[0]['description']);
});

function timesheet(string $body): File
{
    return UploadedFile::fake()->createWithContent('attendance.csv', $body);
}

test('uploading a timesheet replaces the stored entries', function () {
    openAttendanceEntry();

    $this->post(route('attendance.upload'), [
        'file' => timesheet(<<<'CSV'
            Date,"Clock In","Clock Out",Duration,Description
            2026-08-04,08:58,16:45,07:47,"Bug triage"
            2026-08-05,09:12,17:03,07:51,"Sprint planning"
            Total,,,15:38,
            CSV),
    ])->assertRedirect(route('attendance.index'));

    $entries = storedEntries();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['description'])->toBe('Sprint planning')
        ->and($entries[0]['clockedInAt'])->toStartWith('2026-08-05T09:12:00')
        ->and($entries[0]['clockedOutAt'])->toStartWith('2026-08-05T17:03:00')
        ->and($entries[1]['description'])->toBe('Bug triage');
});

test('uploading keeps a row without a clock out time open', function () {
    $this->post(route('attendance.upload'), [
        'file' => timesheet("Date,\"Clock In\",\"Clock Out\",Duration,Description\n2026-08-05,09:12,,,Still going"),
    ])->assertRedirect(route('attendance.index'));

    $entries = storedEntries();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['clockedOutAt'])->toBeNull();
});

test('uploading treats an earlier clock out as an overnight shift', function () {
    $this->post(route('attendance.upload'), [
        'file' => timesheet("Date,\"Clock In\",\"Clock Out\",Duration,Description\n2026-08-05,22:00,06:00,08:00,Night shift"),
    ]);

    expect(storedEntries()[0]['clockedOutAt'])->toStartWith('2026-08-06T06:00:00');
});

test('an upload only replaces the signed in user\'s log', function () {
    $other = Attendance::factory()->for(User::factory())->create();

    $this->post(route('attendance.upload'), [
        'file' => timesheet("Date,\"Clock In\",\"Clock Out\",Duration,Description\n2026-08-05,09:12,17:03,07:51,\"Sprint planning\""),
    ])->assertRedirect(route('attendance.index'));

    expect(Attendance::whereKey($other->id)->exists())->toBeTrue();
});

test('a csv timesheet still uploads alongside the template', function () {
    $csv = "Date,\"Clock In\",\"Clock Out\",Duration,Description\n2026-08-05,09:12,17:03,07:51,\"Sprint planning\"\nTotal,,,07:51,";

    $this->post(route('attendance.upload'), ['file' => timesheet($csv)]);

    $uploaded = storedEntries();

    expect($uploaded)->toHaveCount(1)
        ->and($uploaded[0]['clockedInAt'])->toStartWith('2026-08-05T09:12:00')
        ->and($uploaded[0]['clockedOutAt'])->toStartWith('2026-08-05T17:03:00')
        ->and($uploaded[0]['description'])->toBe('Sprint planning');
});

test('uploading rejects a malformed time', function () {
    $this->post(route('attendance.upload'), [
        'file' => timesheet("Date,\"Clock In\",\"Clock Out\",Duration,Description\n05/08/2026,9am,,,Bad row"),
    ])->assertSessionHasErrors('file');
});

test('uploading rejects more than one open entry', function () {
    $this->post(route('attendance.upload'), [
        'file' => timesheet(<<<'CSV'
            Date,"Clock In","Clock Out",Duration,Description
            2026-08-04,08:58,,,First
            2026-08-05,09:12,,,Second
            CSV),
    ])->assertSessionHasErrors('file');
});

test('uploading requires a file', function () {
    $this->post(route('attendance.upload'))->assertSessionHasErrors('file');
});

test('the description is limited to 1000 characters', function () {
    $this->post(route('attendance.store'), [
        'action' => 'clock-in',
        'description' => str_repeat('a', 1001),
    ])->assertSessionHasErrors('description');
});
