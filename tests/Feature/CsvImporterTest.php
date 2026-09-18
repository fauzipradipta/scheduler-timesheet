<?php

use App\Support\Timesheets\CsvImporter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

const HEADER = "Date,Clock In,Clock Out,Duration,Activity / Remark\n";

/**
 * The importer reads a path rather than an upload, so each test writes its rows
 * to a file of their own and the whole batch is swept up afterwards.
 */
function csvFile(string $body): string
{
    $path = sys_get_temp_dir().'/csv-importer-'.Str::uuid().'.csv';

    file_put_contents($path, $body);

    return $path;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/csv-importer-*.csv') ?: [] as $path) {
        unlink($path);
    }
});

/**
 * @return list<array{id: string, clockedInAt: string, clockedOutAt: string|null, status: string, description: string}>
 */
function importing(string $body): array
{
    return (new CsvImporter)->import(csvFile($body));
}

/**
 * The message the importer refused these rows with.
 */
function importError(string $body): string
{
    try {
        importing($body);
    } catch (ValidationException $exception) {
        return (string) $exception->errors()['file'][0];
    }

    throw new RuntimeException('These rows were expected to be refused.');
}

test('a row becomes an entry', function () {
    $entries = importing(HEADER."2026-08-05,09:12,17:03,7:51,Sprint planning\n");

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['clockedInAt'])->toStartWith('2026-08-05T09:12:00')
        ->and($entries[0]['clockedOutAt'])->toStartWith('2026-08-05T17:03:00')
        ->and($entries[0]['status'])->toBe('P')
        ->and($entries[0]['description'])->toBe('Sprint planning');
});

test('the header row is not read as a day', function () {
    expect(importing(HEADER))->toBe([]);
});

test('a byte order mark does not hide the header', function () {
    expect(importing("\xEF\xBB\xBF".HEADER."2026-08-05,09:12,17:03,7:51,Work\n"))->toHaveCount(1);
});

test('the total row and blank lines are skipped', function () {
    $entries = importing(HEADER."2026-08-05,09:12,17:03,7:51,Work\n\nTotal,,,7:51,\n");

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['description'])->toBe('Work');
});

test('a row without a clock out is left open', function () {
    $entries = importing(HEADER."2026-08-05,09:12,,,Still going\n");

    expect($entries[0]['clockedOutAt'])->toBeNull();
});

test('a clock out earlier than the clock in is an overnight shift', function () {
    $entries = importing(HEADER."2026-08-05,22:00,06:00,8:00,Night shift\n");

    expect($entries[0]['clockedOutAt'])->toStartWith('2026-08-06T06:00:00');
});

test('a malformed time is refused, naming the row it sits on', function () {
    expect(importError(HEADER."2026-08-05,9am,17:03,,Work\n"))
        ->toBe('Row 2 needs a date as YYYY-MM-DD and a time as HH:MM.');
});

test('a date the calendar does not have is refused', function () {
    expect(importError(HEADER."2026-02-30,09:12,17:03,,Work\n"))
        ->toBe('Row 2 needs a date as YYYY-MM-DD and a time as HH:MM.');
});

test('a description longer than 1000 characters is refused', function () {
    expect(importError(HEADER.'2026-08-05,09:12,17:03,7:51,'.str_repeat('a', 1001)."\n"))
        ->toBe('Row 2 has a description longer than 1000 characters.');
});

/**
 * The cap counts the days read, so the header the rows carry is not one of them.
 */
function rows(int $count): string
{
    $body = HEADER;

    foreach (range(1, $count) as $number) {
        $body .= "2026-08-05,09:00,10:00,1:00,Row {$number}\n";
    }

    return $body;
}

test('the last allowed day still reads', function () {
    expect(importing(rows(500)))->toHaveCount(500);
});

test('one day past the cap is refused', function () {
    expect(importError(rows(501)))->toBe('The timesheet may not have more than 500 entries.');
});
