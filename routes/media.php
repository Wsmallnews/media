<?php

use Illuminate\Support\Facades\Route;
use Wsmallnews\Media\Http\Controllers\MediaDownloadController;
use Wsmallnews\Media\Http\Controllers\MediaFileController;

// 下载：以原始文件名（name 列）返回
Route::get('/sn-media/download/{media}', MediaDownloadController::class)
    ->middleware(['web', 'signed'])
    ->name('sn-media.download');

// 私有盘等不可直链盘的 inline 预览（本地流式输出 / 云盘短效签名跳转）
Route::get('/sn-media/file', MediaFileController::class)
    ->middleware(['web', 'signed'])
    ->name('sn-media.file');
