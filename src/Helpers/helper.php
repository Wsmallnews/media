<?php

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
