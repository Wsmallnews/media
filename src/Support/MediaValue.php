<?php

namespace Wsmallnews\Media\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Wsmallnews\Media\Models\Media;

/**
 * Value presenter for attachments: accepts a value-object array, a relative path or a Media model.
 *
 * Resolution is lazy: building the presenter never queries — only url() (the record's real
 * disk decides direct link vs signed route), downloadUrl() and keys outside the value
 * contract (alt, disk) hit the media table, through the request-scoped MediaResolver cache.
 * Prime a whole result set with media_resolve() / Collection::resolveMedia() and rendering
 * in a loop stays query-free. Unknown paths degrade to path-only data (direct link on the
 * active disk).
 */
class MediaValue
{
    protected ?array $value = null;

    protected ?Media $model = null;

    /** Whether the media record has been resolved (a Media input resolves immediately). */
    protected bool $modelResolved = false;

    public function __construct(mixed $value = null)
    {
        if ($value instanceof Media) {
            $this->model = $value;
            $this->modelResolved = true;
            $this->value = $value->toValue();
        } elseif (is_string($value) && $value !== '') {
            $this->value = ['path' => $value];
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
     * Preview URL; appends the resize parameter when the disk defines one. Direct-link disks
     * return their URL, private disks go through the signed file route.
     *
     * The record's real disk decides the strategy (a value object may live on a non-active
     * disk), so url() resolves through the memoized cache — one lookup at most per presenter,
     * zero after a media_resolve() prewarm.
     */
    public function url(?int $width = null): ?string
    {
        $path = $this->value['path'] ?? null;
        if (! $path) {
            return null;
        }

        $media = $this->media();
        $disk = $media?->disk ?: Utils::getDisk();

        if (! $this->diskServesDirectly($disk)) {
            if (! $media) {
                return null;
            }

            return URL::temporarySignedRoute(
                'sn-media.file',
                now()->addMinutes((int) Utils::getConfig('preview_url_ttl', 10)),
                [
                    'path' => $path,
                    'team_id' => $media->team_id ?? 'global',
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
     * Download URL with the original filename: the signed record route when the media table
     * knows the path (one lazy lookup), a direct URL for unknown bare paths.
     */
    public function downloadUrl(): ?string
    {
        if ($media = $this->media()) {
            return URL::temporarySignedRoute(
                'sn-media.download',
                now()->addMinutes((int) Utils::getConfig('download_url_ttl', 10)),
                [
                    'media' => $media,
                    'team_id' => $media->team_id ?? 'global',
                ],
            );
        }

        return $this->url();
    }

    /**
     * The media record, resolved lazily through the request-scoped cache (memoized).
     */
    protected function media(): ?Media
    {
        if (! $this->modelResolved) {
            $this->model = app(MediaResolver::class)->resolve((string) ($this->value['path'] ?? ''));
            $this->modelResolved = true;
        }

        return $this->model;
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
        return $this->value[$name] ?? $this->media()?->{$name};
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
}
