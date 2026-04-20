<?php

use App\Http\Controllers\KeyController;
use App\Http\Controllers\VideoChunkingController;
use Illuminate\Support\Facades\Route;

Route::middleware('worker.auth')->group(function () {
    Route::post('/video-chunking', [VideoChunkingController::class, 'process']);
    Route::get('video/key/{clientName}/{key}', [KeyController::class, 'getKey'])->name('api.video.key');
});
