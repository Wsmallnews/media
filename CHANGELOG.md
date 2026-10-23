# Changelog

All notable changes to `wsmallnews/media` will be documented in this file.

## 1.0.0 - unreleased

首个版本。

### 素材库

- 左侧目录树：无限层级、拖拽排序、行内操作（重命名 / 删除 / 新建子目录），⋯ 菜单在侧栏内就地展开。
- 类型筛选（图片 / 视频 / 音频 / 文档 / 其它）+ 未归类、关键词搜索、分页；计数在单次请求内缓存，不做全表统计。
- 上传 / 新建目录 / 重命名 / 删除 / 下载（强制以原始文件名返回）。
- 多租户隔离（`team_id`）；公开盘直链、私有盘经签名路由流式输出。

### 表单字段

- `MediaPicker`：从素材库挑选。单值 / 多值、拖拽排序、网格与列表两种形态、比例与固定尺寸、触发器图标与文案、弹层方向 / 宽度 / 初始视图 / 排序、`storePath()` 只存 path。
- `FilePicker`：直接上传。文件落宿主表单的附件槽，支持私有盘与 `storageOnly()`（只写盘、不入素材库）。
- 两个字段共用同一套选项与渲染：`Filament\Forms\Concerns\InteractsWithMediaField` + `resources/views/components/filament/forms/*`，差别只在「值从哪来」。

### 上传

- 三个入口（素材页 / 选择弹窗 / 字段）只有一条链路：Livewire 上传 → `Support\Concerns\UploadsMedia` → `MediaManager`。不提供独立的上传 HTTP 路由。
- 进度条统一为 `x-sn-media::filament.components.upload-progress`（`overlay` / `trigger` 两种形态），进度只取自 `$wire.upload*` 的 progress 回调。
- 单文件上限取 PHP ini / Livewire 规则 / 包配置三者的最小值；超限在前端即时拒绝并提示，不发上传请求。
- 成功与失败通知在三个入口保持一致。

### 其它

- 值对象 `{path, name, mime, size, width, height}`；`media()` presenter 兼容旧的纯路径字符串字段。
- `media_url($value, $width)` 按当前盘追加缩放参数（OSS / COS / 七牛 / OBS / S3），本地盘出原图。
- 官方演示页 `MediaDemo`（`/admin/media-demo`）：覆盖两个字段的全部能力，含 Repeater 嵌套。默认**不注册**，本地验证时在 `config/sn-media.php` 的 `panel_register.pages` 里放开 `MediaDemo::class` 与文件顶部的 `use` 即可。
