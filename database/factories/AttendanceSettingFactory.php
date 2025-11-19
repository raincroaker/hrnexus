<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceSetting>
 */
class AttendanceSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requiredTimeIn = fake()->time('H:i');
        $requiredTimeOut = Carbon::createFromFormat('H:i', $requiredTimeIn)
            ->addHours(9)
            ->format('H:i');

        return [
            'required_time_in' => $requiredTimeIn,
            'required_time_out' => $requiredTimeOut,
            'break_duration_minutes' => fake()->numberBetween(0, 120),
            'break_is_counted' => fake()->boolean(),
        ];
    }
}
