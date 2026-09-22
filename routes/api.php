<?php

declare(strict_types=1);

use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::pattern('note', '[A-Za-z0-9]{22}');

Route::post('/notes', [NoteController::class, 'store']);
Route::post('/notes/{note}', [NoteController::class, 'update']);
Route::delete('/notes/{note}', [NoteController::class, 'destroy']);
