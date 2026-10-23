<?php

use Illuminate\Support\Facades\Route;
use Wsmallnews\Media\Http\Controllers\MediaDownloadController;
use Wsmallnews\Media\Http\Controllers\MediaFileController;

// Download with the original filename (the `name` column).
Route::get('/sn-media/download/{media}', MediaDownloadController::class)
    ->middleware(['web', 'signed'])
    ->name('sn-media.download');

// Inline preview for non-public disks (streamed locally or via a signed cloud URL).
Route::get('/sn-media/file', MediaFileController::class)
    ->middleware(['web', 'signed'])
    ->name('sn-media.file');
