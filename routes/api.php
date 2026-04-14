<?php

use App\Actions\VideoChunkingAction;
use App\Http\Controllers\KeyController;
use Illuminate\Support\Facades\Route;

Route::post('/video-chunking', [VideoChunkingAction::class, 'handle']);
Route::get('video/key/{clientName}/{key}', [KeyController::class, 'getKey'])->name('api.video.key');

