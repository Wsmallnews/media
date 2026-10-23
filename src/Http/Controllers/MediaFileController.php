<?php

namespace Wsmallnews\Media\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Wsmallnews\Media\Support\Utils;

/**
 * Inline preview for disks without direct URLs: local disks stream the file, cloud disks
 * redirect to a short-lived temporary URL.
 */
class MediaFileController extends Controller
{
    public function __invoke(): BinaryFileResponse | RedirectResponse
    {
        $path = (string) request()->query('path', '');
        abort_if($path === '', 404);

        $query = Utils::getMediaModel()::query();
        $teamId = request()->query('team_id');
        abort_unless($teamId === null || is_string($teamId), 404);
        Utils::scopeMediaQueryToTeam($query, $teamId);

        $media = $query->where('path', $path)->first();
        abort_unless($media, 404);

        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        $driver = config("filesystems.disks.{$media->disk}.driver");

        if (in_array($driver, ['local', 'public'], true)) {
            return response()->file($disk->path($media->path), [
                'Content-Type' => $media->mime,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        try {
            $ttl = (int) Utils::getConfig('preview_url_ttl', 10);
            $url = $disk->temporaryUrl($media->path, now()->addMinutes($ttl));
        } catch (\Throwable) {
            abort(404);
        }

        return redirect()->away($url);
    }
}
