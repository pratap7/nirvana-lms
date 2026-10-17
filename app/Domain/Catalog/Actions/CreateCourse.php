<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DTOs\CreateCourseData;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Events\CourseCreated;
use App\Domain\Catalog\Models\Course;
use Illuminate\Support\Facades\DB;

final class CreateCourse
{
    public function execute(CreateCourseData $data): Course
    {
        return DB::transaction(function () use ($data): Course {
            $course = Course::create([
                'program_id' => $data->programId,
                'branch_id' => $data->branchId,
                'name' => $data->name,
                'slug' => $data->slug,
                'short_description' => $data->shortDescription,
                'long_description' => $data->longDescription,
                'difficulty_level' => $data->difficultyLevel,
                'duration_hours' => $data->durationHours,
                'max_students' => $data->maxStudents,
                'base_fee_amount' => $data->baseFeeAmount,
                'base_fee_currency' => $data->baseFeeCurrency,
                'status' => CourseStatus::Draft,
                'sort_order' => $data->sortOrder,
                'metadata' => $data->metadata,
                'created_by' => $data->createdBy,
            ]);

            event(new CourseCreated($course));

            return $course;
        });
    }
}
