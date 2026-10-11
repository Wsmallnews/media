<?php

use Wsmallnews\Media\Support\MediaValue;

if (! function_exists('media')) {
    /**
     * 附件值 presenter：接受值对象数组 / 相对路径字符串 / Media 模型。
     * 用法：media($product->image)->url(400) / ->name / ->width / ->downloadUrl()。
     */
    function media(mixed $value = null): MediaValue
    {
        return MediaValue::make($value);
    }
}

if (! function_exists('media_url')) {
    /**
     * 附件访问 URL 快捷方式（宽度非空时按当前盘追加缩放参数）。
     */
    function media_url(mixed $value = null, ?int $width = null): ?string
    {
        return MediaValue::make($value)->url($width);
    }
}
