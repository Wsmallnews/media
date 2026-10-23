<?php

namespace Wsmallnews\Media\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Wsmallnews\Media\Models\Media;

/**
 * 附件值 presenter：统一渲染入口。
 *
 * 接受三种形态：值对象数组（字段保存值）、相对路径字符串（老字段 / 快照）、Media 模型。
 * string 形态按 path 唯一索引反查素材表补齐元数据，反查不到则最小信息降级。
 */
class MediaValue
{
    protected ?array $value = null;

    protected ?Media $model = null;

    public function __construct(mixed $value = null)
    {
        if ($value instanceof Media) {
            $this->model = $value;
            $this->value = $value->toValue();
        } elseif (is_string($value) && $value !== '') {
            $this->model = static::findByPath($value);
            $this->value = $this->model?->toValue() ?? ['path' => $value];
        } elseif (is_array($value) && ($value['path'] ?? null)) {
            $this->value = $value;
            // Value objects omit fields such as alt and disk, so recover those from the media record.
            $this->model = static::findByPath((string) $value['path']);
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
     * 访问 URL；宽度非空且当前盘配置了变换模板时追加缩放参数。
     * 可直链的盘（public 盘 / 云盘）返回直链；不可直链的盘（私有 local）走 file 路由流式输出。
     */
    public function url(?int $width = null): ?string
    {
        $path = $this->value['path'] ?? null;
        if (! $path) {
            return null;
        }

        $disk = $this->model?->disk ?: Utils::getDisk();

        if (! $this->diskServesDirectly($disk)) {
            if (! $this->model) {
                return null;
            }

            return URL::temporarySignedRoute(
                'sn-media.file',
                now()->addMinutes((int) Utils::getConfig('preview_url_ttl', 10)),
                [
                    'path' => $path,
                    'team_id' => $this->model->team_id ?? 'global',
                ],
            );
        }

        $url = Storage::disk($disk)->url($path);

        $template = $this->resolveResizeTemplate($disk);
        if ($width > 0 && filled($template)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . str_replace('{w}', (string) $width, (string) $template);
        }

        return $url;
    }

    /**
     * 盘是否可直链（public 盘或云盘 url()；私有 local 盘不可）
     */
    protected function diskServesDirectly(string $disk): bool
    {
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config)) {
            return true;
        }

        return Utils::getDiskVisibility($disk) === 'public';
    }

    /**
     * 下载 URL（强制原始文件名）：有素材记录走路由，裸路径降级直链
     */
    public function downloadUrl(): ?string
    {
        if ($this->model) {
            return URL::temporarySignedRoute(
                'sn-media.download',
                now()->addMinutes((int) Utils::getConfig('download_url_ttl', 10)),
                [
                    'media' => $this->model,
                    'team_id' => $this->model->team_id ?? 'global',
                ],
            );
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
        // 值对象优先；缺失键回落素材记录（alt/disk 等不在值对象契约里的字段）
        return $this->value[$name] ?? $this->model?->{$name};
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

    protected static function findByPath(string $path): ?Media
    {
        $modelClass = Utils::getMediaModel();

        return $modelClass::findByPath($path);
    }
}
