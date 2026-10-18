<?php

declare(strict_types=1);

namespace Tests\Unit\Certification;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Course;
use App\Domain\Certification\Models\CompletionEvaluation;
use App\Domain\Certification\Services\CompletionEvaluator;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Learning\Models\CourseProgress;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompletionEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected Course $course;

    protected Batch $batch;

    protected CompletionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'Tapovan Rishikesh',
            'code' => 'TAP',
            'city' => 'Rishikesh',
            'state' => 'Uttarakhand',
            'country' => 'IN',
            'postal_code' => '249192',
        ]);

        $this->student = User::create([
            'first_name' => 'Kavita',
            'last_name' => 'Devi',
            'email' => 'kavita@pramana.edu',
            'password' => Hash::make('Secret123!'),
        ]);

        $category = Category::create([
            'name' => 'Vedanta',
            'slug' => 'vedanta',
            'is_active' => true,
        ]);

        $this->course = Course::create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'created_by' => $this->student->id,
            'name' => 'Mandukya Upanishad Intensive',
            'slug' => 'mandukya-upanishad-intensive',
            'default_delivery_mode' => DeliveryMode::Online,
            'type' => CourseType::ShortTerm,
            'difficulty_level' => DifficultyLevel::Advanced,
            'status' => CourseStatus::Published,
            'base_fee_amount' => 500000,
            'base_fee_currency' => 'INR',
        ]);

        $this->batch = Batch::create([
            'course_id' => $this->course->id,
            'branch_id' => $branch->id,
            'name' => 'Cohort One',
            'delivery_mode' => DeliveryMode::Online,
            'status' => BatchStatus::InProgress,
            'capacity' => 20,
            'start_date' => now()->startOfDay(),
            'end_date' => now()->addMonth()->endOfDay(),
        ]);

        $this->evaluator = app(CompletionEvaluator::class);
    }

    public function test_empty_rules_evaluate_to_immediate_pass(): void
    {
        // Workshops and short seminars with empty rules pass automatically
        $evaluation = $this->evaluator->evaluate($this->course, $this->student, $this->batch, []);

        $this->assertTrue($evaluation->is_passed);
        $this->assertIsArray($evaluation->checklist_results);
    }

    public function test_attendance_requirement_fails_when_below_threshold(): void
    {
        // 80% attendance required
        $rules = [
            'min_attendance_percent' => 80,
        ];

        // Student has only 60% attendance
        CourseProgress::create([
            'course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'attendance_percentage' => 60,
            'lessons_completed' => 5,
            'total_required_lessons' => 10,
        ]);

        $evaluation = $this->evaluator->evaluate($this->course, $this->student, $this->batch, $rules);

        $this->assertFalse($evaluation->is_passed);
        $this->assertNotEmpty($evaluation->checklist_results);
        $this->assertFalse($evaluation->checklist_results[0]['passed']);
    }

    public function test_attendance_requirement_passes_when_at_or_above_threshold(): void
    {
        $rules = [
            'min_attendance_percent' => 80,
        ];

        // Student has 85% attendance
        CourseProgress::create([
            'course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'attendance_percentage' => 85,
            'lessons_completed' => 10,
            'total_required_lessons' => 10,
        ]);

        $evaluation = $this->evaluator->evaluate($this->course, $this->student, $this->batch, $rules);

        $this->assertTrue($evaluation->is_passed);
        $this->assertTrue($evaluation->checklist_results[0]['passed']);
    }

    public function test_manual_approval_overrides_incomplete_requirements(): void
    {
        $rules = [
            'requirements' => [
                [
                    'type' => 'attendance',
                    'title' => 'Minimum Attendance Required',
                    'min_percentage' => 90,
                ],
            ],
        ];

        // Pre-create an evaluation with manual approval by Acharya
        CompletionEvaluation::create([
            'course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'batch_id' => $this->batch->id,
            'is_passed' => true,
            'is_manually_approved' => true,
            'requires_manual_approval' => true,
            'rules_snapshot' => $rules,
            'checklist_results' => [],
        ]);

        $evaluation = $this->evaluator->evaluate($this->course, $this->student, $this->batch, $rules);

        // Evaluation passes because manual approval is preserved
        $this->assertTrue($evaluation->is_passed);
        $this->assertTrue($evaluation->is_manually_approved);
    }
}
