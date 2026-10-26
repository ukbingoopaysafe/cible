<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandAssetController extends Controller
{
    public function logo(): BinaryFileResponse
    {
        return response()->file(public_path('images/brand/logo.svg'), [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function icon(): BinaryFileResponse
    {
        return response()->file(public_path('images/brand/icon.png'), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function pattern(): BinaryFileResponse
    {
        return response()->file(public_path('images/brand/pattern.png'), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
