<?php

namespace Wsmallnews\Media\Support;

use Illuminate\Support\Facades\Storage;
use Wsmallnews\Media\Models\Media;

/**
 * 附件值 presenter：统一渲染入口。
 *
 * 接受三种形态：值对象数组（字段保存值）、相对路径字符串（老字段 / 快照）、Media 模型。
 * string 形态按 path 唯一索引反查素材表补齐元数据（请求级缓存，反查不到则最小信息降级）。
 */
class MediaValue
{
    /** @var array<string, Media|null> path => model 请求级缓存 */
    protected static array $pathCache = [];

    protected ?array $value = null;

    protected ?Media $model = null;

    public function __construct(mixed $value = null)
    {
        if ($value instanceof Media) {
            $this->model = $value;
            $this->value = $value->toValue();
        } elseif (is_string($value) && $value !== '') {
            $this->model = static::findByPathCached($value);
            $this->value = $this->model?->toValue() ?? ['path' => $value];
        } elseif (is_array($value) && ($value['path'] ?? null)) {
            $this->value = $value;
        }
    }

    public static function make(mixed $value = null): self
    {
        return new self($value);
    }

    public function exists(): bool
    {
        return filled($this->value['path'] ?? null);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) ($this->value['mime'] ?? ''), 'image/');
    }

    /**
     * 访问 URL；宽度非空且当前盘配置了变换模板时追加缩放参数
     */
    public function url(?int $width = null): ?string
    {
        $path = $this->value['path'] ?? null;
        if (! $path) {
            return null;
        }

        $disk = $this->model?->disk ?: Utils::getDisk();
        $url = Storage::disk($disk)->url($path);

        $template = $this->resolveResizeTemplate($disk);
        if ($width > 0 && filled($template)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . str_replace('{w}', (string) $width, (string) $template);
        }

        return $url;
    }

    /**
     * 下载 URL（强制原始文件名）：有素材记录走路由，裸路径降级直链
     */
    public function downloadUrl(): ?string
    {
        if ($this->model) {
            return route('sn-media.download', ['media' => $this->model]);
        }

        return $this->url();
    }

    /**
     * @return array{path: string, name: string, mime: string, size: int, width: int|null, height: int|null}|null
     */
    public function toArray(): ?array
    {
        return $this->value;
    }

    public function __get(string $name): mixed
    {
        return $this->value[$name] ?? null;
    }

    protected function resolveResizeTemplate(string $disk): ?string
    {
        $transforms = (array) Utils::getConfig('transforms', []);
        $disks = (array) ($transforms['disks'] ?? []);
        $providers = (array) ($transforms['providers'] ?? []);

        // 盘名映射优先（值为 provider 键），其次按盘 driver 推断
        $providerKey = $disks[$disk] ?? config("filesystems.disks.{$disk}.driver");
        $provider = is_string($providerKey) ? ($providers[$providerKey] ?? null) : null;

        return is_array($provider) ? ($provider['resize'] ?? null) : null;
    }

    protected static function findByPathCached(string $path): ?Media
    {
        if (! array_key_exists($path, static::$pathCache)) {
            static::$pathCache[$path] = Media::findByPath($path);
        }

        return static::$pathCache[$path];
    }
}
