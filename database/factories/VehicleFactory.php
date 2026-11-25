<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'brand' => fake()->company(),
            'model' => fake()->word(),
            'year' => fake()->numberBetween(2000, (int) date('Y')),
            'vehicle_type' => 'Car',
            'vehicle_category' => 'four_wheeled',
        ];
    }
}
