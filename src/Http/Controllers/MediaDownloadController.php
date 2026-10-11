<?php

namespace Wsmallnews\Media\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Wsmallnews\Media\Models\Media;
use Wsmallnews\Media\Support\Utils;

/**
 * 下载：以原始文件名（name 列）返回。
 * 本地盘直接流式输出；云盘 temporaryUrl + ResponseContentDisposition。
 */
class MediaDownloadController extends Controller
{
    public function __invoke(Media $media): BinaryFileResponse | RedirectResponse
    {
        $disk = Storage::disk($media->disk);
        $driver = config("filesystems.disks.{$media->disk}.driver");

        if (in_array($driver, ['local', 'public'], true)) {
            abort_unless($disk->exists($media->path), 404);

            return response()->download($disk->path($media->path), $media->name);
        }

        $ttl = (int) Utils::getConfig('download_url_ttl', 10);

        try {
            $url = $disk->temporaryUrl($media->path, now()->addMinutes($ttl), [
                'ResponseContentDisposition' => 'attachment; filename="' . rawurlencode($media->name) . '"',
            ]);
        } catch (\Throwable) {
            $url = $disk->url($media->path);
        }

        return redirect()->away($url);
    }
}
