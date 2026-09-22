<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A published Obsidian note, reachable only through its random hash.
 *
 * @property int $id
 * @property string $hash
 * @property string $title
 * @property string $markdown
 * @property string $html
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    protected $fillable = ['title', 'markdown', 'html'];

    public static function newHash(): string
    {
        return Str::random(22);
    }

    public function getRouteKeyName(): string
    {
        return 'hash';
    }

    public function assetDirectory(): string
    {
        return "notes/{$this->hash}";
    }
}
