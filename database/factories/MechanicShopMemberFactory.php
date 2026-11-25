<?php

namespace Database\Factories;

use App\Models\MechanicShopMember;
use App\Models\MechanicProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MechanicShopMember>
 */
class MechanicShopMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mechanic_profile_id' => MechanicProfile::factory(),
            'name' => fake()->name(),
            'is_active' => true,
        ];
    }
}
