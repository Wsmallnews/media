<?php

namespace Wsmallnews\Media\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Wsmallnews\Media\Models\Media;

/**
 * 文件读取（inline 预览）：私有盘等无法直链的盘，由此路由流式输出。
 * 本地盘直接读文件；云盘 temporaryUrl（短效签名）跳转。
 */
class MediaFileController extends Controller
{
    public function __invoke(): BinaryFileResponse | RedirectResponse
    {
        $media = Media::findByPath((string) request()->query('path', ''));
        abort_unless($media, 404);

        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        $driver = config("filesystems.disks.{$media->disk}.driver");

        if (in_array($driver, ['local', 'public'], true)) {
            return response()->file($disk->path($media->path), ['Content-Type' => $media->mime]);
        }

        try {
            $url = $disk->temporaryUrl($media->path, now()->addMinutes(10));
        } catch (\Throwable) {
            $url = $disk->url($media->path);
        }

        return redirect()->away($url);
    }
}
