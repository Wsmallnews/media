<?php

use Wsmallnews\Media\Models\Media;

// MediaPage 由宿主 panel 原生注册（AdminPanelProvider pages）；MediaDemo 演示页发布版不注册。

return [

    /*
    |--------------------------------------------------------------------------
    | Active disk
    |--------------------------------------------------------------------------
    | Every upload lands here. Changing disk / domain / CDN only touches this
    | value — relative paths stored in business columns stay valid.
    */
    'disk' => env('SN_MEDIA_DISK', 'public'),

    'allowed_disks' => [],

    /*
    |--------------------------------------------------------------------------
    | Upload visibility
    */
    'visibility' => 'public',

    /*
    |--------------------------------------------------------------------------
    | Default file directory
    |--------------------------------------------------------------------------
    | Root of the on-disk path, without trailing slash. A date segment is
    | appended automatically: {file_directory}/{Ymd}/{hash}.{ext}.
    */
    'file_directory' => 'sn/media',

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    | max_size: KB, null = unlimited (still bounded by PHP / Livewire).
    | mimes: lowercase extension whitelist, null = unlimited. Executable
    | extensions are always rejected; SVG/HTML stay closed by default because
    | they can run scripts same-origin. MIME sniffing rejects spoofed suffixes.
    */
    'max_size' => 20 * 1024,

    'mimes' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp',
        'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv',
        'txt', 'md',
        'zip', 'rar', '7z',
        'mp4', 'm4v', 'mov', 'mp3', 'm4a', 'wav',
    ],

    /*
    |--------------------------------------------------------------------------
    | URL transforms (thumbnail params)
    |--------------------------------------------------------------------------
    | providers: resize template per object storage, {w} = width, appended as
    | query string; null = serve the original.
    | disks: disk name => provider key, takes precedence over driver inference
    | (OBS reuses the s3 driver, so it must be mapped here).
    | Resolution: disks[disk] -> providers[driver] -> original.
    */
    'transforms' => [
        'providers' => [
            'oss' => ['resize' => 'x-oss-process=image/resize,w_{w}'],
            'cos' => ['resize' => 'imageMogr2/thumbnail/{w}x'],
            'qiniu' => ['resize' => 'imageView2/2/w/{w}'],
            'obs' => ['resize' => 'x-image-process=image/resize,m_lfit,w_{w}'],
            's3' => null,
        ],
        'disks' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Download
    |--------------------------------------------------------------------------
    | Lifetime (minutes) of the temporary URL used to force the original filename.
    */
    'download_url_ttl' => 10,

    'preview_url_ttl' => 10,

    /*
    |--------------------------------------------------------------------------
    | Replaceable models
    */
    'models' => [
        'media' => Media::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel registration
    |--------------------------------------------------------------------------
    | pages: registered by MediaPlugin::register().
    */
    /**
     * 本包组件不消费 scope：引入无需声明 scopeable。
     *
     * 资源清单（默认解开即注册；内存注入 pages([...]) 对同类覆盖）。
     */

];
