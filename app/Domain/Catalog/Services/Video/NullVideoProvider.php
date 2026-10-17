<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Services\Video;

class NullVideoProvider implements VideoProvider
{
    public function getSignedStreamUrl(string $videoId, int $expiresInSeconds = 7200, array $options = []): string
    {
        $expires = time() + $expiresInSeconds;
        $signature = hash_hmac('sha256', "{$videoId}:{$expires}", 'testing-secret');

        return "https://stream.pramana-institute.org/videos/{$videoId}/play?expires={$expires}&sig={$signature}";
    }

    public function getSignedThumbnailUrl(string $videoId): ?string
    {
        return "https://stream.pramana-institute.org/videos/{$videoId}/poster.webp";
    }

    public function getVideoMetadata(string $videoId): array
    {
        return [
            'provider' => 'null_provider',
            'video_id' => $videoId,
            'duration_seconds' => 1800,
            'resolutions' => ['1080p', '720p', '480p'],
        ];
    }
}
