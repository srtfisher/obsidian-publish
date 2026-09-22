<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PublishNoteRequest;
use App\Markdown\NoteRenderer;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

final class NoteController
{
    public function __construct(private readonly NoteRenderer $renderer) {}

    public function show(Note $note): View
    {
        return view('notes.show', ['note' => $note]);
    }

    public function store(PublishNoteRequest $request): JsonResponse
    {
        $note = new Note;
        $note->hash = Note::newHash();

        return $this->publish($note, $request, 201);
    }

    public function update(PublishNoteRequest $request, Note $note): JsonResponse
    {
        return $this->publish($note, $request, 200);
    }

    public function destroy(Note $note): Response
    {
        Storage::disk('public')->deleteDirectory($note->assetDirectory());
        $note->delete();

        return response()->noContent();
    }

    private function publish(Note $note, PublishNoteRequest $request, int $status): JsonResponse
    {
        $disk = Storage::disk('public');
        $disk->deleteDirectory($note->assetDirectory());

        foreach ($request->assetFiles() as $file) {
            $file->storeAs($note->assetDirectory(), $file->getClientOriginalName(), 'public');
        }

        $markdown = $request->string('markdown')->toString();

        $note->fill([
            'title' => $request->string('title')->toString(),
            'markdown' => $markdown,
            'html' => $this->renderer->render($markdown, $disk->url($note->assetDirectory())),
        ])->save();

        return response()->json(['hash' => $note->hash, 'url' => route('notes.show', $note)], $status);
    }
}
