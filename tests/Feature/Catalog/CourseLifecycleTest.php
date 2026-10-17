<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateCourse;
use App\Domain\Catalog\Actions\TransitionCourseStatus;
use App\Domain\Catalog\DTOs\CreateCourseData;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Catalog\Events\CourseCreated;
use App\Domain\Catalog\Events\CoursePublished;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Policies\CoursePolicy;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Exceptions\IllegalStateTransitionException;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create();
});

it('creates a course with Draft status and ULID', function (): void {
    Event::fake([CourseCreated::class]);

    $data = new CreateCourseData(
        name: 'Classical Sankhya Karika',
        slug: 'classical-sankhya-karika',
        branchId: $this->branch->id,
        createdBy: $this->user->id,
        shortDescription: 'In-depth study of Ishvarakrishna\'s text.',
        difficultyLevel: DifficultyLevel::Intermediate,
        durationHours: 40,
        baseFeeAmount: 850000, // ₹8,500
    );

    $course = (new CreateCourse)->execute($data);

    expect($course)->toBeInstanceOf(Course::class)
        ->and($course->ulid)->toBeString()->toHaveLength(26)
        ->and($course->status)->toBe(CourseStatus::Draft)
        ->and($course->base_fee_amount)->toBe(850000);

    Event::assertDispatched(CourseCreated::class, fn ($e) => $e->course->id === $course->id);
});

it('follows the strict state machine transition: Draft -> InReview -> Published -> Archived', function (): void {
    Event::fake([CoursePublished::class]);

    $course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->user->id,
        'status' => CourseStatus::Draft,
    ]);

    $transition = new TransitionCourseStatus;

    // 1. Submit for review
    $course = $transition->execute($course, CourseStatus::InReview);
    expect($course->status)->toBe(CourseStatus::InReview);

    // 2. Approve and publish
    $course = $transition->execute($course, CourseStatus::Published, actorId: $this->user->id);
    expect($course->status)->toBe(CourseStatus::Published)
        ->and($course->published_at)->not->toBeNull()
        ->and($course->approved_by)->toBe($this->user->id);

    Event::assertDispatched(CoursePublished::class);

    // 3. Archive
    $course = $transition->execute($course, CourseStatus::Archived);
    expect($course->status)->toBe(CourseStatus::Archived);
});

it('throws IllegalStateTransitionException when attempting illegal jump (Draft -> Published)', function (): void {
    $course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->user->id,
        'status' => CourseStatus::Draft,
    ]);

    expect(fn () => (new TransitionCourseStatus)->execute($course, CourseStatus::Published))
        ->toThrow(IllegalStateTransitionException::class);
});

it('authorizes course publishing using granular permissions without checking role names', function (): void {
    $policy = new CoursePolicy;

    $course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->user->id,
        'status' => CourseStatus::Draft,
    ]);

    $unauthorizedUser = User::factory()->create();
    expect($policy->publish($unauthorizedUser, $course))->toBeFalse();

    // Grant permission directly (or via role)
    $authorizedUser = User::factory()->create();
    $authorizedUser->givePermissionTo('course.publish');

    expect($policy->publish($authorizedUser, $course))->toBeTrue();
});
