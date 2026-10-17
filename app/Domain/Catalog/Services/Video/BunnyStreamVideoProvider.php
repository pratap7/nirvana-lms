<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Services\Video;

class BunnyStreamVideoProvider implements VideoProvider
{
    public function __construct(
        protected string $libraryId = '',
        protected string $apiKey = '',
        protected string $tokenSecurityKey = '',
        protected string $pullZoneDomain = 'video.pramana-institute.org',
    ) {
        $this->libraryId = $libraryId ?: (string) config('services.bunny.library_id', '12345');
        $this->tokenSecurityKey = $tokenSecurityKey ?: (string) config('services.bunny.token_security_key', 'bunny_secret_token_key');
        $this->pullZoneDomain = $pullZoneDomain ?: (string) config('services.bunny.pull_zone', 'video.pramana-institute.org');
    }

    public function getSignedStreamUrl(string $videoId, int $expiresInSeconds = 7200, array $options = []): string
    {
        $expires = time() + $expiresInSeconds;
        $hashable = $this->tokenSecurityKey.$videoId.$expires;
        $token = hash('sha256', $hashable);

        $params = http_build_query(array_merge([
            'token' => $token,
            'expires' => $expires,
            'autoplay' => ! empty($options['autoplay']) ? 'true' : 'false',
        ], $options['extra_params'] ?? []));

        return "https://iframe.mediadelivery.net/embed/{$this->libraryId}/{$videoId}?{$params}";
    }

    public function getSignedThumbnailUrl(string $videoId): ?string
    {
        return "https://{$this->pullZoneDomain}/{$videoId}/thumbnail.jpg";
    }

    public function getVideoMetadata(string $videoId): array
    {
        return [
            'provider' => 'bunny_stream',
            'video_id' => $videoId,
            'library_id' => $this->libraryId,
            'embed_url' => "https://iframe.mediadelivery.net/embed/{$this->libraryId}/{$videoId}",
        ];
    }
}
