<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $ntt_id
 * @property string|null $project_name
 * @property string|null $division
 * @property string|null $timesheet_template_path
 * @property string|null $timesheet_template_name
 * @property list<array{column: string, label: string}>|null $timesheet_template_columns
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'ntt_id', 'project_name', 'division', 'password'])]
#[Hidden(['password', 'remember_token', 'timesheet_template_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'timesheet_template_columns' => 'array',
        ];
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * The absolute path of the timesheet template this user uploaded, or null
     * when they have none yet or its file has gone missing from the disk.
     */
    public function timesheetTemplatePath(): ?string
    {
        if ($this->timesheet_template_path === null || ! Storage::disk('local')->exists($this->timesheet_template_path)) {
            return null;
        }

        return Storage::disk('local')->path($this->timesheet_template_path);
    }

    /**
     * The columns the user's template adds beyond the fixed layout, which each
     * entry may carry a value for.
     *
     * @return list<array{column: string, label: string}>
     */
    public function timesheetTemplateColumns(): array
    {
        return $this->timesheetTemplatePath() === null ? [] : ($this->timesheet_template_columns ?? []);
    }
}
