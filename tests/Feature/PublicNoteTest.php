<?php

declare(strict_types=1);

use App\Models\Note;

it('shows a published note', function (): void {
    $note = Note::factory()->create(['title' => 'My Note', 'html' => '<p>Rendered body</p>']);

    $this->get("/{$note->hash}")
        ->assertOk()
        ->assertSee('My Note')
        ->assertSee('<p>Rendered body</p>', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('<meta name="referrer" content="no-referrer">', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('returns 404 for unknown hashes', function (): void {
    $this->get('/'.str_repeat('b', 22))->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('has no index page', function (): void {
    $this->get('/')->assertNotFound();
});
