<?php

declare(strict_types=1);

namespace App\Domain\Cms\Models;

use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $email
 * @property ?string $phone
 * @property ?string $subject
 * @property string $message
 * @property ?string $course_interest
 * @property string $status
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property bool $is_spam
 */
class ContactInquiry extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'course_interest',
        'status',
        'ip_address',
        'user_agent',
        'is_spam',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_spam' => 'boolean',
        ];
    }

    /**
     * @param  Builder<ContactInquiry>  $query
     * @return Builder<ContactInquiry>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_spam', false);
    }
}
