<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function index(): View
    {
        $storagePath = storage_path('app');
        $probe = is_writable($storagePath) ? tempnam($storagePath, 'admin-health-') : false;
        $writable = $probe !== false && @file_put_contents($probe, 'ok') === 2;
        if (is_string($probe) && is_file($probe)) @unlink($probe);
        return view('admin.system.index', [
            'version' => app()->version(), 'phpVersion' => PHP_VERSION,
            'storageWritable' => $writable, 'mailConfigured' => filled(config('mail.default')) && config('mail.default') !== 'log',
            'mailDriver' => (string) config('mail.default', 'unconfigured'),
        ]);
    }

    public function clearCache(): RedirectResponse
    {
        Cache::forget('storefront.settings');
        Cache::forget('storefront.categories');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');
        return back()->with('success', 'Application and storefront caches cleared.');
    }
}
