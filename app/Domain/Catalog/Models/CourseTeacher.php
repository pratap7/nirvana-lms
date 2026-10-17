<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CourseTeacherRole;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teacher assigned to a course with role (primary, assistant, guest).
 *
 * @property int $id
 * @property int $course_id
 * @property int $user_id
 * @property CourseTeacherRole $role
 * @property int $sort_order
 * @property-read ?Course $course
 * @property-read ?User $user
 */
class CourseTeacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'user_id',
        'role',
        'sort_order',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CourseTeacherRole::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
