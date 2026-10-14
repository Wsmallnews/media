<?php

use Illuminate\Support\Facades\Route;
use Wsmallnews\Media\Http\Controllers\MediaDownloadController;
use Wsmallnews\Media\Http\Controllers\MediaFileController;
use Wsmallnews\Media\Http\Controllers\MediaUploadController;

// 下载：以原始文件名（name 列）返回
Route::get('/sn-media/download/{media}', MediaDownloadController::class)
    ->middleware('web')
    ->name('sn-media.download');

// FilePicker 直传端点（面板内使用，走统一上传管道）
Route::post('/sn-media/upload', MediaUploadController::class)
    ->middleware('web')
    ->name('sn-media.upload');

// 私有盘等不可直链盘的 inline 预览（本地流式输出 / 云盘短效签名跳转）
Route::get('/sn-media/file', MediaFileController::class)
    ->middleware('web')
    ->name('sn-media.file');
