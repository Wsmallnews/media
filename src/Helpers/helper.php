<?php

use Illuminate\Contracts\Pagination\Paginator;
use Wsmallnews\Media\Support\MediaValue;

if (! function_exists('media')) {
    /**
     * Attachment presenter: value-object array, relative path or Media model.
     * e.g. media($product->image)->url(400) / ->name / ->downloadUrl().
     */
    function media(mixed $value = null): MediaValue
    {
        return MediaValue::make($value);
    }
}

if (! function_exists('media_url')) {
    /**
     * Shortcut for the preview URL (adds the resize parameter when a width is given).
     */
    function media_url(mixed $value = null, ?int $width = null): ?string
    {
        return MediaValue::make($value)->url($width);
    }
}

if (! function_exists('media_resolve')) {
    /**
     * Prewarm the media cache for a result set before rendering URLs in a loop:
     * one whereIn covers every path held in the given fields. Accepts a paginator
     * or any iterable of models; single-record reads do not need this (lazy
     * resolution already stays query-free on a direct-link active disk).
     */
    function media_resolve(mixed $items, array $fields): void
    {
        $collection = $items instanceof Paginator
            ? $items->getCollection()
            : collect($items);

        $collection->resolveMedia($fields);
    }
}
