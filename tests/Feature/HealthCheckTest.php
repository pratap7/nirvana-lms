<?php

declare(strict_types=1);

it('returns healthy status on /health endpoint', function (): void {
    $response = $this->getJson('/health');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'timestamp',
            'environment',
            'version',
            'services' => [
                'database' => ['ok', 'message'],
                'cache' => ['ok', 'message'],
                'storage' => ['ok', 'message'],
            ],
        ])
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('services.database.ok', true)
        ->assertJsonPath('services.cache.ok', true)
        ->assertJsonPath('services.storage.ok', true);
});
