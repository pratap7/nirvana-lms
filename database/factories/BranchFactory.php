<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => fake()->city().' Center for Yoga & Philosophy',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'address_line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => 'IN',
            'postal_code' => fake()->postcode(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ];
    }
}
