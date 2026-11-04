<?php

use App\Http\Controllers\BrandAssetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('brand')->name('brand.')->group(function (): void {
    Route::get('logo', [BrandAssetController::class, 'logo'])->name('logo');
    Route::get('icon', [BrandAssetController::class, 'icon'])->name('icon');
    Route::get('pattern', [BrandAssetController::class, 'pattern'])->name('pattern');
});

Route::get('/admin/rental-aftercares', function (Request $request) {
    $query = $request->getQueryString();

    return redirect('/admin/rental-customer-follow-ups'.($query ? '?'.$query : ''));
});

Route::get('/admin/resale-aftercares', function (Request $request) {
    $query = $request->getQueryString();

    return redirect('/admin/resale-customer-follow-ups'.($query ? '?'.$query : ''));
});

Route::get('/', function () {
    return response('', 302)->header('Location', '/admin/login');
});
