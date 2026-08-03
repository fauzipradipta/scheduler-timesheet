<?php

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

test('the description is limited to 1000 characters', function () {
    $this->post(route('attendance.store'), [
        'action' => 'clock-in',
        'description' => str_repeat('a', 1001),
    ])->assertSessionHasErrors('description');
});
