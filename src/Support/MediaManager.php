<?php

namespace Wsmallnews\Media\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Wsmallnews\Media\Exceptions\MediaException;
use Wsmallnews\Media\Models\Media;
use Wsmallnews\Media\Models\MediaDirectory;

class MediaManager
{
    /**
     * 上传并入账（统一上传管道：内容哈希 → 当天判重 → 写盘 → 读宽高 → 记录 sn_media）。
     *
     * 物理路径 = {file_directory}/{Ymd}/{内容md5}.{ext}（内容寻址）：
     * - 当天同内容 = 同 path = 唯一索引命中 → 秒传复用已有记录（含并发撞索引回查）；
     * - 隔天同内容 = 不同日期层 = 独立新文件；
     * - path 生成后永不变（移动目录只改 media.directory_id 逻辑归属）。
     *
     * @param  MediaDirectory|int|null  $directory  逻辑目录（模型/id/null=未归类）
     * @param  string|null  $disk  目标盘（null = 活动盘；私有文件传私有盘名）
     * @param  string|null  $fileDirectory  落盘目录（可带日期；null = config('sn-media.file_directory') + 日期）
     */
    public function upload(UploadedFile $file, MediaDirectory | int | null $directory = null, ?Model $uploader = null, ?int $teamId = null, ?string $disk = null, ?string $fileDirectory = null, ?array $acceptedMimes = null): Media
    {
        if (! $file->isValid()) {
            throw new MediaException('The uploaded file is invalid.');
        }

        $this->validate($file, $acceptedMimes);

        $modelClass = Utils::getMediaModel();
        $disk = $disk ?: Utils::getDisk();
        $this->validateDisk($disk);
        $teamId = $this->resolveTeamId($teamId);
        $ext = $this->extension($file);
        $hash = $this->contentHash($file);
        $path = $this->resolvePath($modelClass, $hash, $ext, $disk, $teamId, $this->resolveFileDirectory($fileDirectory));
        $directoryId = $this->resolveDirectoryId($directory, $teamId);

        // 当天秒传：同内容同 path 已有记录 → 直接复用（磁盘写入也跳过）
        $existing = $modelClass::findByPath($path, $teamId);
        if ($existing && $existing->disk === $disk && Storage::disk($disk)->exists($existing->path)) {
            return $existing;
        }

        $stream = @fopen((string) $file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new MediaException('Unable to read the uploaded file.');
        }

        try {
            $written = Storage::disk($disk)->put($path, $stream, [
                'visibility' => Utils::getDiskVisibility($disk),
            ]);
        } finally {
            fclose($stream);
        }

        if (! $written) {
            throw new MediaException('Unable to store the uploaded file.');
        }

        [$width, $height] = $this->readImageSize($file);
        $uploader = $uploader ?? $this->resolveUploader();

        try {
            return $modelClass::create([
                'team_id' => $teamId,
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
            $existing = $modelClass::findByPath($path, $teamId);
            if ($existing && $existing->disk === $disk) {
                // The other request owns the same deterministic path. Never
                // delete it here: doing so would remove the file referenced by
                // the record that won the race.
                return $existing;
            }

            // If another request inserts the row immediately after the lookup,
            // leaving the file is safer than deleting a now-referenced object.
            if (! $modelClass::query()->where('path', $path)->exists()) {
                Storage::disk($disk)->delete($path);
            }

            throw $e;
        }
    }

    /**
     * 上传并直接返回值对象（字段保存值）
     *
     * @return array{path: string, name: string, mime: string, size: int, width: int|null, height: int|null}
     */
    public function uploadValue(UploadedFile $file, MediaDirectory | int | null $directory = null, ?Model $uploader = null, ?int $teamId = null, ?string $disk = null, ?string $fileDirectory = null, ?array $acceptedMimes = null): array
    {
        return $this->upload($file, $directory, $uploader, $teamId, $disk, $fileDirectory, $acceptedMimes)->toValue();
    }

    /**
     * 只写盘、不入账（前台 / 业务字段专用）。
     *
     * 与 upload() 的区别只有最后一跳：不创建 sn_media 记录，因此不会出现在素材库里，
     * 也没有目录归属 / 上传者 / 团队归属可言。校验（扩展名白名单、MIME 嗅探、大小）
     * 与路径规则（内容寻址 {Ymd}/{md5}.{ext}）完全一致——不因为是「不进库」就放松。
     *
     * 适用：访客提交的表单附件、评论图片这类「只属于这条业务记录」的文件。
     * 不入库 = 没有统一的引用计数，业务侧自己负责这条记录的清理（换图/删记录时删文件）。
     *
     * @return array{path: string, name: string, mime: string, size: int, width: int|null, height: int|null}
     */
    public function store(UploadedFile $file, ?string $disk = null, ?string $fileDirectory = null, ?array $acceptedMimes = null): array
    {
        if (! $file->isValid()) {
            throw new MediaException('The uploaded file is invalid.');
        }

        $this->validate($file, $acceptedMimes);

        $disk = $disk ?: Utils::getDisk();
        $this->validateDisk($disk);

        $ext = $this->extension($file);
        $path = $this->resolvePath(Utils::getMediaModel(), $this->contentHash($file), $ext, $disk, null, $this->resolveFileDirectory($fileDirectory));

        // 内容寻址天然幂等：同内容同路径已存在就不用再写一遍
        if (! Storage::disk($disk)->exists($path)) {
            $stream = @fopen((string) $file->getRealPath(), 'rb');

            if ($stream === false) {
                throw new MediaException('Unable to read the uploaded file.');
            }

            try {
                $written = Storage::disk($disk)->put($path, $stream, [
                    'visibility' => Utils::getDiskVisibility($disk),
                ]);
            } finally {
                fclose($stream);
            }

            if (! $written) {
                throw new MediaException('Unable to store the uploaded file.');
            }
        }

        [$width, $height] = $this->readImageSize($file);

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName() ?: $path,
            'mime' => $this->sniff($file) ?: ((string) ($file->getMimeType() ?: 'application/octet-stream')),
            'size' => (int) ($file->getSize() ?: 0),
            'width' => $width,
            'height' => $height,
        ];
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
    protected function validate(UploadedFile $file, ?array $acceptedMimes = null): void
    {
        $maxSize = Utils::getConfig('max_size');
        if ($maxSize && $file->getSize() > ((int) $maxSize) * 1024) {
            throw new MediaException('File exceeds the maximum allowed size.');
        }

        $ext = $this->extension($file);

        $mimes = Utils::getConfig('mimes');
        $allowedExtensions = is_array($mimes) ? array_map('strtolower', $mimes) : [];

        if ($allowedExtensions !== [] && ! in_array($ext, $allowedExtensions, true)) {
            throw new MediaException('File type is not allowed.');
        }

        $executableExtensions = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar'];

        if (in_array($ext, $executableExtensions, true)) {
            throw new MediaException('File type is not allowed.');
        }

        // 嗅探一致性：扩展名对应的真实 MIME 家族（finfo 内容嗅探必须命中，防改后缀伪装）。
        // The MIME type must come from the temporary file contents, not the client-provided MIME header.
        $sniffed = $this->sniff($file);
        $allowedMimes = static::mimeFamilies()[$ext] ?? null;

        if ($allowedMimes !== null && $sniffed === null) {
            throw new MediaException('Unable to determine the file content type.');
        }

        if ($sniffed !== null && $allowedMimes !== null) {
            $matchesExtension = collect($allowedMimes)->contains(function (string $allowed) use ($sniffed): bool {
                return str_ends_with($allowed, '*')
                    ? str_starts_with($sniffed, substr($allowed, 0, -1))
                    : $sniffed === $allowed;
            });

            if (! $matchesExtension) {
                throw new MediaException('File content does not match its extension.');
            }
        }

        if ($acceptedMimes !== null && $acceptedMimes !== []) {
            if ($sniffed === null) {
                throw new MediaException('Unable to determine the file content type.');
            }

            $isAccepted = collect($acceptedMimes)->contains(function (mixed $pattern) use ($sniffed): bool {
                if (! is_string($pattern)) {
                    return false;
                }

                $pattern = strtolower($pattern);

                return str_ends_with($pattern, '/*')
                    ? str_starts_with($sniffed, substr($pattern, 0, -1))
                    : $sniffed === $pattern;
            });

            if (! $isAccepted) {
                throw new MediaException('File content does not match the field requirements.');
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
        if (! str_starts_with((string) $this->sniff($file), 'image/')) {
            return [null, null];
        }

        $info = @getimagesize((string) $file->getRealPath());

        return $info ? [(int) $info[0], (int) $info[1]] : [null, null];
    }

    /**
     * 面板租户（控制台 / 队列等无面板上下文时为 null）
     */
    protected function resolveTeamId(?int $requestedTeamId = null): ?int
    {
        $tenant = rescue(fn () => current_tenant(), null, false);

        if ($requestedTeamId !== null && $tenant && (int) $tenant->getKey() !== $requestedTeamId) {
            throw new MediaException('The selected tenant is not available.');
        }

        return $tenant?->getKey() ?? $requestedTeamId;
    }

    protected function resolveUploader(): ?Model
    {
        return rescue(fn () => filament()?->auth()->user() ?? Auth::user(), null, false);
    }

    protected function validateDisk(string $disk): void
    {
        if (! Utils::isDiskAllowed($disk) || ! is_array(config("filesystems.disks.{$disk}"))) {
            throw new MediaException('The selected storage disk is not allowed.');
        }
    }

    protected function resolveDirectoryId(MediaDirectory | int | null $directory, ?int $teamId): ?int
    {
        if ($directory === null || $directory === 0) {
            return null;
        }

        $directoryId = $directory instanceof MediaDirectory ? (int) $directory->getKey() : $directory;
        $query = MediaDirectory::query()->whereKey($directoryId);

        if ($teamId === null) {
            $query->scopeTenant();
        } else {
            $query->where('team_id', $teamId);
        }

        $ownedDirectory = $query->first();

        if (! $ownedDirectory) {
            throw new MediaException('The selected directory is not available.');
        }

        return (int) $ownedDirectory->getKey();
    }

    /**
     * 内容寻址路径：{已带日期的目录}/{内容 md5}.{ext}
     *
     * 日期层由 resolveFileDirectory() 一次性拼好（Utils::withDate 幂等），这里不再加。
     */
    protected function resolvePath(string $modelClass, string $hash, string $ext, string $disk, ?int $teamId, string $directory): string
    {
        $path = $directory . '/' . $hash . '.' . $ext;
        $existing = $modelClass::query()->where('path', $path)->first();

        if (! $existing || ((int) $existing->team_id === (int) $teamId && $existing->disk === $disk)) {
            return $path;
        }

        $scope = $teamId === null ? 'global' : 'team-' . $teamId;

        return $directory . '/' . $hash . '-' . $scope . '-' . substr(sha1($disk), 0, 8) . '.' . $ext;
    }

    /**
     * 归一落盘目录（**带日期**）。
     *
     * 幂等判断只有一处（Utils::withDate），所以调用处传什么都不用管：
     * - null → config('sn-media.file_directory') + 日期；
     * - 'sn/product/products' → 补日期；
     * - Utils::getFileDirectory('products')（已带日期）→ 原样返回，不会变成两层日期。
     *
     * 中间还做逐段校验：拒绝空段 / . / .. ，杜绝路径穿越。
     */
    protected function resolveFileDirectory(?string $directory): string
    {
        $directory = trim($directory ?: (string) Utils::getConfig('file_directory', 'sn/media'), '/');

        if ($directory === '' || preg_match('/^[A-Za-z0-9._\/-]+$/', $directory) !== 1) {
            throw new MediaException('The upload directory is invalid.');
        }

        foreach (explode('/', $directory) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new MediaException('The upload directory is invalid.');
            }
        }

        return Utils::withDate($directory);
    }
}
