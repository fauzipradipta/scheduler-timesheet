<?php

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->actingAs($this->user);

    Storage::fake('local');

    /** The export shades holidays, and the suite must never call Google for them. */
    Http::fake(['calendar.google.com/*' => Http::response('')]);
});

/**
 * The layout the export fills, as the company template lays it out.
 */
function templateFixture(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/timesheet-template.xlsx'));
}

/**
 * A copy of the fixture with some of its parts rewritten.
 *
 * @param  array<string, Closure(string): string>  $edits  part name => rewrite of that part
 */
function editedTemplate(array $edits): string
{
    $path = tempnam(sys_get_temp_dir(), 'template');
    file_put_contents($path, templateFixture());

    $zip = new ZipArchive;
    $zip->open($path);

    foreach ($edits as $part => $edit) {
        $zip->addFromString($part, $edit((string) $zip->getFromName($part)));
    }

    $zip->close();

    $contents = (string) file_get_contents($path);
    unlink($path);

    return $contents;
}

function uploadTemplate(string $contents, string $name = 'my-template.xlsx'): TestResponse
{
    return test()->post(route('attendance.template.store'), [
        'template' => UploadedFile::fake()->createWithContent($name, $contents),
    ]);
}

/**
 * Read one part of a downloaded workbook.
 */
function downloadedPart(TestResponse $response, string $part): string
{
    $path = tempnam(sys_get_temp_dir(), 'downloaded');
    file_put_contents($path, (string) file_get_contents($response->baseResponse->getFile()->getPathname()));

    $zip = new ZipArchive;
    $zip->open($path);
    $contents = (string) $zip->getFromName($part);
    $zip->close();
    unlink($path);

    return $contents;
}

test('a user starts without a timesheet template', function () {
    $this->get(route('attendance.index'))
        ->assertInertia(fn ($page) => $page->where('timesheetTemplate', null));
});

test('an uploaded template is kept for the user and named on the page', function () {
    uploadTemplate(templateFixture())->assertRedirect(route('attendance.index'));

    $this->user->refresh();

    expect($this->user->timesheet_template_name)->toBe('my-template.xlsx');
    Storage::disk('local')->assertExists($this->user->timesheet_template_path);

    $this->get(route('attendance.index'))
        ->assertInertia(fn ($page) => $page->where('timesheetTemplate', 'my-template.xlsx'));
});

test('uploading again replaces the earlier template', function () {
    uploadTemplate(templateFixture(), 'first.xlsx');
    uploadTemplate(templateFixture(), 'second.xlsx');

    $this->user->refresh();

    expect($this->user->timesheet_template_name)->toBe('second.xlsx');
    Storage::disk('local')->assertCount('timesheet-templates', 1);
});

test('the download is filled into the uploaded template', function () {
    /** A part only this upload carries proves the export copied it rather than some other workbook. */
    uploadTemplate(editedTemplate([
        'docProps/marker.txt' => fn (): string => 'custom template',
    ]));

    $response = $this->get(route('attendance.download', ['month' => '2026-08']));

    $response->assertOk()->assertDownload('timesheet-2026-08.xlsx');

    expect(downloadedPart($response, 'docProps/marker.txt'))->toBe('custom template');
});

test('a timesheet cannot be downloaded before a template is uploaded', function () {
    $this->from(route('attendance.index'))
        ->get(route('attendance.download', ['month' => '2026-08']))
        ->assertRedirect(route('attendance.index'))
        ->assertSessionHasErrors('template');
});

test('a template belongs to the user who uploaded it', function () {
    uploadTemplate(templateFixture());

    $this->actingAs(User::factory()->create())
        ->from(route('attendance.index'))
        ->get(route('attendance.download', ['month' => '2026-08']))
        ->assertSessionHasErrors('template');
});

test('a template must be an xlsx workbook', function () {
    uploadTemplate('Date,Start,End', 'template.csv')->assertSessionHasErrors('template');

    uploadTemplate('not a zip at all')->assertSessionHasErrors('template');

    expect($this->user->refresh()->timesheet_template_path)->toBeNull();
});

test('a template that leaves out a row the export writes to is rejected', function () {
    $withoutSignatures = editedTemplate([
        'xl/worksheets/sheet1.xml' => fn (string $sheet): string => (string) preg_replace('#<row r="55"[^>]*>.*?</row>#s', '', $sheet),
    ]);

    uploadTemplate($withoutSignatures)
        ->assertSessionHasErrors(['template' => 'The template does not follow the timesheet layout: row 55 is missing. Keep the employee details in B4 to B7, the period in B8, one day per row from row 11 to 41, and the signature name and dates in rows 54 and 55.']);

    expect($this->user->refresh()->timesheet_template_path)->toBeNull();
});

test('removing the template forgets it and its file', function () {
    uploadTemplate(templateFixture());
    $path = $this->user->refresh()->timesheet_template_path;

    $this->delete(route('attendance.template.destroy'))->assertRedirect(route('attendance.index'));

    Storage::disk('local')->assertMissing($path);

    expect($this->user->refresh()->timesheet_template_path)->toBeNull()
        ->and($this->user->timesheet_template_name)->toBeNull();
});

test('a template without the weekend grey is given one', function () {
    /** Swap every light grey in the fixture for green, so no fill matches the weekend grey. */
    $green = editedTemplate([
        'xl/styles.xml' => fn (string $styles): string => str_replace(
            '<fgColor theme="0" tint="-0.249977111117893"/>',
            '<fgColor rgb="FF00FF00"/>',
            $styles,
        ),
    ]);

    uploadTemplate($green);

    $response = $this->get(route('attendance.download', ['month' => '2026-08']));

    $styles = new DOMDocument;
    $styles->loadXML(downloadedPart($response, 'xl/styles.xml'));
    $sheet = new DOMDocument;
    $sheet->loadXML(downloadedPart($response, 'xl/worksheets/sheet1.xml'));

    $fills = $styles->getElementsByTagName('fills')->item(0);
    $formats = $styles->getElementsByTagName('cellXfs')->item(0)->getElementsByTagName('xf');

    $style = null;

    foreach ($sheet->getElementsByTagName('c') as $cell) {
        if ($cell->getAttribute('r') === 'B11') {
            $style = (int) $cell->getAttribute('s');
        }
    }

    /** August 2026 opens on a Saturday. */
    $fillId = (int) $formats->item($style)->getAttribute('fillId');
    $color = $fills->getElementsByTagName('fill')->item($fillId)->getElementsByTagName('fgColor')->item(0);

    expect($color->getAttribute('rgb'))->toBe('FFD9D9D9')
        ->and((int) $fills->getAttribute('count'))->toBe($fills->getElementsByTagName('fill')->length);
});

/**
 * The text or number each cell of a downloaded first worksheet holds.
 *
 * @return array<string, string>
 */
function downloadedCells(TestResponse $response): array
{
    $sheet = new DOMDocument;
    $sheet->loadXML(downloadedPart($response, 'xl/worksheets/sheet1.xml'));

    $cells = [];

    foreach ($sheet->getElementsByTagName('c') as $cell) {
        $cells[$cell->getAttribute('r')] = trim($cell->textContent);
    }

    return $cells;
}

/**
 * Put a worked stretch of a day in the signed in user's log.
 *
 * @param  array<string, string>  $extra
 */
function loggedDay(string $clockedInAt, string $clockedOutAt, array $extra): Attendance
{
    return Attendance::factory()->for(test()->user)->create([
        'clocked_in_at' => Carbon::parse($clockedInAt),
        'clocked_out_at' => Carbon::parse($clockedOutAt),
        'status' => 'P',
        'description' => '',
        'extra' => $extra,
    ]);
}

test('an uploaded template remembers the columns it adds after the remark', function () {
    uploadTemplate(templateFixture());

    expect($this->user->refresh()->timesheet_template_columns)->toBe([
        ['column' => 'L', 'label' => 'Project Name'],
        ['column' => 'M', 'label' => 'Project Code'],
        ['column' => 'N', 'label' => 'Application Name'],
    ]);

    $this->get(route('attendance.index'))
        ->assertInertia(fn ($page) => $page->where('templateColumns', ['Project Name', 'Project Code', 'Application Name']));
});

test('a template without added headings offers no extra columns', function () {
    uploadTemplate(editedTemplate([
        'xl/worksheets/sheet1.xml' => fn (string $sheet): string => (string) preg_replace('#<c r="[LMN]9"[^>]*?(/>|>.*?</c>)#s', '', $sheet),
    ]));

    $this->get(route('attendance.index'))
        ->assertInertia(fn ($page) => $page->where('templateColumns', []));
});

test('removing the template removes its extra columns from the page', function () {
    uploadTemplate(templateFixture());

    $this->delete(route('attendance.template.destroy'));

    $this->get(route('attendance.index'))
        ->assertInertia(fn ($page) => $page->where('templateColumns', []));
});

test('clocking in keeps a value for each column the template adds', function () {
    uploadTemplate(templateFixture());

    $this->post(route('attendance.store'), [
        'action' => 'clock-in',
        'extra' => ['Project Name' => ' Digisales ', 'Project Code' => '', 'Not In Template' => 'dropped'],
    ])->assertSessionHasNoErrors();

    expect($this->user->attendances()->sole()->extra)->toBe(['Project Name' => 'Digisales']);
});

test('clocking out keeps the values given on the way in unless new ones are written', function () {
    uploadTemplate(templateFixture());

    $this->post(route('attendance.store'), [
        'action' => 'clock-in',
        'extra' => ['Project Name' => 'Digisales', 'Project Code' => 'DS-1'],
    ]);

    $this->post(route('attendance.store'), [
        'action' => 'clock-out',
        'extra' => ['Project Code' => 'DS-2', 'Application Name' => ''],
    ])->assertSessionHasNoErrors();

    expect($this->user->attendances()->sole()->extra)->toBe(['Project Name' => 'Digisales', 'Project Code' => 'DS-2']);
});

test('a day picked from the calendar keeps its extra column values', function () {
    uploadTemplate(templateFixture());

    $this->post(route('attendance.entry.store'), [
        'date' => '2026-08-05',
        'status' => 'P',
        'startedAt' => '09:00',
        'endedAt' => '17:00',
        'extra' => ['Application Name' => 'Mobile app'],
    ])->assertSessionHasNoErrors();

    expect($this->user->attendances()->sole()->extra)->toBe(['Application Name' => 'Mobile app']);
});

test('an extra column value is limited in length', function () {
    uploadTemplate(templateFixture());

    $this->post(route('attendance.store'), [
        'action' => 'clock-in',
        'extra' => ['Project Name' => str_repeat('a', 256)],
    ])->assertSessionHasErrors('extra.Project Name');
});

test('the download writes each extra column into the day row', function () {
    uploadTemplate(templateFixture());

    loggedDay('2026-08-05T09:00:00+00:00', '2026-08-05T12:00:00+00:00', ['Project Name' => 'Digisales', 'Project Code' => 'DS-1']);
    loggedDay('2026-08-05T13:00:00+00:00', '2026-08-05T17:00:00+00:00', ['Project Name' => 'Digisales', 'Project Code' => 'DS-2', 'Application Name' => 'Portal']);

    $cells = downloadedCells($this->get(route('attendance.download', ['month' => '2026-08'])));

    /** The fifth of August is the fifth date row; a day's distinct values are joined like its remarks. */
    expect($cells['L15'])->toBe('Digisales')
        ->and($cells['M15'])->toBe('DS-1; DS-2')
        ->and($cells['N15'])->toBe('Portal');
});

test('a spare row of a short month has its extra columns cleared', function () {
    uploadTemplate(editedTemplate([
        'xl/worksheets/sheet1.xml' => fn (string $sheet): string => (string) preg_replace(
            '#<c r="L41"([^>]*?)(/>|>.*?</c>)#s',
            '<c r="L41"$1 t="inlineStr"><is><t>leftover</t></is></c>',
            $sheet,
        ),
    ]));

    $cells = downloadedCells($this->get(route('attendance.download', ['month' => '2026-09'])));

    expect($cells['L41'] ?? '')->toBe('');
});

test('the download writes the profile into the header and the signature', function () {
    $this->user->update([
        'name' => 'Ada Lovelace',
        'ntt_id' => 'NTT000001',
        'project_name' => 'Digisales',
        'division' => 'RDL',
    ]);

    uploadTemplate(templateFixture());

    $cells = downloadedCells($this->get(route('attendance.download', ['month' => '2026-08'])));

    expect($cells['B4'])->toBe(': Digisales')
        ->and($cells['B5'])->toBe(': RDL')
        ->and($cells['B6'])->toBe(': Ada Lovelace')
        ->and($cells['B7'])->toBe(': NTT000001')
        ->and($cells['B54'])->toBe('(Ada Lovelace)');
});

test('extra column values round trip through a downloaded timesheet', function () {
    uploadTemplate(templateFixture());

    loggedDay('2026-08-05T09:00:00+00:00', '2026-08-05T17:00:00+00:00', ['Project Name' => 'Digisales', 'Application Name' => 'Portal']);

    $response = $this->get(route('attendance.download', ['month' => '2026-08']));
    $workbook = (string) file_get_contents($response->baseResponse->getFile()->getPathname());

    $this->post(route('attendance.upload'), [
        'file' => UploadedFile::fake()->createWithContent('timesheet.xlsx', $workbook),
    ])->assertSessionHasNoErrors();

    expect($this->user->attendances()->sole()->extra)->toBe(['Project Name' => 'Digisales', 'Application Name' => 'Portal']);
});
