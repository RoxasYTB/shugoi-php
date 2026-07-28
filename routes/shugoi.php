<?php
use Illuminate\Support\Facades\Route;
use Shugoi\Laravel\ShugoiController;

Route::get('/__shugoi/render', [ShugoiController::class, 'render']);
Route::match(['get', 'head'], '/__shugoi/healthcheck', fn() => response('', 200));
