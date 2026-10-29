<?php

namespace Wsmallnews\Media\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Wsmallnews\Media\Support\MediaValue;

/**
 * Single media field cast: ⇄ MediaValue presenter, over two storage shapes
 * (chosen by the cast parameter — 'json' by default, 'path' for plain string
 * columns such as order snapshot images or spec-value images):
 *
 *     protected $casts = [
 *         'cover'        => MediaCast::class,             // stores {"path": ...} JSON
 *         'relate_image' => MediaCast::class . ':path',   // stores 'dir/file.png'
 *     ];
 *
 * Reading accepts a bare path or a JSON object (MediaValue lookups stay lazy via
 * the request-scoped MediaResolver); writing normalizes every input shape
 * (MediaValue / value-object array / bare path / null) and serializes to the
 * declared shape, dropping path-less dirty values.
 */
class MediaCast implements CastsAttributes
{
    public function __construct(
        protected string $storage = 'json',
    ) {}

    /**
     * @return MediaValue|null
     */
    public function get($model, string $key, $value, array $attributes)
    {
        // A bare path string is only valid raw input for 'path' columns; for 'json'
        // columns it would be a JSON body, so decode first in both cases.
        $decoded = $this->decode($value);

        if ($this->storage === 'path' && $decoded === null && is_string($value) && $value !== '') {
            return MediaValue::make($value);
        }

        return is_array($decoded) && filled($decoded['path'] ?? null) ? MediaValue::make($decoded) : null;
    }

    /**
     * @param  MediaValue|array<string, mixed>|string|null  $value
     */
    public function set($model, string $key, $value, array $attributes)
    {
        $value = $this->normalize($value);

        if ($value === null) {
            return null;
        }

        return $this->storage === 'path' ? $value['path'] : json_encode($value);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function normalize(mixed $value): ?array
    {
        if ($value instanceof MediaValue) {
            $value = $value->toArray();
        }

        if (is_string($value)) {
            $value = $value !== '' ? ['path' => $value] : null;
        }

        return is_array($value) && filled($value['path'] ?? null) ? $value : null;
    }

    protected function decode(mixed $value): mixed
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }
}
