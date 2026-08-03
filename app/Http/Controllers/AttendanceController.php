<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    private const SESSION_KEY = 'attendance.entries';

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
     * Open a new entry, newest first.
     *
     * @param  list<array{id: string, clockedInAt: string, clockedOutAt: string|null, description: string}>  $entries
     * @return list<array{id: string, clockedInAt: string, clockedOutAt: string|null, description: string}>
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
            'description' => $description,
        ]);

        return $entries;
    }

    /**
     * Close the open entry, keeping its original description when no new one is written.
     *
     * @param  list<array{id: string, clockedInAt: string, clockedOutAt: string|null, description: string}>  $entries
     * @return list<array{id: string, clockedInAt: string, clockedOutAt: string|null, description: string}>
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
     * @return list<array{id: string, clockedInAt: string, clockedOutAt: string|null, description: string}>
     */
    private function entries(Request $request): array
    {
        return array_values((array) $request->session()->get(self::SESSION_KEY, []));
    }

    /**
     * @param  list<array{id: string, clockedInAt: string, clockedOutAt: string|null, description: string}>  $entries
     * @return array{id: string, clockedInAt: string, clockedOutAt: string|null, description: string}|null
     */
    private function activeEntry(array $entries): ?array
    {
        foreach ($entries as $entry) {
            if ($entry['clockedOutAt'] === null) {
                return $entry;
            }
        }

        return null;
    }
}
