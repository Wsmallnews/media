<?php

namespace Wsmallnews\Media\Support;

use Illuminate\Database\Eloquent\Builder;

class Utils
{
    /**
     * 读取 sn-media 配置（dot notation）
     */
    public static function getConfig(?string $name = null, mixed $default = null): mixed
    {
        if (is_null($name)) {
            return config('sn-media');
        }

        return config('sn-media.' . $name, $default);
    }

    /**
     * 活动盘
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

        // An empty allow-list means that any disk explicitly configured by the
        // host application may be used. The route that accepts disk overrides
        // is signed, while applications that need a narrower policy can set
        // `allowed_disks` explicitly.
        return is_array(config("filesystems.disks.{$disk}"));
    }

    /**
     * 文件目录（**唯一**的日期入口）。
     *
     * 签名与返回值与各业务包的 Utils::getFileDirectory() 完全一致：{file_directory}/{type}/{Ymd}。
     * Filament 原生 FileUpload 的 directory() 只是原样 storeAs，**不会补日期**，所以要给
     * `->directory()` 用的目录必须从这里拿。
     *
     * 落盘侧（MediaManager）同样走这里，因此同一份目录重复经过不会再叠一层日期——
     * 幂等由 withDate() 判断，调用处不需要自己判断「带没带日期」。
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
     * 幂等补日期：已以「合法的 8 位日期」结尾则原样返回。
     *
     * 用 checkdate() 而不是裸 \d{8}：目录名本身可能是 8 位数字（如 sn/media/12345678），
     * 裸正则会把这种目录误判成已带日期，导致真正的日期层永远不出现。
     *
     * 日期走 now() 而不是原生 date()：返回值与各包的 date('Ymd') 完全一致，但能被
     * 测试的 travel() 穿越——跨天判重（当天秒传 / 隔天新建）是内容寻址的核心行为，必须可测。
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
     * 人类可读的文件大小（B / KB / MB / GB）。
     *
     * 纯换算，只认字节数：模型层有 `$media->size_for_humans` 这个便利属性，
     * 但手里只有字节数时（UploadedFile、值对象数组、日志）用这个，
     * 两边共用同一份实现，不会出现第二套换算规则。
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
     * 可替换模型类名
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
     * 面板注册配置
     */
    public static function getPanelRegister(?string $type = null): mixed
    {
        $registers = self::getConfig('panel_register', []);

        return is_null($type) ? $registers : ($registers[$type] ?? []);
    }
}
