<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Services\Video;

interface VideoProvider
{
    /**
     * Generate secure, time-limited signed URL for streaming video playback.
     *
     * @param  string  $videoId  Unique identifier or GUID in the provider system
     * @param  int  $expiresInSeconds  Validity duration in seconds (default 2 hours)
     * @param  array<string, mixed>  $options  Custom options (e.g. autoplay, token controls)
     */
    public function getSignedStreamUrl(string $videoId, int $expiresInSeconds = 7200, array $options = []): string;

    /**
     * Generate signed URL for video thumbnail poster.
     */
    public function getSignedThumbnailUrl(string $videoId): ?string;

    /**
     * Retrieve provider metadata (duration, resolution, encoding status).
     *
     * @return array<string, mixed>
     */
    public function getVideoMetadata(string $videoId): array;
}
