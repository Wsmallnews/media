## 素材包（wsmallnews/media）

`wsmallnews/media` 是 Filament 素材库插件：素材管理页 + `MediaPicker` / `FilePicker` 字段 + 嵌套集目录树。命名空间根 `Wsmallnews\Media`，视图前缀 `sn-media`，配置 `config/sn-media.php`。

### 三条不可动摇的契约

1. **值对象**：业务字段存 `{path, name, mime, size, width, height}`，多值是对象数组、数组序即排序，渲染零查询。纯 path 字符串（老字段）由 `media()` presenter 兼容。
2. **path 是唯一身份证**：内容寻址 `{file_directory}/{Ymd}/{md5}.{ext}`，生成后永不变；「重命名」只改 `name`，托目录只改 `directory_id`。
3. **删除只删记录**：物理文件永不删除，没有引用扫描、没有 prune。业务侧要清理就自己删文件（`storeOnly` 的文件更是只有业务侧知道）。

### 文件大小只有三处实现，不要加第四处

| 场景 | 用什么 |
|---|---|
| Blade 里手上有模型 | `$media->size_for_humans` |
| 只有字节数（`UploadedFile`、值对象、日志/通知） | `Utils::formatBytes((int) $bytes)` |
| 前端 Alpine（`wire:ignore` 区） | `formatSize()` from `resources/js/support/format-size.js` |

不要把格式化结果塞进值对象（会固化进数据），也不要换成 `Number::fileSize()`（精度与阈值都不同，只有行为变化没有收益）。

### MediaPicker 与 FilePicker 共用一套契约

差别只有「值从哪来」：MediaPicker 走弹窗选库，FilePicker 走系统文件选择器直传。其余全部同源：

| 共同部分 | 位置 |
|---|---|
| 选项、初始状态归一、Alpine props 组装 | `src/Filament/Forms/Concerns/InteractsWithMediaField.php` |
| 触发器（单值空态 / 网格「+」/ 列表「+」三处共用一份） | `views/components/filament/forms/media-field-trigger.blade.php` |
| 单值预览 / 网格 / 列表 / 放大层 | 同目录 `media-field-single|grid|list|zoom.blade.php` |
| 展示辅助（`isImageValue` / `extLabel` / `valueMeta` / `itemMeta`） | `resources/js/support/media-value.js` |

这四个视图是**组件不是 partial**：各自靠 props 收参，字段主视图只按 `multiple` / `itemsLayout` 选择渲染谁。**不要退回 `@include`**——子视图继承父级所有局部变量，没有契约。

### Alpine 成员名就是 API

视图里的方法名与两个 JS 对象耦合，改名会让 markup 静默失效（Alpine 运行时才报错，PHP/Blade/测试都不报）。

| 组件 | 需要的成员 |
|---|---|
| `media-field-single` | `values`、`openPicker()`、`removeSingle()`、`openPreview(v)`、`isImageValue(v)`、`extLabel(v)`、`valueMeta(v)`、`thumbSrc(v)`、`isFull()` |
| `media-field-grid` | `values`、`openPicker()`、`removeAt(i)`、`openPreview(v)`、`isFull()`、`dragStart(i)`、`dragOver(i)`、`dragEnd()`、`dragIndex`、`isImageValue(v)`、`extLabel(v)`、`thumbSrc(v)` |
| `media-field-list` | 同上，但用 `itemMeta(v)` 代替 `extLabel` 组合 |
| `media-field-zoom` | `previewing`、`closePreview()`、`thumbSrc(v)` |

`snMediaPickerField` 与 `snFilePickerField` 必须同时实现这张表。

### 触发器：一处实现，三处引用

单值空态、网格「+」瓦片、列表「+」行共用 `media-field-trigger`，不要在任一形态里另写虚线加号按钮。

- `triggerIcon` / `triggerLabel` **只走服务端渲染**，不进 `mediaFieldProps()`：`Heroicon` 是 enum、`Htmlable` 不可序列化。
- 两者都没有默认值，需要文案就调用处 `->triggerLabel('上传封面')`；`aria-label` 兜底 `picker.trigger`。
- 布局是一段 flex-wrap：一行放得下图标在左，放不下自动换行；别给文案加 `min-w-0`（永远换不了行），也别用 `line-clamp-*`。
- 盒子尺寸走内联 px（动态 Tailwind 类 JIT 生成不了）；**不能在组件标签中间夹 `@if`**（标签内解析不了指令），条件属性先在 `@php` 里拼好再 `$attributes->merge()`。

### 上传：三处入口一条链路

| 层 | 唯一实现 |
|---|---|
| 传输 | Livewire 上传（页面/弹窗用 `uploads`，字段用宿主表单的附件槽） |
| 落库 | `src/Filament/Concerns/UploadsMedia.php`（`uploadMediaFile` / `storeMediaFile` / `uploadFailed` / `notifyMediaUploaded` / `maxUploadKb`） |
| 进度 UI | `x-sn-media::filament.components.upload-progress`（`variant="overlay"|"trigger"`） |
| 进度状态 | `resources/js/support/upload-progress.js`（两个 Alpine 对象都 spread 它） |

- **进度只能来自回调**：`$wire.uploadMultiple('uploads', files, finish, error, progress)`。不要监听 `livewire-upload-*` DOM 事件——那些只由 `wire:model` 绑定的 file input 派发。
- 字段上传不要另开 HTTP 端点：文件落宿主附件槽，再交给 `MediaManager`。包里不存在独立上传路由。
- 失败通知由「持有 `uploadFailed` 的对象」发：页面/弹窗直接调；字段必须 `$wire.callSchemaComponentMethod(fieldKey, 'notifyUploadRejected', [message])`。
- 前端单文件上限一律用 `maxUploadKb()`（PHP ini / Livewire 规则 / 包配置取最小），只读 `config('sn-media.max_size')` 会放过 PHP 拦掉的文件。
- 字段的 `storeUploadedMedia()` / `notifyUploadRejected()` 必须同时带 `#[ExposedLivewireMethod]` 与 `#[Renderless]`。

### 演示页 MediaDemo 是功能清单

`src/Filament/Pages/MediaDemo.php`（slug `media-demo`）覆盖全部能力，默认不注册（`config/sn-media.php` 的 `panel_register.pages` 里放开）。

**给字段加新选项时，必须同时加一个演示字段与一条测试**，否则等于没有文档。演示页挂了 20+ 个 picker，首屏约 5s / 2MB HTML，是固有成本，不要再往上加花样。

### 目录与租户

目录是纯逻辑归类（嵌套集，最多 3 级），文件物理路径不含目录。租户隔离走 `team_id`；业务维度（商城/门店）用 path 目录前缀表达，不额外加列。
