<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum LessonDripType: string
{
    case Immediate = 'immediate';
    case DaysAfterEnrollment = 'days_after_enrollment';
    case AfterPreviousLesson = 'after_previous_lesson';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Immediate (Available on enrollment)',
            self::DaysAfterEnrollment => 'Days After Enrollment',
            self::AfterPreviousLesson => 'Unlock After Previous Lesson Completed',
        };
    }
}
