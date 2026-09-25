<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // API-first (R3); keine eigenen Web-Views.
});
