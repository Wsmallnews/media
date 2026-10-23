<?php

use Wsmallnews\Media\Filament\Pages\MediaPage;
use Wsmallnews\Media\Models\Media;

// 演示页默认不注册（发布版面板里不带）。本地要验证字段改动时，把本行与下方
// panel_register.pages 里的 `// MediaDemo::class,` 一起放开。
// use Wsmallnews\Media\Filament\Pages\MediaDemo;

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

    'allowed_disks' => [],

    /*
    |--------------------------------------------------------------------------
    | 上传可见性
    */
    'visibility' => 'public',

    /*
    |--------------------------------------------------------------------------
    | 默认文件目录
    |--------------------------------------------------------------------------
    | 落盘根目录，不带尾斜杠（尾斜杠即使写了也会被容忍，但统一标准是不写）。
    | 实际目录由 Utils::getFileDirectory() 补上日期层：{file_directory}/{Ymd}/{hash}.{ext}。
    | 调用处可按模块覆盖（如 sn/product/products、sn/cms/contents）。
    | 名称与其他业务包保持一致（file_directory），语义也一致：Filament 原生 FileUpload
    | 的 ->directory() 直接吃本方法的返回值。
    */
    'file_directory' => 'sn/media',

    /*
    |--------------------------------------------------------------------------
    | 上传限制
    |--------------------------------------------------------------------------
    | max_size：KB，null = 不限（受 PHP / Livewire 上传上限约束）。
    | mimes：扩展名白名单（小写），null = 不限。PHP 可执行扩展名始终禁止；SVG / HTML 等
    | 可执行主动内容默认不放开（SVG 在同源下可执行脚本），需要时自行加入并自担风险。
    | 另有 MIME 嗅探一致性校验：改后缀伪装（如 .jpg 实为 PHP 文本）会被拒绝。
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

    'preview_url_ttl' => 10,

    /*
    |--------------------------------------------------------------------------
    | 素材选择弹层（picker）形态
    |--------------------------------------------------------------------------
    | 刻意不放在配置里：弹层形态是字段自己的外观，和 multiple()/ratio() 一样属于调用处的选择，
    | 全局配置会和「一个字段一个字段地设」互相打架。
    | 默认值写在 MediaPicker 上（居中 modal + Width::SevenExtraLarge），需要时链式覆盖：
    |   MediaPicker::make('cover')->pickerSlideOver(SlideOverPosition::End)
    |   MediaPicker::make('cover')->pickerWidth(Width::FiveExtraLarge)
    */

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
    |--------------------------------------------------------------------------
    | pages：MediaPlugin 在 register() 时注册进面板的页面。
    */
    'panel_register' => [
        'global_default' => [
            'navigation_group' => 'sn-media::media.global_default.navigation_group',
        ],
        'pages' => [
            MediaPage::class,

            /*
             * 官方演示页：把 MediaPicker / FilePicker 的全部能力摆在一起
             * （单值 / 多值 / 上限 / 网格与列表 / 弹窗形态 / path-only / 私有盘 /
             * storageOnly / Repeater 嵌套 …），只用于体验与抄写法，不落任何业务表。
             *
             * 默认不注册：发布版面板里不带演示页。本地要验证字段改动时，
             * 把下面这行与文件顶部的 `use ...\MediaDemo;` 一起放开即可。
             */
            // MediaDemo::class,
        ],
    ],

];
