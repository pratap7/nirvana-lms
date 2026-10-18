<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum AssessmentType: string
{
    use HasEnumHelpers;

    case Quiz = 'quiz';
    case Assignment = 'assignment';
    case Practical = 'practical';
    case FinalExam = 'final_exam';
    case TeacherEvaluation = 'teacher_evaluation';

    public function label(): string
    {
        return match ($this) {
            self::Quiz => 'Online Quiz',
            self::Assignment => 'Written Assignment / Journal',
            self::Practical => 'Practical Sadhana / Asana Demonstration',
            self::FinalExam => 'Comprehensive Final Exam',
            self::TeacherEvaluation => 'Faculty Mentor Evaluation',
        };
    }
}
