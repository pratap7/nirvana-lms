<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Models;

use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $quiz_id
 * @property string $question_text
 * @property string $type
 * @property array<int, array{id: string, text: string, is_correct: bool}> $options
 * @property ?string $explanation
 * @property int $points
 * @property int $sort_order
 * @property-read ?Quiz $quiz
 */
class QuizQuestion extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'quiz_id',
        'question_text',
        'type',
        'options',
        'explanation',
        'points',
        'sort_order',
        'question_type',
    ];

    public function setQuestionTypeAttribute(string $value): void
    {
        $this->attributes['type'] = $value;
    }

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'points' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
