<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
final class NoteFactory extends Factory
{
    public function definition(): array
    {
        $body = fake()->paragraph();

        return [
            'hash' => Note::newHash(),
            'title' => fake()->sentence(3),
            'markdown' => $body,
            'html' => "<p>{$body}</p>",
        ];
    }
}
