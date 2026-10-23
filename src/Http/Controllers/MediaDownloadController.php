<?php

namespace Wsmallnews\Media\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Wsmallnews\Media\Support\Utils;

/**
 * Download with the original filename: local disks stream the file, cloud disks redirect to
 * a temporary URL with Content-Disposition.
 */
class MediaDownloadController extends Controller
{
    public function __invoke(int | string $media): BinaryFileResponse | RedirectResponse
    {
        $query = Utils::getMediaModel()::query();
        $teamId = request()->query('team_id');
        abort_unless($teamId === null || is_string($teamId), 404);
        Utils::scopeMediaQueryToTeam($query, $teamId);

        $media = $query->findOrFail($media);
        $disk = Storage::disk($media->disk);
        $driver = config("filesystems.disks.{$media->disk}.driver");

        abort_unless($disk->exists($media->path), 404);

        if (in_array($driver, ['local', 'public'], true)) {
            return response()->download($disk->path($media->path), $media->name);
        }

        $ttl = (int) Utils::getConfig('download_url_ttl', 10);

        try {
            $url = $disk->temporaryUrl($media->path, now()->addMinutes($ttl), [
                'ResponseContentDisposition' => 'attachment; filename="' . rawurlencode($media->name) . '"',
            ]);
        } catch (\Throwable) {
            abort(404);
        }

        return redirect()->away($url);
    }
}
