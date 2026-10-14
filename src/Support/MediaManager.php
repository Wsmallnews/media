<?php

namespace Wsmallnews\Media\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Wsmallnews\Media\Exceptions\MediaException;
use Wsmallnews\Media\Models\Media;
use Wsmallnews\Media\Models\MediaDirectory;

class MediaManager
{
    /**
     * 上传并入账（统一上传管道：内容哈希 → 当天判重 → 写盘 → 读宽高 → 记录 sn_media）。
     *
     * 物理路径 = {根}/{Ymd}/{内容md5}.{ext}（内容寻址）：
     * - 当天同内容 = 同 path = 唯一索引命中 → 秒传复用已有记录（含并发撞索引回查）；
     * - 隔天同内容 = 不同日期层 = 独立新文件；
     * - path 生成后永不变（移动目录只改 media.directory_id 逻辑归属）。
     *
     * @param  MediaDirectory|int|null  $directory  逻辑目录（模型/id/null=未归类）
     * @param  string|null  $disk  目标盘（null = 活动盘；私有文件传私有盘名）
     */
    public function upload(UploadedFile $file, $directory = null, ?Model $uploader = null, ?int $teamId = null, ?string $disk = null): Media
    {
        $this->validate($file);

        $modelClass = Utils::getMediaModel();
        $disk = $disk ?: Utils::getDisk();
        $ext = $this->extension($file);
        $hash = $this->contentHash($file);
        $path = 'sn/' . now()->format('Ymd') . '/' . $hash . '.' . $ext;

        // 当天秒传：同内容同 path 已有记录 → 直接复用（磁盘写入也跳过）
        $existing = $modelClass::findByPath($path);
        if ($existing) {
            return $existing;
        }

        Storage::disk($disk)->put($path, fopen($file->getRealPath(), 'rb'), [
            'visibility' => Utils::getConfig('visibility', 'public'),
        ]);

        [$width, $height] = $this->readImageSize($file);
        $uploader = $uploader ?? $this->resolveUploader();
        $directoryId = $directory instanceof MediaDirectory ? $directory->id : ($directory !== null && $directory !== '' && $directory !== 0 ? (int) $directory : null);

        try {
            return $modelClass::create([
                'team_id' => $teamId ?? $this->resolveTeamId(),
                'disk' => $disk,
                'path' => $path,
                'directory_id' => $directoryId,
                'name' => $file->getClientOriginalName() ?: ($path),
                'ext' => $ext,
                'mime' => $this->sniff($file) ?: ((string) ($file->getMimeType() ?: 'application/octet-stream')),
                'size' => $file->getSize() ?: 0,
                'width' => $width,
                'height' => $height,
                'uploader_type' => $uploader?->getMorphClass(),
                'uploader_id' => $uploader?->getKey(),
            ]);
        } catch (\Throwable $e) {
            // 并发双传撞 path 唯一索引：回查复用；其他异常补偿删除已落盘文件
            $existing = $modelClass::findByPath($path);
            if ($existing) {
                Storage::disk($disk)->delete($path);

                return $existing;
            }

            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }

    /**
     * 上传并直接返回值对象（字段保存值）
     *
     * @return array{path: string, name: string, mime: string, size: int, width: int|null, height: int|null}
     */
    public function uploadValue(UploadedFile $file, $directory = null, ?Model $uploader = null, ?int $teamId = null, ?string $disk = null): array
    {
        return $this->upload($file, $directory, $uploader, $teamId, $disk)->toValue();
    }

    /**
     * 内容 md5（文件内容寻址；失败退化到随机串保证路径唯一）
     */
    protected function contentHash(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if ($path && is_file($path)) {
            $md5 = @md5_file($path);

            if ($md5 !== false) {
                return $md5;
            }
        }

        return Str::random(32);
    }

    /**
     * 大小 / 类型白名单 / MIME 嗅探一致性校验
     */
    protected function validate(UploadedFile $file): void
    {
        $maxSize = Utils::getConfig('max_size');
        if ($maxSize && $file->getSize() > ((int) $maxSize) * 1024) {
            throw new MediaException('File exceeds the maximum allowed size.');
        }

        $ext = $this->extension($file);

        $mimes = Utils::getConfig('mimes');
        if (is_array($mimes) && ! empty($mimes) && ! in_array($ext, array_map('strtolower', (array) $mimes), true)) {
            throw new MediaException('File type is not allowed.');
        }

        // 嗅探一致性：扩展名对应的真实 MIME 家族（finfo 内容嗅探必须命中，防改后缀伪装）。
        // 注意 Symfony UploadedFile::getMimeType() 返回客户端声明值（按扩展名猜），不可作安全判据。
        $sniffed = $this->sniff($file);
        if ($sniffed !== null) {
            foreach (static::mimeFamilies()[$ext] ?? [] as $allowed) {
                $matched = str_ends_with($allowed, '*')
                    ? str_starts_with($sniffed, substr($allowed, 0, -1))
                    : $sniffed === $allowed;

                if ($matched) {
                    return;
                }
            }

            if (isset(static::mimeFamilies()[$ext])) {
                throw new MediaException('File content does not match its extension.');
            }
        }
    }

    /**
     * finfo 内容嗅探（真实 MIME；失败返回 null）
     */
    protected function sniff(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        if (! $path || ! is_file($path)) {
            return null;
        }

        $mime = @mime_content_type($path);

        return $mime === false ? null : strtolower($mime);
    }

    /**
     * 扩展名 => 允许的嗅探 MIME 家族（条目支持 'text/*' 前缀通配；族表外的白名单扩展不嗅探）
     *
     * @return array<string, array<int, string>>
     */
    protected static function mimeFamilies(): array
    {
        $ooxml = [
            'application/zip',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        $legacyOffice = [
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.ms-office',
            'application/cdfv2',
            'application/x-ole-storage',
        ];

        return [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'avif' => ['image/avif'],
            'bmp' => ['image/bmp'],
            'pdf' => ['application/pdf'],
            'doc' => $legacyOffice,
            'docx' => $ooxml,
            'xls' => $legacyOffice,
            'xlsx' => $ooxml,
            'ppt' => $legacyOffice,
            'pptx' => $ooxml,
            'csv' => ['text/*', 'application/octet-stream'],
            'txt' => ['text/*', 'application/octet-stream'],
            'md' => ['text/*', 'application/octet-stream'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'rar' => ['application/x-rar*', 'application/vnd.rar', 'application/octet-stream'],
            '7z' => ['application/x-7z-compressed', 'application/octet-stream'],
            'mp4' => ['video/mp4'],
            'm4v' => ['video/x-m4v', 'video/mp4'],
            'mov' => ['video/quicktime'],
            'mp3' => ['audio/mpeg', 'audio/mp3'],
            'm4a' => ['audio/mp4', 'audio/x-m4a'],
            'wav' => ['audio/wav', 'audio/x-wav'],
        ];
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
