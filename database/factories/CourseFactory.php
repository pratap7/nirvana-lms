<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Foundations of Hatha Yoga & Pranayama',
            'Samkhya & Yoga Philosophy: Classical Texts',
            'Ayurvedic Nutrition and Sattvic Dietetics',
            'Upanishadic Psychology & Mind Exploration',
            'Patanjali Yoga Sutras In-Depth Study',
            'Introduction to Sanskrit for Yoga Practitioners',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'branch_id' => Branch::factory(),
            'created_by' => User::factory(),
            'short_description' => fake()->sentence(),
            'long_description' => fake()->paragraphs(3, true),
            'difficulty_level' => DifficultyLevel::Beginner,
            'duration_hours' => fake()->numberBetween(20, 200),
            'max_students' => 30,
            'base_fee_amount' => 1500000, // ₹15,000 in paise
            'base_fee_currency' => 'INR',
            'status' => CourseStatus::Draft,
            'sort_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => CourseStatus::Published,
            'published_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn () => [
            'status' => CourseStatus::InReview,
        ]);
    }
}
