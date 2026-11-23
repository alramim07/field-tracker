<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Area> */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'name' => fake()->unique()->streetName(),
            'code' => strtoupper(fake()->unique()->lexify('AREA-???')),
            'status' => 'active',
        ];
    }
}
