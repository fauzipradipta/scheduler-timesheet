<?php

namespace App\Models;

use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $clocked_in_at
 * @property Carbon|null $clocked_out_at
 * @property string $status
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @phpstan-type Entry array{id: string, clockedInAt: string, clockedOutAt: string|null, status: string, description: string}
 */
#[Fillable(['clocked_in_at', 'clocked_out_at', 'status', 'description'])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clocked_in_at' => 'datetime',
            'clocked_out_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Present the row in the shape the attendance page and the timesheet
     * writer already speak, so neither has to know a database exists.
     *
     * @return Entry
     */
    public function toEntry(): array
    {
        return [
            'id' => (string) $this->id,
            'clockedInAt' => $this->clocked_in_at->toIso8601String(),
            'clockedOutAt' => $this->clocked_out_at?->toIso8601String(),
            'status' => $this->status,
            'description' => $this->description ?? '',
        ];
    }
}
