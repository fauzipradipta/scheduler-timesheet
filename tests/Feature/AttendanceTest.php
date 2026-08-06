<?php

use App\Models\User;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/** The attendance routes now sit behind the auth middleware. */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * @return list<array{id: string, clockedInAt: string, clockedOutAt: string|null, description: string}>
 */
function openAttendanceEntry(string $description = 'Started the shift'): array
{
    return [[
        'id' => 'entry-1',
        'clockedInAt' => now()->subHour()->toIso8601String(),
        'clockedOutAt' => null,
        'description' => $description,
    ]];
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
    $this->withSession(['attendance.entries' => openAttendanceEntry()])
        ->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries', 1)
            ->where('activeEntry.id', 'entry-1')
            ->where('activeEntry.description', 'Started the shift')
            ->where('activeEntry.clockedOutAt', null)
        );
});

test('clocking in opens an entry with a description', function () {
    $this->post(route('attendance.store'), [
        'action' => 'clock-in',
        'description' => 'Started the shift',
    ])
        ->assertRedirect(route('attendance.index'))
        ->assertSessionHas('attendance.entries', fn (array $entries) => count($entries) === 1
            && $entries[0]['description'] === 'Started the shift'
            && $entries[0]['clockedOutAt'] === null
        );
});

test('clocking out closes the open entry', function () {
    $this->withSession(['attendance.entries' => openAttendanceEntry()])
        ->post(route('attendance.store'), [
            'action' => 'clock-out',
            'description' => 'Wrapped up the report',
        ])
        ->assertRedirect(route('attendance.index'))
        ->assertSessionHas('attendance.entries', fn (array $entries) => count($entries) === 1
            && $entries[0]['description'] === 'Wrapped up the report'
            && $entries[0]['clockedOutAt'] !== null
        );
});

test('clocking out keeps the existing description when none is written', function () {
    $this->withSession(['attendance.entries' => openAttendanceEntry()])
        ->post(route('attendance.store'), ['action' => 'clock-out'])
        ->assertRedirect(route('attendance.index'))
        ->assertSessionHas('attendance.entries', fn (array $entries) => $entries[0]['description'] === 'Started the shift');
});

test('a user cannot clock in twice', function () {
    $this->withSession(['attendance.entries' => openAttendanceEntry()])
        ->post(route('attendance.store'), ['action' => 'clock-in'])
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

test('a day picked from the calendar is added to the log', function () {
    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-12',
        'status' => 'P',
        'startedAt' => '08:30',
        'endedAt' => '16:45',
        'description' => 'Release prep',
    ])
        ->assertRedirect(route('attendance.index'))
        ->assertSessionHas('attendance.entries', fn (array $entries) => count($entries) === 1
            && str_starts_with($entries[0]['clockedInAt'], '2026-08-12T08:30:00')
            && str_starts_with($entries[0]['clockedOutAt'], '2026-08-12T16:45:00')
            && $entries[0]['description'] === 'Release prep'
        );
});

test('the log stays newest first when an older day is added', function () {
    $this->withSession(['attendance.entries' => [[
        'id' => 'entry-1',
        'clockedInAt' => '2026-08-20T09:00:00+00:00',
        'clockedOutAt' => '2026-08-20T17:00:00+00:00',
        'description' => 'Later day',
    ]]])
        ->post(route('attendance.entry.store'), [
            'date' => '2026-08-03',
            'status' => 'P',
            'startedAt' => '09:00',
            'endedAt' => '17:00',
        ])
        ->assertSessionHas('attendance.entries', fn (array $entries) => count($entries) === 2
            && $entries[0]['description'] === 'Later day'
        );
});

test('a calendar day that ends before it starts runs past midnight', function () {
    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-12',
        'status' => 'P',
        'startedAt' => '22:00',
        'endedAt' => '06:00',
    ])->assertSessionHas('attendance.entries', fn (array $entries) => str_starts_with($entries[0]['clockedOutAt'], '2026-08-13T06:00:00'));
});

test('a calendar day may be left running when nothing else is open', function () {
    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-12',
        'status' => 'P',
        'startedAt' => '09:00',
    ])->assertSessionHas('attendance.entries', fn (array $entries) => $entries[0]['clockedOutAt'] === null);
});

test('a second running day is rejected', function () {
    $this->withSession(['attendance.entries' => openAttendanceEntry()])
        ->post(route('attendance.entry.store'), [
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
    ])
        ->assertRedirect(route('attendance.index'))
        ->assertSessionHas('attendance.entries', fn (array $entries) => count($entries) === 1
            && $entries[0]['status'] === 'S'
            && $entries[0]['clockedOutAt'] === null
            && str_starts_with($entries[0]['clockedInAt'], '2026-08-12T00:00:00')
        );
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
    $cells = cells(downloaded($this->withSession(['attendance.entries' => [
        [
            'id' => 'entry-1',
            'clockedInAt' => '2026-08-05T09:00:00+00:00',
            'clockedOutAt' => '2026-08-05T17:00:00+00:00',
            'status' => 'P',
            'description' => 'Worked',
        ],
        [
            'id' => 'entry-2',
            'clockedInAt' => '2026-08-06T00:00:00+00:00',
            'clockedOutAt' => null,
            'status' => 'S',
            'description' => 'Flu',
        ],
        [
            'id' => 'entry-3',
            'clockedInAt' => '2026-08-07T00:00:00+00:00',
            'clockedOutAt' => null,
            'status' => 'BT',
            'description' => 'Client visit',
        ],
    ]])->get(route('attendance.download'))));

    /** Present lands in E, sick in F, business trip in G. */
    expect($cells['E15'])->toBe('P')
        ->and($cells['F16'])->toBe('S')
        ->and($cells['G17'])->toBe('BT')
        ->and($cells)->not->toHaveKey('E16')
        ->and($cells)->not->toHaveKey('B16')
        ->and($cells['K16'])->toBe('Flu');
});

test('a day off round trips through the template', function () {
    $workbook = downloaded($this->withSession(['attendance.entries' => [[
        'id' => 'entry-1',
        'clockedInAt' => '2026-08-06T00:00:00+00:00',
        'clockedOutAt' => null,
        'status' => 'V',
        'description' => 'Annual leave',
    ]]])->get(route('attendance.download')));

    $this->post(route('attendance.upload'), [
        'file' => UploadedFile::fake()->createWithContent('timesheet.xlsx', $workbook),
    ])->assertSessionHas('attendance.entries', fn (array $entries) => count($entries) === 1
        && $entries[0]['status'] === 'V'
        && $entries[0]['description'] === 'Annual leave'
        && $entries[0]['clockedOutAt'] === null
    );
});

test('a day can be removed from the calendar', function () {
    $this->withSession(['attendance.entries' => openAttendanceEntry()])
        ->delete(route('attendance.entry.destroy', 'entry-1'))
        ->assertRedirect(route('attendance.index'))
        ->assertSessionHas('attendance.entries', []);
});

test('removing an unknown day leaves the log alone', function () {
    $this->withSession(['attendance.entries' => openAttendanceEntry()])
        ->delete(route('attendance.entry.destroy', 'missing'))
        ->assertSessionHas('attendance.entries', fn (array $entries) => count($entries) === 1);
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

test('the download fills the template row that matches the day', function () {
    $response = $this->withSession(['attendance.entries' => [[
        'id' => 'entry-1',
        'clockedInAt' => '2026-08-05T09:12:00+00:00',
        'clockedOutAt' => '2026-08-05T17:03:00+00:00',
        'description' => 'Sprint planning',
    ]]])->get(route('attendance.download'));

    $response->assertOk()->assertDownload('timesheet-'.now()->format('Y-m').'.xlsx');

    $cells = cells(downloaded($response));

    /** The fifth of August is the fifth date row of the template. */
    expect(round((float) $cells['B15'] * 24 * 60))->toBe(9.0 * 60 + 12)
        ->and(round((float) $cells['C15'] * 24 * 60))->toBe(17.0 * 60 + 3)
        ->and(round((float) $cells['D15'] * 24, 2))->toBe(7.85)
        ->and($cells['E15'])->toBe('P')
        ->and($cells['K15'])->toBe('Sprint planning');
});

test('the download keeps the template header and signature block', function () {
    $cells = cells(downloaded($this->get(route('attendance.download'))));

    expect($cells['A9'])->toBe('DATE')
        ->and($cells['A11'])->toBe('46235')
        ->and($cells['A41'])->toBe('46265')
        ->and($cells)->toHaveKey('B54');
});

test('the download leaves days without attendance empty', function () {
    $cells = cells(downloaded($this->withSession(['attendance.entries' => [[
        'id' => 'entry-1',
        'clockedInAt' => '2026-08-05T09:12:00+00:00',
        'clockedOutAt' => '2026-08-05T17:03:00+00:00',
        'description' => 'Sprint planning',
    ]]])->get(route('attendance.download'))));

    expect($cells)->not->toHaveKey('B16')
        ->and($cells)->not->toHaveKey('E16');
});

test('the download merges several clock ins on one day into a single row', function () {
    $cells = cells(downloaded($this->withSession(['attendance.entries' => [
        [
            'id' => 'entry-1',
            'clockedInAt' => '2026-08-05T13:00:00+00:00',
            'clockedOutAt' => '2026-08-05T17:00:00+00:00',
            'description' => 'Afternoon',
        ],
        [
            'id' => 'entry-2',
            'clockedInAt' => '2026-08-05T09:00:00+00:00',
            'clockedOutAt' => '2026-08-05T12:00:00+00:00',
            'description' => 'Morning',
        ],
    ]])->get(route('attendance.download'))));

    expect(round((float) $cells['B15'] * 24))->toBe(9.0)
        ->and(round((float) $cells['C15'] * 24))->toBe(17.0)
        ->and(round((float) $cells['D15'] * 24, 2))->toBe(7.0)
        ->and($cells['K15'])->toBe('Morning; Afternoon');
});

test('a downloaded template can be uploaded again', function () {
    $entries = [[
        'id' => 'entry-1',
        'clockedInAt' => '2026-08-05T09:12:00+00:00',
        'clockedOutAt' => '2026-08-05T17:03:00+00:00',
        'description' => 'Sprint planning',
    ]];

    $workbook = downloaded($this->withSession(['attendance.entries' => $entries])
        ->get(route('attendance.download')));

    $this->post(route('attendance.upload'), [
        'file' => UploadedFile::fake()->createWithContent('timesheet.xlsx', $workbook),
    ])
        ->assertRedirect(route('attendance.index'))
        ->assertSessionHas('attendance.entries', fn (array $uploaded) => count($uploaded) === 1
            && $uploaded[0]['clockedInAt'] === $entries[0]['clockedInAt']
            && $uploaded[0]['clockedOutAt'] === $entries[0]['clockedOutAt']
            && $uploaded[0]['description'] === $entries[0]['description']
        );
});

function timesheet(string $body): File
{
    return UploadedFile::fake()->createWithContent('attendance.csv', $body);
}

test('uploading a timesheet replaces the stored entries', function () {
    $this->withSession(['attendance.entries' => openAttendanceEntry()])
        ->post(route('attendance.upload'), [
            'file' => timesheet(<<<'CSV'
                Date,"Clock In","Clock Out",Duration,Description
                2026-08-04,08:58,16:45,07:47,"Bug triage"
                2026-08-05,09:12,17:03,07:51,"Sprint planning"
                Total,,,15:38,
                CSV),
        ])
        ->assertRedirect(route('attendance.index'))
        ->assertSessionHas('attendance.entries', function (array $entries) {
            return count($entries) === 2
                && $entries[0]['description'] === 'Sprint planning'
                && str_starts_with($entries[0]['clockedInAt'], '2026-08-05T09:12:00')
                && str_starts_with($entries[0]['clockedOutAt'], '2026-08-05T17:03:00')
                && $entries[1]['description'] === 'Bug triage';
        });
});

test('uploading keeps a row without a clock out time open', function () {
    $this->post(route('attendance.upload'), [
        'file' => timesheet("Date,\"Clock In\",\"Clock Out\",Duration,Description\n2026-08-05,09:12,,,Still going"),
    ])
        ->assertRedirect(route('attendance.index'))
        ->assertSessionHas('attendance.entries', fn (array $entries) => count($entries) === 1
            && $entries[0]['clockedOutAt'] === null
        );
});

test('uploading treats an earlier clock out as an overnight shift', function () {
    $this->post(route('attendance.upload'), [
        'file' => timesheet("Date,\"Clock In\",\"Clock Out\",Duration,Description\n2026-08-05,22:00,06:00,08:00,Night shift"),
    ])->assertSessionHas('attendance.entries', fn (array $entries) => str_starts_with($entries[0]['clockedOutAt'], '2026-08-06T06:00:00'));
});

test('a csv timesheet still uploads alongside the template', function () {
    $csv = "Date,\"Clock In\",\"Clock Out\",Duration,Description\n2026-08-05,09:12,17:03,07:51,\"Sprint planning\"\nTotal,,,07:51,";

    $this->post(route('attendance.upload'), ['file' => timesheet($csv)])
        ->assertSessionHas('attendance.entries', fn (array $uploaded) => count($uploaded) === 1
            && str_starts_with($uploaded[0]['clockedInAt'], '2026-08-05T09:12:00')
            && str_starts_with($uploaded[0]['clockedOutAt'], '2026-08-05T17:03:00')
            && $uploaded[0]['description'] === 'Sprint planning'
        );
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
