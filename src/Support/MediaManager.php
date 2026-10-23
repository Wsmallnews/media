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
     * Upload and register: hash content, dedupe same day, write, read dimensions, create the row.
     *
     * Path is content-addressed ({file_directory}/{Ymd}/{md5}.{ext}) and never changes: same
     * content on the same day reuses the record, another day creates a new file.
     *
     * @param  MediaDirectory|int|null  $directory  Logical directory (model/id/null = uncategorized)
     * @param  string|null  $disk  Target disk (null = active disk; pass a private disk name for private files)
     * @param  string|null  $fileDirectory  Target directory (may carry a date; null = config + date)
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

        // Same content, same day: reuse the record and skip the disk write.
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
            // Concurrent upload hit the unique path index: reuse the winner's record and keep its
            // file. A row may land right after the lookup, so delete ours only if it is unreferenced.
            $existing = $modelClass::findByPath($path, $teamId);
            if ($existing && $existing->disk === $disk) {
                return $existing;
            }

            if (! $modelClass::query()->where('path', $path)->exists()) {
                Storage::disk($disk)->delete($path);
            }

            throw $e;
        }
    }

    /**
     * Upload and return the value object stored by the field.
     *
     * @return array{path: string, name: string, mime: string, size: int, width: int|null, height: int|null}
     */
    public function uploadValue(UploadedFile $file, MediaDirectory | int | null $directory = null, ?Model $uploader = null, ?int $teamId = null, ?string $disk = null, ?string $fileDirectory = null, ?array $acceptedMimes = null): array
    {
        return $this->upload($file, $directory, $uploader, $teamId, $disk, $fileDirectory, $acceptedMimes)->toValue();
    }

    /**
     * Write to disk only, without an sn_media record.
     *
     * Same validation and path rules as upload(); with no record there is no reference counting,
     * so the caller owns cleanup.
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

        // Content addressing is idempotent: an existing path needs no second write.
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
     * Content md5 for addressing; a random string when the file cannot be read.
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
     * Size, extension allow-list and sniffed MIME must all agree.
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

        // The MIME must come from the file contents, never from the client-provided header;
        // a whitelisted extension whose family does not match is a renamed file.
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
     * Real MIME via finfo; null when it cannot be determined.
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
     * Extension => accepted sniffed MIME families ('text/*' prefix wildcards allowed).
     * Extensions missing from the map are not sniffed.
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
     * Panel tenant, null outside a panel context (console, queue).
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
     * Content-addressed path: {dated directory}/{content md5}.{ext}.
     *
     * The date layer is added by resolveFileDirectory(); a suffix is appended only when the
     * path is already owned by another team or disk.
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
     * Normalise the target directory and append the date (Utils::withDate is idempotent, so a
     * dated directory is returned as is). Rejects empty, '.' and '..' segments to block traversal.
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
