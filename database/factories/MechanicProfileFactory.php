<?php

namespace Database\Factories;

use App\Models\MechanicProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MechanicProfile>
 */
class MechanicProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'mechanic']),
            'shop_name' => fake()->company().' Repair',
            'service_address' => fake()->address(),
            'is_verified' => false,
            'is_online' => false,
        ];
    }
}
