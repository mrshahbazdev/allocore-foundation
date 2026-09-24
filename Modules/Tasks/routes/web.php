<?php

use Illuminate\Support\Facades\Route;
use Modules\Tasks\Http\Controllers\TasksController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('tasks', TasksController::class)->names('tasks');
});
