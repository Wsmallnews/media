# wsmallnews/media

Wsmallnews 生态的素材管理扩展包（Laravel + Filament）。

## 核心概念

- **上传即入库**：所有上传走同一 `MediaManager` 管道（写盘 → 读宽高 → 记录 `sn_media` → 返回值对象），没有"黑户"文件。
- **path 是文件唯一身份证**：相对路径生成后不可变（不移动、不改存储名），"重命名"只改显示名 `name` 列。
- **删除只删记录**：物理文件永不删除；无引用扫描、无 prune。
- **值对象契约**：业务字段保存 `{path, name, mime, size, width, height}`（多值字段是对象数组，数组序即排序），渲染零查询；`media()` presenter 兼容纯路径字符串老字段。
- **URL 变换层**：`media_url($value, 400)` 按当前盘追加缩放参数（阿里云 OSS / 腾讯云 COS / 七牛 / 华为云 OBS / Amazon S3），本地盘出原图；无预生成、无队列。
- **多格式**：图片 / 视频 / 音频 / PDF / Office / 压缩包 / 任意文件；宽高仅图片计算。
- **租户隔离**：`team_id`；业务维度（商城 / 门店）用 path 目录前缀表达。

## 安装

```bash
composer require wsmallnews/media
php artisan media:install
```

## 使用

```php
use Wsmallnews\Media\Filament\Forms\FilePicker;
use Wsmallnews\Media\Filament\Forms\MediaPicker;

// 表单字段：单值（商品主图）
MediaPicker::make('image')->directory('sn/shop/product')

// 多值 + 排序（商品轮播）
MediaPicker::make('gallery')->multiple()->directory('sn/shop/product')

// 长方形外观（导航 banner）
MediaPicker::make('banner')->ratio(3)->acceptImages()

// 仅 PDF（证书）
MediaPicker::make('certificate')->acceptedMimes(['application/pdf'])

// 直接上传（不进素材库：文件落表单附件槽，随表单提交）
FilePicker::make('proof')->directory('sn/shop/contracts')->acceptedMimes(['application/pdf'])
```

```blade
{{-- 渲染：presenter 统一入口 --}}
<img src="{{ media($product->image)->url(400) }}" alt="{{ media($product->image)->alt }}">

{{-- 下载（强制原始文件名） --}}
<a href="{{ media($award->certificate)->downloadUrl() }}">下载</a>

{{-- 老的纯路径字符串字段同样支持 --}}
<img src="{{ media_url($options['icon_src'], 64) }}">
```

## 素材库与演示页

`MediaPlugin` 会把素材管理页注册进面板（导航分组等见 `config/sn-media.php` 的 `panel_register`）。

包内另带一个官方演示页 `MediaDemo`（slug `media-demo`），把 `MediaPicker` / `FilePicker` 的全部能力摆在同一页，用来体验与抄写法。它不落任何业务表，默认**不注册**：把 `config/sn-media.php` 中 `panel_register.pages` 里的 `// MediaDemo::class,` 与文件顶部对应的 `use` 一起放开即可。

## License

MIT
