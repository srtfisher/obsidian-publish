<?php

declare(strict_types=1);

use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::get('/{note}', [NoteController::class, 'show'])
    ->where('note', '[A-Za-z0-9]{22}')
    ->name('notes.show');
