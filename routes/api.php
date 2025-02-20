<?php

use App\Http\Controllers\PythonController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/chatgpt', [PythonController::class, 'runPythonScript']);
