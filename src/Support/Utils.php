<?php

namespace Wsmallnews\Media\Support;

use Illuminate\Database\Eloquent\Builder;

class Utils
{
    /**
     * Read sn-media config (dot notation).
     */
    public static function getConfig(?string $name = null, mixed $default = null): mixed
    {
        if (is_null($name)) {
            return config('sn-media');
        }

        return config('sn-media.' . $name, $default);
    }

    /**
     * Active disk.
     */
    public static function getDisk(): string
    {
        return (string) self::getConfig('disk', 'public');
    }

    public static function getDiskVisibility(string $disk): string
    {
        $diskConfig = config("filesystems.disks.{$disk}", []);

        if (is_array($diskConfig) && is_string($diskConfig['visibility'] ?? null)) {
            return $diskConfig['visibility'];
        }

        if ($disk === self::getDisk()) {
            return (string) self::getConfig('visibility', 'public');
        }

        return ($diskConfig['driver'] ?? null) === 'public' ? 'public' : 'private';
    }

    public static function isDiskAllowed(string $disk): bool
    {
        $allowed = self::getConfig('allowed_disks', []);

        if (is_array($allowed) && $allowed !== []) {
            return in_array($disk, array_values(array_filter($allowed, 'is_string')), true);
        }

        // An empty allow-list accepts any disk configured by the host app; the disk is always
        // resolved server-side, so no client payload can override it.
        return is_array(config("filesystems.disks.{$disk}"));
    }

    /**
     * Dated file directory: {file_directory}/{type}/{Ymd}.
     *
     * Native FileUpload::directory() stores the path as given and never appends a date, so
     * anything passed to it must come from here.
     */
    public static function getFileDirectory(?string $type = null): string
    {
        $directory = rtrim((string) self::getConfig('file_directory', 'sn/media'), '/');

        if (filled($type)) {
            $directory .= '/' . trim((string) $type, '/');
        }

        return static::withDate($directory);
    }

    /**
     * Append the Ymd date layer unless it is already there.
     *
     * checkdate() not /\d{8}/: an 8-digit directory name would pass the bare regex.
     * now() not date(): same-day dedupe must stay reachable from travel().
     */
    public static function withDate(string $directory): string
    {
        $directory = rtrim($directory, '/');

        if (preg_match('#/(\d{8})$#', $directory, $matches) === 1
            && checkdate((int) substr($matches[1], 4, 2), (int) substr($matches[1], 6, 2), (int) substr($matches[1], 0, 4))) {
            return $directory;
        }

        return $directory . '/' . now()->format('Ymd');
    }

    /**
     * Human-readable size (B / KB / MB / GB); also backs Media::sizeForHumans.
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1073741824, 2) . ' GB';
    }

    public static function scopeMediaQueryToTeam(Builder $query, ?string $teamId): Builder
    {
        if ($teamId === null) {
            return $query->scopeTenant();
        }

        if ($teamId === 'global') {
            return $query->whereNull('team_id');
        }

        if (! ctype_digit($teamId) || (int) $teamId < 1) {
            abort(404);
        }

        return $query->where('team_id', (int) $teamId);
    }

    /**
     * Replaceable model class name.
     */
    public static function getModel(string $name, bool $shouldException = true): ?string
    {
        $model = self::getConfig("models.{$name}");

        if (! $model && $shouldException) {
            throw new \RuntimeException("Media model [{$name}] is not configured.");
        }

        return $model;
    }

    public static function getMediaModel(): string
    {
        return (string) self::getModel('media');
    }

    /**
     * Panel registration config.
     */
    public static function getPanelRegister(?string $type = null): mixed
    {
        $registers = self::getConfig('panel_register', []);

        return is_null($type) ? $registers : ($registers[$type] ?? []);
    }
}
