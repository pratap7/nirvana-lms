<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Security\SecureUploadService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\ValidationException;

class SecureFileUpload implements ValidationRule
{
    /**
     * @param  array<string>|null  $allowedMimes
     */
    public function __construct(
        protected int $maxSizeBytes = 25 * 1024 * 1024,
        protected ?array $allowedMimes = null,
        protected ?SecureUploadService $service = null
    ) {
        $this->service ??= app(SecureUploadService::class);
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! ($value instanceof UploadedFile)) {
            $fail('The :attribute must be an uploaded file.');

            return;
        }

        try {
            $this->service->inspect($value, $this->maxSizeBytes, $this->allowedMimes);
        } catch (ValidationException $e) {
            $messages = $e->errors();
            $firstMessage = reset($messages)[0] ?? 'File validation failed.';
            $fail($firstMessage);
        } catch (\Throwable $e) {
            $fail('File upload failed security checks: '.$e->getMessage());
        }
    }
}
