<?php

use App\Http\Controllers\Api\SlicerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Náš vlastní slicer endpoint
Route::prefix('slicer')->group(function () {
Route::get('/ping', [SlicerController::class, 'ping']);
Route::post('/upload', [SlicerController::class, 'upload'])
    ->middleware('auth:sanctum');
});

// OctoPrint / Orca kompatibilní API - root level
Route::get('/version', [SlicerController::class, 'octoprintVersion']);
Route::get('/printer', [SlicerController::class, 'octoprintPrinter'])
    ->middleware(['App\Http\Middleware\ApiKeyToBearer', 'auth:sanctum']);
Route::post('/files/local', [SlicerController::class, 'octoprintUpload'])
    ->middleware(['App\Http\Middleware\ApiKeyToBearer', 'auth:sanctum']);

// OctoPrint kompatibilní API - octoprint prefix
Route::prefix('octoprint')->group(function () {
Route::get('/version', [SlicerController::class, 'octoprintVersion']);
Route::get('/printer', [SlicerController::class, 'octoprintPrinter'])
    ->middleware(['App\Http\Middleware\ApiKeyToBearer', 'auth:sanctum']);
Route::post('/files/local', [SlicerController::class, 'octoprintUpload'])
    ->middleware(['App\Http\Middleware\ApiKeyToBearer', 'auth:sanctum']);
});
