<?php

use Illuminate\Support\Facades\Route;

// Web UI follows in a later sprint; functions are available via /api/v1.
Route::middleware(['auth', 'verified'])->group(function () {
    //
});
