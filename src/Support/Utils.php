<?php

namespace Wsmallnews\Media\Support;


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
