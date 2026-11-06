<?php

namespace App\Http\Controllers;

use App\Rules\FollowsTimesheetLayout;
use App\Support\Timesheets\TemplateColumns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Each user keeps one timesheet template of their own, which every export
 * they take is filled into. The headings it adds beyond the fixed layout are
 * remembered, so the page can offer a field for each of them.
 */
class TimesheetTemplateController extends Controller
{
    /**
     * Folder on the local disk that holds every user's template.
     */
    private const DIRECTORY = 'timesheet-templates';

    /**
     * Keep the uploaded workbook as the user's template, replacing any earlier one.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'template' => ['required', 'file', 'extensions:xlsx', 'max:2048', new FollowsTimesheetLayout],
        ]);

        $user = $request->user();
        $file = $request->file('template');

        $columns = TemplateColumns::read((string) $file->getRealPath());

        /** One file per user, so a replacement simply overwrites the last one. */
        $path = $file->storeAs(self::DIRECTORY, $user->id.'.xlsx', 'local');

        if ($path === false) {
            throw ValidationException::withMessages([
                'template' => 'The template could not be saved. Please try again.',
            ]);
        }

        $user->forceFill([
            'timesheet_template_path' => $path,
            'timesheet_template_name' => $file->getClientOriginalName(),
            'timesheet_template_columns' => $columns,
        ])->save();

        return to_route('attendance.index');
    }

    /**
     * Forget the user's template. Exports stay unavailable until another is uploaded.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->timesheet_template_path !== null) {
            Storage::disk('local')->delete($user->timesheet_template_path);
        }

        $user->forceFill([
            'timesheet_template_path' => null,
            'timesheet_template_name' => null,
            'timesheet_template_columns' => null,
        ])->save();

        return to_route('attendance.index');
    }
}
