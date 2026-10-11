<?php

use Wsmallnews\Media\Filament\Pages\MediaPage;
use Wsmallnews\Media\Models\Media;

return [

    /*
    |--------------------------------------------------------------------------
    | 活动盘
    |--------------------------------------------------------------------------
    | 所有上传落此盘（统一入口 => 全站文件同一活动盘）。换盘 / 换域名 / 上 CDN
    | 只改这一处配置，业务字段里的相对路径不变，全站 URL 跟随。
    | 华为云 OBS 接入：driver 填 s3（OBS 提供 S3 兼容 API），endpoint 指向
    | https://obs.{region}.myhuaweicloud.com，再把 transforms.disks 里加 '盘名' => 'obs'。
    */
    'disk' => env('SN_MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | 上传可见性
    */
    'visibility' => 'public',

    /*
    |--------------------------------------------------------------------------
    | 默认目录前缀
    |--------------------------------------------------------------------------
    | 调用处可按模块覆盖（如 sn/shop/product、sn/cms/banner）。
    */
    'directory' => 'sn/media',

    /*
    |--------------------------------------------------------------------------
    | 上传限制
    |--------------------------------------------------------------------------
    | max_size：KB，null = 不限（受 PHP / Livewire 上传上限约束）。
    | mimes：扩展名白名单（小写），null = 不限。
    */
    'max_size' => 20 * 1024,

    'mimes' => null,

    /*
    |--------------------------------------------------------------------------
    | URL 变换层（缩略图参数）
    |--------------------------------------------------------------------------
    | providers：主流对象存储的缩放模板，{w} 为宽度占位，作为 query string 追加；
    | null = 该 provider 出原图（Amazon S3 无原生图片处理，接 CloudFront+Lambda 后自行配置）。
    | disks：盘名 => provider 键，优先于按 driver 推断（OBS 复用 s3 driver，靠这里与 AWS 区分）。
    | 解析顺序：disks[盘名] → providers[盘 driver] → 出原图。
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
    | 下载
    |--------------------------------------------------------------------------
    | 云盘 temporaryUrl（强制原始文件名下载）的有效期，分钟。
    */
    'download_url_ttl' => 10,

    /*
    |--------------------------------------------------------------------------
    | 可替换模型
    */
    'models' => [
        'media' => Media::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | 面板注册
    */
    'panel_register' => [
        'global_default' => [
            'navigation_group' => 'sn-media::media.global_default.navigation_group',
        ],
        'pages' => [
            MediaPage::class,
        ],
    ],

];
