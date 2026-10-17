<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\BulkPublishCourses;
use App\Domain\Catalog\Actions\CalculateCourseProgress;
use App\Domain\Catalog\Actions\CancelCourse;
use App\Domain\Catalog\Actions\DuplicateCourse;
use App\Domain\Catalog\Actions\ModerateCourseReview;
use App\Domain\Catalog\Actions\SubmitCourseReview;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\LessonContentType;
use App\Domain\Catalog\Enums\ResourceType;
use App\Domain\Catalog\Enums\ReviewStatus;
use App\Domain\Catalog\Events\CourseCancelled;
use App\Domain\Catalog\Events\CoursePublished;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\CourseReview;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\LessonResource;
use App\Domain\Catalog\Models\Module;
use App\Domain\Catalog\Services\Video\BunnyStreamVideoProvider;
use App\Domain\Catalog\Services\Video\VideoProvider;
use App\Domain\Catalog\ValueObjects\CompletionRule;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Support\Money;
use Database\Seeders\BranchSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->branch = Branch::first();
    $this->admin = User::factory()->create(['primary_branch_id' => $this->branch->id]);
    $this->teacher = User::factory()->create(['primary_branch_id' => $this->branch->id]);
    $this->teacher->assignRole(Role::findByName('teacher'));
    $this->student = User::factory()->create(['primary_branch_id' => $this->branch->id]);
    $this->student->assignRole(Role::findByName('student'));
});

it('proves published courses appear when published, and draft courses are invisible to students', function (): void {
    $publishedCourse = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'status' => CourseStatus::Published,
    ]);

    $draftCourse = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'status' => CourseStatus::Draft,
    ]);

    // Student can view published course
    expect(Gate::forUser($this->student)->allows('view', $publishedCourse))->toBeTrue();

    // Student CANNOT view draft course
    expect(Gate::forUser($this->student)->allows('view', $draftCourse))->toBeFalse();

    // Teacher who created the course CAN view their draft
    expect(Gate::forUser($this->teacher)->allows('view', $draftCourse))->toBeTrue();

    // Published scope only returns published courses
    $publishedCourses = Course::published()->get();
    expect($publishedCourses->pluck('id'))->toContain($publishedCourse->id)
        ->and($publishedCourses->pluck('id'))->not->toContain($draftCourse->id);
});

it('calculates progress only over published, required lessons and ignores soft-deleted lessons', function (): void {
    $course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
    ]);

    $module = Module::create([
        'course_id' => $course->id,
        'name' => 'Module 1: Foundations',
        'sort_order' => 1,
    ]);

    // Lesson 1: Published & Required
    $lesson1 = Lesson::create([
        'module_id' => $module->id,
        'title' => 'Patanjali Yoga Sutra 1.1',
        'slug' => 'pys-1-1',
        'content_type' => LessonContentType::Video,
        'is_published' => true,
        'is_mandatory' => true,
        'sort_order' => 1,
    ]);

    // Lesson 2: Published & Required
    $lesson2 = Lesson::create([
        'module_id' => $module->id,
        'title' => 'Patanjali Yoga Sutra 1.2',
        'slug' => 'pys-1-2',
        'content_type' => LessonContentType::Video,
        'is_published' => true,
        'is_mandatory' => true,
        'sort_order' => 2,
    ]);

    // Lesson 3: Published & Optional Preview (is_mandatory = false)
    $lesson3 = Lesson::create([
        'module_id' => $module->id,
        'title' => 'Introductory Chant (Optional)',
        'slug' => 'intro-chant',
        'content_type' => LessonContentType::Audio,
        'is_published' => true,
        'is_mandatory' => false,
        'sort_order' => 3,
    ]);

    // Lesson 4: Unpublished draft lesson (is_published = false)
    $lesson4 = Lesson::create([
        'module_id' => $module->id,
        'title' => 'Unpublished Discussion',
        'slug' => 'draft-discussion',
        'content_type' => LessonContentType::Text,
        'is_published' => false,
        'is_mandatory' => true,
        'sort_order' => 4,
    ]);

    // Lesson 5: Soft-deleted lesson
    $lesson5 = Lesson::create([
        'module_id' => $module->id,
        'title' => 'Deprecated Lesson',
        'slug' => 'deprecated-lesson',
        'content_type' => LessonContentType::Video,
        'is_published' => true,
        'is_mandatory' => true,
        'sort_order' => 5,
    ]);
    $lesson5->delete(); // Soft delete

    // Only Lesson 1 and Lesson 2 are active, published, mandatory lessons (Total: 2)
    $progressAction = new CalculateCourseProgress;

    // 0 completed -> 0%
    expect($progressAction->execute($course, []))->toBe(0.0);

    // 1 completed (Lesson 1) -> 50%
    expect($progressAction->execute($course, [$lesson1->id]))->toBe(50.0);

    // Both required completed -> 100%
    expect($progressAction->execute($course, [$lesson1->id, $lesson2->id]))->toBe(100.0);

    // Completing optional or unpublished lessons does not inflate calculation
    expect($progressAction->execute($course, [$lesson1->id, $lesson3->id, $lesson4->id]))->toBe(50.0);
});

it('proves that reordering lessons does not change completion percentage unexpectedly', function (): void {
    $course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
    ]);

    $module = Module::create([
        'course_id' => $course->id,
        'name' => 'Asana Practice',
        'sort_order' => 1,
    ]);

    $lessonA = Lesson::create([
        'module_id' => $module->id,
        'title' => 'Surya Namaskar A',
        'slug' => 'surya-a',
        'content_type' => LessonContentType::Video,
        'is_published' => true,
        'is_mandatory' => true,
        'sort_order' => 1,
    ]);

    $lessonB = Lesson::create([
        'module_id' => $module->id,
        'title' => 'Surya Namaskar B',
        'slug' => 'surya-b',
        'content_type' => LessonContentType::Video,
        'is_published' => true,
        'is_mandatory' => true,
        'sort_order' => 2,
    ]);

    $completedIds = [$lessonA->id];
    $action = new CalculateCourseProgress;

    $initialProgress = $action->execute($course, $completedIds);
    expect($initialProgress)->toBe(50.0);

    // Reorder: Swap sort_order
    $lessonA->update(['sort_order' => 2]);
    $lessonB->update(['sort_order' => 1]);

    // Progress remains exactly 50% after reorder
    $reorderedProgress = $action->execute($course, $completedIds);
    expect($reorderedProgress)->toBe(50.0);
});

it('supports nested categories hierarchy', function (): void {
    $parentYoga = Category::create([
        'name' => 'Yoga',
        'slug' => 'yoga',
        'sort_order' => 1,
    ]);

    $subHatha = Category::create([
        'parent_id' => $parentYoga->id,
        'name' => 'Hatha Yoga',
        'slug' => 'hatha-yoga',
        'sort_order' => 1,
    ]);

    expect($subHatha->parent_id)->toBe($parentYoga->id)
        ->and($subHatha->full_path)->toBe('Yoga > Hatha Yoga')
        ->and($parentYoga->children)->toHaveCount(1);
});

it('validates and evaluates CompletionRule value objects', function (): void {
    $rule = new CompletionRule(
        minAttendancePercent: 85,
        minScorePercent: 75,
        requiredLessons: ['10', '20'],
        assignmentsRequired: 2,
        manualApproval: true,
    );

    expect($rule->minAttendancePercent)->toBe(85)
        ->and($rule->minScorePercent)->toBe(75)
        ->and($rule->assignmentsRequired)->toBe(2)
        ->and($rule->manualApproval)->toBeTrue();

    // Satisfied scenario
    $isPass = $rule->isSatisfiedBy(
        attendancePercent: 90,
        scorePercent: 80,
        completedLessonIds: ['10', '20', '30'],
        completedAssignments: 2,
        isManuallyApproved: true,
    );
    expect($isPass)->toBeTrue();

    // Failed due to attendance below 85%
    expect($rule->isSatisfiedBy(
        attendancePercent: 80,
        scorePercent: 80,
        completedLessonIds: ['10', '20'],
        completedAssignments: 2,
        isManuallyApproved: true,
    ))->toBeFalse();

    // Failed due to missing required lesson '20'
    expect($rule->isSatisfiedBy(
        attendancePercent: 90,
        scorePercent: 80,
        completedLessonIds: ['10'],
        completedAssignments: 2,
        isManuallyApproved: true,
    ))->toBeFalse();

    // Failed due to lack of manual approval
    expect($rule->isSatisfiedBy(
        attendancePercent: 90,
        scorePercent: 80,
        completedLessonIds: ['10', '20'],
        completedAssignments: 2,
        isManuallyApproved: false,
    ))->toBeFalse();

    // Invalid percent throws InvalidArgumentException
    expect(fn () => new CompletionRule(minAttendancePercent: 120))
        ->toThrow(InvalidArgumentException::class);
});

it('enforces that only enrolled students with >= 25% progress can review, and limits to one review', function (): void {
    $course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
    ]);

    $action = new SubmitCourseReview;

    // 1. Attempt review with 15% progress throws ValidationException
    expect(fn () => $action->execute(
        course: $course,
        student: $this->student,
        rating: 5,
        reviewText: 'Great course so far!',
        studentProgressPercent: 15.0,
    ))->toThrow(ValidationException::class);

    // 2. Submit with 30% progress succeeds
    $review = $action->execute(
        course: $course,
        student: $this->student,
        rating: 5,
        reviewText: 'Deep philosophical clarity and wonderful pranayama practices.',
        studentProgressPercent: 30.0,
    );

    expect($review)->toBeInstanceOf(CourseReview::class)
        ->and($review->status)->toBe(ReviewStatus::Pending)
        ->and($review->rating)->toBe(5);

    // 3. Second review attempt by same student throws duplicate validation exception
    expect(fn () => $action->execute(
        course: $course,
        student: $this->student,
        rating: 4,
        reviewText: 'Trying to post another review.',
        studentProgressPercent: 40.0,
    ))->toThrow(ValidationException::class);
});

it('moderates course reviews and updates course rating average cache', function (): void {
    $course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'rating_average' => 0.00,
        'reviews_count' => 0,
    ]);

    $student2 = User::factory()->create(['primary_branch_id' => $this->branch->id]);

    $review1 = CourseReview::create([
        'course_id' => $course->id,
        'user_id' => $this->student->id,
        'rating' => 5,
        'review_text' => 'Transformative practice.',
        'status' => ReviewStatus::Pending,
    ]);

    $review2 = CourseReview::create([
        'course_id' => $course->id,
        'user_id' => $student2->id,
        'rating' => 3,
        'review_text' => 'Good content, could be faster.',
        'status' => ReviewStatus::Pending,
    ]);

    $moderateAction = new ModerateCourseReview;

    // Approve Review 1 (Rating 5)
    $moderateAction->execute($review1, ReviewStatus::Approved, $this->admin);

    $course->refresh();
    expect($course->rating_average)->toBe(5.0)
        ->and($course->reviews_count)->toBe(1);

    // Approve Review 2 (Rating 3) -> Average: (5 + 3) / 2 = 4.0
    $moderateAction->execute($review2, ReviewStatus::Approved, $this->admin);

    $course->refresh();
    expect($course->rating_average)->toBe(4.0)
        ->and($course->reviews_count)->toBe(2);
});

it('cancels a course, sets cancellation reason, notifies students and flags refunds for review', function (): void {
    Event::fake([CourseCancelled::class]);

    $course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'status' => CourseStatus::Published,
    ]);

    $action = new CancelCourse;
    $cancelledCourse = $action->execute($course, 'Acharya unavailable due to retreat.', $this->admin);

    expect($cancelledCourse->status)->toBe(CourseStatus::Cancelled)
        ->and($cancelledCourse->cancelled_at)->not->toBeNull()
        ->and($cancelledCourse->cancellation_reason)->toBe('Acharya unavailable due to retreat.');

    Event::assertDispatched(CourseCancelled::class, function (CourseCancelled $event) use ($course): bool {
        return $event->course->id === $course->id
            && $event->reason === 'Acharya unavailable due to retreat.'
            && $event->flagRefundsForReview === true;
    });

    // Check audit activity log
    $activity = Activity::where('description', 'course_cancelled')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->properties['flag_refunds_for_review'])->toBeTrue();
});

it('duplicates a course along with its curriculum and resets status to draft', function (): void {
    $category = Category::create(['name' => 'Yoga Therapy', 'slug' => 'yoga-therapy']);

    $course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'category_id' => $category->id,
        'created_by' => $this->teacher->id,
        'name' => 'Ayurvedic Marma Therapy',
        'slug' => 'ayurvedic-marma-therapy',
        'status' => CourseStatus::Published,
        'base_fee_amount' => 1200000,
    ]);

    $module = Module::create([
        'course_id' => $course->id,
        'name' => 'Marma Points Intro',
        'sort_order' => 1,
    ]);

    $lesson = Lesson::create([
        'module_id' => $module->id,
        'title' => 'Anatomy of 107 Marma Points',
        'slug' => '107-marma-points',
        'content_type' => LessonContentType::Video,
        'sort_order' => 1,
    ]);

    LessonResource::create([
        'lesson_id' => $lesson->id,
        'title' => 'Marma Chart PDF',
        'type' => ResourceType::Pdf,
        'url_or_path' => 'https://assets.pramana.org/marma-chart.pdf',
    ]);

    $duplicateAction = new DuplicateCourse;
    $clone = $duplicateAction->execute($course);

    expect($clone->id)->not->toBe($course->id)
        ->and($clone->name)->toBe('Ayurvedic Marma Therapy (Copy)')
        ->and($clone->status)->toBe(CourseStatus::Draft)
        ->and($clone->modules)->toHaveCount(1)
        ->and($clone->modules->first()->lessons)->toHaveCount(1)
        ->and($clone->modules->first()->lessons->first()->resources)->toHaveCount(1);
});

it('generates secure signed streaming URLs through VideoProvider', function (): void {
    /** @var VideoProvider $provider */
    $provider = app(VideoProvider::class);

    $signedUrl = $provider->getSignedStreamUrl('video-guid-12345');
    expect($signedUrl)->toContain('video-guid-12345')
        ->and($signedUrl)->toContain('expires=');

    // Bunny Stream test
    $bunny = new BunnyStreamVideoProvider(libraryId: '999', tokenSecurityKey: 'secretKey');
    $bunnyUrl = $bunny->getSignedStreamUrl('sample-video-id');
    expect($bunnyUrl)->toContain('https://iframe.mediadelivery.net/embed/999/sample-video-id?token=')
        ->and($bunnyUrl)->toContain('expires=');
});

it('bulk publishes multiple courses', function (): void {
    Event::fake([CoursePublished::class]);

    $course1 = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'status' => CourseStatus::Draft,
    ]);

    $course2 = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'status' => CourseStatus::Draft,
    ]);

    $action = new BulkPublishCourses;
    $count = $action->execute([$course1->id, $course2->id], $this->admin);

    expect($count)->toBe(2)
        ->and($course1->fresh()->status)->toBe(CourseStatus::Published)
        ->and($course2->fresh()->status)->toBe(CourseStatus::Published);

    Event::assertDispatched(CoursePublished::class, 2);
});

it('supports Money value object on course fee attribute', function (): void {
    $course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'base_fee_amount' => 750000,
        'base_fee_currency' => 'INR',
    ]);

    expect($course->fee)->toBeInstanceOf(Money::class)
        ->and($course->fee->getMinorAmount())->toBe(750000)
        ->and($course->fee->getCurrency())->toBe('INR');

    // Mutator
    $course->fee = Money::fromMinor(900000, 'INR');
    $course->save();

    expect($course->fresh()->base_fee_amount)->toBe(900000);
});
