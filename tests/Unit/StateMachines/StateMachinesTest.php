<?php

declare(strict_types=1);

namespace Tests\Unit\StateMachines;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Course;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Exceptions\IllegalStateTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StateMachinesTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected Course $course;

    protected Batch $batch;

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
            'first_name' => 'Meera',
            'last_name' => 'Bai',
            'email' => 'meera@pramana.edu',
            'password' => Hash::make('Secret123!'),
        ]);

        $category = Category::create([
            'name' => 'Pranayama',
            'slug' => 'pranayama',
            'is_active' => true,
        ]);

        $this->course = Course::create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'created_by' => $this->student->id,
            'name' => 'Kundalini & Pranayama Mastery',
            'slug' => 'kundalini-pranayama-mastery',
            'default_delivery_mode' => DeliveryMode::Online,
            'type' => CourseType::ShortTerm,
            'difficulty_level' => DifficultyLevel::Intermediate,
            'status' => CourseStatus::Draft,
            'base_fee_amount' => 1000000,
            'base_fee_currency' => 'INR',
        ]);

        $this->batch = Batch::create([
            'course_id' => $this->course->id,
            'branch_id' => $branch->id,
            'name' => 'Winter Cohort',
            'delivery_mode' => DeliveryMode::Online,
            'status' => BatchStatus::InProgress,
            'capacity' => 25,
            'start_date' => now()->startOfDay(),
            'end_date' => now()->addMonth()->endOfDay(),
        ]);
    }

    public function test_course_status_state_machine_valid_transitions(): void
    {
        // Draft -> InReview
        $this->course->transitionTo(CourseStatus::InReview);
        $this->assertSame(CourseStatus::InReview, $this->course->status);

        // InReview -> Published
        $this->course->transitionTo(CourseStatus::Published);
        $this->assertSame(CourseStatus::Published, $this->course->status);

        // Published -> Archived
        $this->course->transitionTo(CourseStatus::Archived);
        $this->assertSame(CourseStatus::Archived, $this->course->status);
    }

    public function test_course_status_state_machine_illegal_transition_throws_exception(): void
    {
        $this->assertSame(CourseStatus::Draft, $this->course->status);

        // Draft cannot directly transition to Published or Archived without InReview
        $this->expectException(IllegalStateTransitionException::class);
        $this->course->transitionTo(CourseStatus::Published);
    }

    public function test_enrollment_status_state_machine_valid_transitions(): void
    {
        $enrollment = Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'status' => EnrollmentStatus::Approved,
        ]);

        // Approved -> PaymentPending
        $enrollment->transitionTo(EnrollmentStatus::PaymentPending);
        $this->assertSame(EnrollmentStatus::PaymentPending, $enrollment->status);

        // PaymentPending -> Active
        $enrollment->transitionTo(EnrollmentStatus::Active);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);

        // Active -> Completed
        $enrollment->transitionTo(EnrollmentStatus::Completed);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);
    }

    public function test_enrollment_status_state_machine_illegal_transition_throws_exception(): void
    {
        $enrollment = Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'status' => EnrollmentStatus::Cancelled,
        ]);

        // Cancelled enrollment cannot transition directly back to Active or Completed
        $this->expectException(IllegalStateTransitionException::class);
        $enrollment->transitionTo(EnrollmentStatus::Active);
    }
}
