<?php

use Illuminate\Support\Facades\Route;
use Wsmallnews\Media\Http\Controllers\MediaDownloadController;

Route::get('/sn-media/download/{media}', MediaDownloadController::class)
    ->middleware('web')
    ->name('sn-media.download');
