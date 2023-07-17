<?php

use Illuminate\Support\Facades\Route;
use Lunar\Hub\Http\Middleware\Authenticate;
use XtendLunar\Addons\StoreImporter\Livewire\Pages\StoreImporter;

/**
 * Store Importer Routes
 */
Route::group([
    'prefix' => config('lunar-hub.system.path', 'hub'),
    'middleware' => ['web', Authenticate::class, 'can:settings:core'],
], function () {
    Route::get('/store-importer', StoreImporter::class)->name('hub.store-importer');
    //Route::get('/store-importer/{importer}', StoreImporter::class)->name('hub.store-importer.show');
});
