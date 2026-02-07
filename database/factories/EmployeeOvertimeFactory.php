<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeOvertime>
 */
class EmployeeOvertimeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => 1,
            'date' => fake()->dateTimeBetween('-1 month', 'now'),
            'approved_by' => null,
        ];
    }

    public function approved(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_by' => $user?->id ?? User::factory()->create()->id,
        ]);
    }
}
