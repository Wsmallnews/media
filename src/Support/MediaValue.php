<?php

namespace Wsmallnews\Media\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Wsmallnews\Media\Models\Media;

/**
 * Value presenter for attachments: accepts a value-object array, a relative path or a Media model.
 * A path string is looked up in the media table; unknown paths degrade to path-only data.
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
     * Preview URL; appends the resize parameter when the disk defines one. Direct-link disks
     * return their URL, private disks go through the signed file route.
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
     * Whether the disk can be linked directly (public or cloud); a private local disk cannot.
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
     * Download URL with the original filename: the route for records, a direct URL for bare paths.
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
        // Value object first; fall back to the record for keys it does not carry (alt, disk).
        return $this->value[$name] ?? $this->model?->{$name};
    }

    protected function resolveResizeTemplate(string $disk): ?string
    {
        $transforms = (array) Utils::getConfig('transforms', []);
        $disks = (array) ($transforms['disks'] ?? []);
        $providers = (array) ($transforms['providers'] ?? []);

        // Explicit disk mapping first (value is a provider key), then infer from the disk driver.
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
