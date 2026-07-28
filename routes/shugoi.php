<?php
use Illuminate\Support\Facades\Route;
use Shugoi\Laravel\ShugoiController;

Route::get('/__shugoi/render', [ShugoiController::class, 'render']);
Route::head('/__shugoi/healthcheck', function () { return response('', 200); });
