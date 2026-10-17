<?php

declare(strict_types=1);

it('renders custom 404 page with spiritual aesthetic and institute name', function (): void {
    $response = $this->get('/non-existent-route-for-testing-404');

    $response->assertStatus(404)
        ->assertSee('Untrod Path')
        ->assertSee('Pramana Institute');
});
