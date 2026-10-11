<?php

namespace Wsmallnews\Media\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Wsmallnews\Media\Exceptions\MediaException;

class MediaManager
{
    /**
     * 上传并入账（统一上传管道：写盘 → 读宽高 → 记录 sn_media）。
     *
     * @param  string|null  $directory  目录前缀（模块语义，如 sn/shop/product；null = 配置默认）
     */
    public function upload(UploadedFile $file, ?string $directory = null, ?Model $uploader = null, ?int $teamId = null): \Wsmallnews\Media\Models\Media
    {
        $this->validate($file);

        $modelClass = Utils::getMediaModel();
        $disk = Utils::getDisk();
        $ext = $this->extension($file);
        $directory = trim($directory ?: (string) Utils::getConfig('directory', 'sn/media'), '/');
        $path = $directory . '/' . now()->format('Ymd') . '/' . Str::random(8) . '.' . $ext;

        Storage::disk($disk)->put($path, fopen($file->getRealPath(), 'rb'), [
            'visibility' => Utils::getConfig('visibility', 'public'),
        ]);

        [$width, $height] = $this->readImageSize($file);
        $uploader = $uploader ?? $this->resolveUploader();

        return $modelClass::create([
            'team_id' => $teamId ?? $this->resolveTeamId(),
            'disk' => $disk,
            'path' => $path,
            'name' => $file->getClientOriginalName() ?: ($path),
            'ext' => $ext,
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'width' => $width,
            'height' => $height,
            'uploader_type' => $uploader?->getMorphClass(),
            'uploader_id' => $uploader?->getKey(),
        ]);
    }

    /**
     * 上传并直接返回值对象（字段保存值）
     *
     * @return array{path: string, name: string, mime: string, size: int, width: int|null, height: int|null}
     */
    public function uploadValue(UploadedFile $file, ?string $directory = null, ?Model $uploader = null, ?int $teamId = null): array
    {
        return $this->upload($file, $directory, $uploader, $teamId)->toValue();
    }

    /**
     * 大小 / 类型白名单校验
     */
    protected function validate(UploadedFile $file): void
    {
        $maxSize = Utils::getConfig('max_size');
        if ($maxSize && $file->getSize() > ((int) $maxSize) * 1024) {
            throw new MediaException('File exceeds the maximum allowed size.');
        }

        $mimes = Utils::getConfig('mimes');
        if (is_array($mimes) && ! empty($mimes) && ! in_array($this->extension($file), array_map('strtolower', (array) $mimes), true)) {
            throw new MediaException('File type is not allowed.');
        }
    }

    protected function extension(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());

        return $ext !== '' ? $ext : strtolower((string) $file->guessExtension() ?: 'bin');
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function readImageSize(UploadedFile $file): array
    {
        if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
            return [null, null];
        }

        $info = @getimagesize((string) $file->getRealPath());

        return $info ? [(int) $info[0], (int) $info[1]] : [null, null];
    }

    /**
     * 面板租户（控制台 / 队列等无面板上下文时为 null）
     */
    protected function resolveTeamId(): ?int
    {
        $tenant = rescue(fn () => filament()?->getTenant(), null, false);

        return $tenant?->getKey();
    }

    protected function resolveUploader(): ?Model
    {
        return rescue(fn () => filament()?->auth()->user(), null, false);
    }
}
