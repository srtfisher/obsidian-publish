<?php

declare(strict_types=1);

use App\Models\Note;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config(['publish.token' => 'secret-token']);
    Storage::fake('public');
});

function authed(): array
{
    return ['Authorization' => 'Bearer secret-token', 'Accept' => 'application/json'];
}

it('rejects requests without a valid token', function (?string $token): void {
    $headers = ['Accept' => 'application/json'];

    if ($token !== null) {
        $headers['Authorization'] = "Bearer {$token}";
    }

    $this->post('/api/notes', ['title' => 'x', 'markdown' => 'y'], $headers)->assertUnauthorized();
})->with([null, 'wrong']);

it('rejects all requests when no token is configured', function (): void {
    config(['publish.token' => '']);

    $this->post('/api/notes', ['title' => 'x', 'markdown' => 'y'], ['Authorization' => 'Bearer ', 'Accept' => 'application/json'])
        ->assertUnauthorized();
});

it('creates a note with assets', function (): void {
    $response = $this->post('/api/notes', [
        'title' => 'End-to-end Testing',
        'markdown' => "# Hi\n\n![shot](<1-shot.png>)",
        'assets' => [UploadedFile::fake()->image('1-shot.png')],
    ], authed());

    $response->assertCreated()->assertJsonStructure(['hash', 'url']);

    $note = Note::sole();
    expect($note->hash)->toMatch('/^[A-Za-z0-9]{22}$/')
        ->and($note->title)->toBe('End-to-end Testing')
        ->and($note->html)->toContain('<h1>Hi</h1>')
        ->and($note->html)->toContain("/storage/notes/{$note->hash}/1-shot.png")
        ->and($response->json('url'))->toBe(route('notes.show', $note));

    Storage::disk('public')->assertExists("notes/{$note->hash}/1-shot.png");
});

it('replaces content and assets on update', function (): void {
    $hash = $this->post('/api/notes', [
        'title' => 'Old',
        'markdown' => 'old',
        'assets' => [UploadedFile::fake()->image('1-old.png')],
    ], authed())->json('hash');

    $this->post("/api/notes/{$hash}", [
        'title' => 'New',
        'markdown' => 'new body',
        'assets' => [UploadedFile::fake()->image('1-new.jpg')],
    ], authed())->assertOk()->assertJson(['hash' => $hash]);

    $note = Note::sole();
    expect($note->title)->toBe('New')->and($note->html)->toContain('new body');

    Storage::disk('public')->assertMissing("notes/{$hash}/1-old.png");
    Storage::disk('public')->assertExists("notes/{$hash}/1-new.jpg");
});

it('returns 404 when updating an unknown note', function (): void {
    $this->post('/api/notes/'.str_repeat('a', 22), ['title' => 'x', 'markdown' => 'y'], authed())->assertNotFound();
});

it('deletes a note and its assets', function (): void {
    $hash = $this->post('/api/notes', [
        'title' => 'x',
        'markdown' => 'y',
        'assets' => [UploadedFile::fake()->image('a.png')],
    ], authed())->json('hash');

    $this->delete("/api/notes/{$hash}", [], authed())->assertNoContent();

    expect(Note::count())->toBe(0);
    Storage::disk('public')->assertMissing("notes/{$hash}");
    $this->delete("/api/notes/{$hash}", [], authed())->assertNotFound();
});

it('validates the payload', function (array $payload, string $field): void {
    $this->post('/api/notes', $payload, authed())->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'missing title' => [['markdown' => 'y'], 'title'],
    'missing markdown' => [['title' => 'x'], 'markdown'],
    'svg' => [fn () => ['title' => 'x', 'markdown' => 'y', 'assets' => [UploadedFile::fake()->create('a.svg', 1, 'image/svg+xml')]], 'assets.0'],
    'not an image' => [fn () => ['title' => 'x', 'markdown' => 'y', 'assets' => [UploadedFile::fake()->create('a.png', 1, 'text/plain')]], 'assets.0'],
    'unsafe name' => [fn () => ['title' => 'x', 'markdown' => 'y', 'assets' => [UploadedFile::fake()->image('../a b.png')]], 'assets.0'],
    'duplicate names' => [fn () => ['title' => 'x', 'markdown' => 'y', 'assets' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('a.png')]], 'assets'],
]);
