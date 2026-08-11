<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clockedInAt = Carbon::parse(fake()->dateTimeThisMonth())->setTime(fake()->numberBetween(6, 10), 0);

        return [
            'user_id' => User::factory(),
            'clocked_in_at' => $clockedInAt,
            'clocked_out_at' => $clockedInAt->copy()->addHours(fake()->numberBetween(4, 9)),
            'status' => 'P',
            'description' => fake()->sentence(),
        ];
    }

    /**
     * A shift that has been clocked into but not yet closed.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'clocked_out_at' => null,
        ]);
    }

    /**
     * A day marked with a non-present status, which carries no hours.
     */
    public function status(string $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'clocked_in_at' => Carbon::parse($attributes['clocked_in_at'])->startOfDay(),
            'clocked_out_at' => null,
        ]);
    }
}
