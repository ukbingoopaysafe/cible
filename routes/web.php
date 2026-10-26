<?php

use App\Http\Controllers\BrandAssetController;
use Illuminate\Support\Facades\Route;

Route::prefix('brand')->name('brand.')->group(function (): void {
    Route::get('logo', [BrandAssetController::class, 'logo'])->name('logo');
    Route::get('icon', [BrandAssetController::class, 'icon'])->name('icon');
    Route::get('pattern', [BrandAssetController::class, 'pattern'])->name('pattern');
});

Route::get('/', function () {
    return view('welcome');
});
