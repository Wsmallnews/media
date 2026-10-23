## 素材包（wsmallnews/media）

`wsmallnews/media` 是 Filament 的素材库插件（素材管理页 + `MediaPicker` / `FilePicker` 表单字段 + 目录树）。命名空间根为 `Wsmallnews\Media`，Blade 视图前缀为 `sn-media`，配置文件为 `config/sn-media.php`。

### 文件大小的展示：全包只有三处实现，不要再加第四处

| 场景 | 用什么 | 位置 |
|---|---|---|
| Blade 模板里手上有模型（素材页、选择弹窗列表、预览面板） | `$media->size_for_humans` | `Models/Media.php` 的 `sizeForHumans()` 访问器，内部调 `Utils::formatBytes()` |
| 只有字节数、没有模型（`UploadedFile`、值对象数组、日志 / 通知文案） | `Utils::formatBytes((int) $bytes)` | `Support/Utils.php` |
| 前端 Alpine 组件（`wire:ignore` 区域，渲染 `valueMeta()` / `itemMeta()`） | `import { formatSize } from '../support/format-size.js'` | `resources/js/support/format-size.js`，`picker-field.js` 与 `file-picker-field.js` 共用 |

**不要写第四份换算**（JS 或 PHP 都不行），也不要把这个字符串预热到值对象里：

- `Media::toValue()` 是**业务字段的保存值**。把 `size_for_humans` 塞进去等于把展示格式固化进数据：格式化规则一改，老记录留旧串、新记录是新串，同一个列表里两种格式并存；而且值对象还要参与排序回写、去重与比较，多一个派生键要一直剔除。值对象只存字节数（`size`）。
- `$appends` 解决不了字段组件的需求：字段组件从不序列化 Media 模型，拿到的是 `toValue()` 出来的数组（三条来源都是服务端产出的——`initialValues`、上传响应、弹窗确认），所以加 `$appends` 对它们无效，只会让所有 `Media::toArray()`（API、导出、其他包）多一个键。
- 前端没有"凭空造值对象"的路径（`media-manager.js` 里的 `file.size` 只用于上传前的超限拦截，不进值对象），所以字节数一定拿得到。只有「素材表按 path 反查不到」的降级值没有 `size`，`formatSize()` 输出 `—` 是预期行为，别当 bug 修。

**不要把 `Utils::formatBytes()` 换成 `Number::fileSize()`**：后者默认 `precision = 0`（1234567 变 `1 MB`、1.5 GB 变 `2 GB`）、换算阈值是 `abs(bytes)/1024 > 0.9`（950 B 显示 `0.9 KB`）、locale 只影响数字分隔符与字形且必须 `Number::useLocale()` 全局设置（框架不会跟随 `app()->getLocale()`），单位 `B/KB/MB/GB` 永远不翻译——换过去只有行为变化，没有收益。

### 两个字段共用一套契约：动 MediaPicker 时同步看 FilePicker

`MediaPicker`（弹窗选素材）与 `FilePicker`（直接上传）的差别只有「值从哪来」，其余全部同源：

| 共同部分 | 位置 |
|---|---|
| `multiple` / `ratio` / `acceptedMimes` / `directory` / `storePath` / `triggerSize` / `triggerIcon` / `triggerLabel` / `previewInfo` / `maxCount` / `itemsLayout` / `imagePreview`，以及初始状态归一、Alpine props 组装 | `src/Filament/Forms/Concerns/InteractsWithMediaField.php` |
| 触发器（单值空态 / 网格「+」瓦片 / 列表「+」行，三处共用一份） | `resources/views/components/filament/forms/media-field-trigger.blade.php`（`x-sn-media::filament.forms.media-field-trigger`） |
| 单值预览（有值时） | `resources/views/components/filament/forms/media-field-single.blade.php`（`x-sn-media::filament.forms.media-field-single`） |
| 多值网格（序号 + 悬停放大/移除 + 末尾「+」） | `resources/views/components/filament/forms/media-field-grid.blade.php`（`x-sn-media::filament.forms.media-field-grid`） |
| 多值列表（拖拽手柄 + 一行一条 + 底部触发器） | `resources/views/components/filament/forms/media-field-list.blade.php`（`x-sn-media::filament.forms.media-field-list`） |
| 放大预览层（teleport 到 body） | `resources/views/components/filament/forms/media-field-zoom.blade.php`（`x-sn-media::filament.forms.media-field-zoom`） |
| Alpine 方法名与展示辅助的**实现** | `resources/js/support/media-value.js`（展示辅助）+ 两个字段的 `resources/js/components/*.js`（交互方法） |

**这几段 markup 是组件，不是 partial**：它们各自是一个有边界的 UI 单元（触发器 / 单值形态 / 网格 / 列表 / 浮层），靠显式 props 声明收参、靠自己的 Alpine 方法契约工作。字段主视图只负责按 `multiple` 与 `itemsLayout` 选哪一个。

@verbatim
```blade
{{-- 字段主视图里的引入方式（Alpine 的 x-if 要求单个根元素，各组件的根都是单个元素）--}}
<template x-if="! multiple">
    <x-sn-media::filament.forms.media-field-single :width="$props['width']" :height="$props['height']"
        :image-preview="$props['imagePreview']" :preview-info="$props['previewInfo']"
        :trigger-icon="$getTriggerIcon()" :trigger-label="$getTriggerLabel()" />
</template>

<template x-if="multiple">
    @if ($props['itemsLayout'] === 'list')
        <x-sn-media::filament.forms.media-field-list :image-preview="$props['imagePreview']"
            :trigger-icon="$getTriggerIcon()" :trigger-label="$getTriggerLabel()" />
    @else
        <x-sn-media::filament.forms.media-field-grid :width="$props['width']" :height="$props['height']"
            :trigger-icon="$getTriggerIcon()" :trigger-label="$getTriggerLabel()" />
    @endif
</template>
```
@endverbatim

@verbatim**不要退回 `@include`**（`views/filament/forms/partials/` 那种写法已删）：它是父视图的延续——子视图继承父级所有局部变量、没有 props 契约；这四段是**独立 UI 单元**，只依赖自己收到的 props。判断标准是「它有没有用到自己没显式收到的东西」：用了，它就不是组件，硬转必断；没用，它早就是组件了。@endverbatim

### 字段组件的 Alpine 契约：改方法名等于改 API

这四段 markup 与调用方 Alpine 对象的方法名耦合，**比与 PHP props 的耦合更深**。每个组件文件头部都列了它自己要的成员，改 JS 前先对照：

| 组件 | 需要的 Alpine 成员 |
|---|---|
| `media-field-single` | `values`、`openPicker()`、`removeSingle()`、`openPreview(v)`、`isImageValue(v)`、`extLabel(v)`、`valueMeta(v)`、`thumbSrc(v)`、`isFull()` |
| `media-field-grid` | `values`、`openPicker()`、`removeAt(i)`、`openPreview(v)`、`isFull()`、`dragStart(i)`、`dragOver(i)`、`dragEnd()`、`dragIndex`、`isImageValue(v)`、`extLabel(v)`、`thumbSrc(v)` |
| `media-field-list` | 同上，但**用 `itemMeta(v)` 代替 `extLabel/v` 的组合展示**（列表行显示「尺寸 · 大小 · 类型」） |
| `media-field-zoom` | `previewing`、`closePreview()`、`thumbSrc(v)` |

**两个字段的 Alpine 对象必须同时实现这张表**（`snMediaPickerField` 与 `snFilePickerField`）。删改任何一个名字，四处 markup 会静默失效——Alpine 对未定义方法只在运行时抛错，PHP 侧、Blade 编译都不报，测试也照过。

新增选项时先判断它是不是「两个字段都适用」：是就进 trait 或共享组件；只服务一个字段的（`pickerViewMode`、`pickerDisplay`、`disk`、`storageOnly`、`uploadUrl`、`csrfToken`）留在各自类里。不要在某个字段的视图里另抄一份触发框或条目渲染——那正是两个字段交互走散的开始。

### 触发器外观：一处实现，三处引用

单值空态、多值网格的「+」瓦片、多值列表的「+」行**共用 `media-field-trigger`**，不许在单值 / 网格 / 列表任一组件里另写一个虚线加号按钮。改触发器只改这一个文件。

- **`triggerIcon` / `triggerLabel` 只走服务端渲染**：视图里直接 `$getTriggerIcon()` / `$getTriggerLabel()` 读，**不要塞进 `mediaFieldProps()`** —— 那份数组要 `Js::from()` 下发给 Alpine，而 `Heroicon` 是 enum、`Htmlable` 不可序列化，塞进去字段直接渲染失败。
- **两者都没有默认值**：MediaPicker 与 FilePicker 在表单上不该靠文案区分用途，要文字就调用处自己 `->triggerLabel('上传封面')`，不传就是纯图标；`aria-label` 兜底成 `picker.trigger`（「选择文件」），保证纯图标按钮对读屏仍有名字。
- **布局是一段 flex-wrap，不是三套版式**：一行放得下就图标在左、文案在右；放不下 flex 自动换行把文案挤到第二行（视觉上等于图标在上、文案在下）；独占一行仍超宽则 `truncate` 出省略号。所以**别给文案元素加 `min-w-0`**（会被压在同一行里直接缩没、永远换不了行），**也别用 `line-clamp-*`**（与 `truncate` 互斥）。
- 盒子的 `width` / `height` 走内联 px（动态 Tailwind 类 JIT 生成不了），且**不能在组件标签中间夹 @verbatim`@if`@endverbatim** —— 标签内解析不了指令，会编出语法错误的视图；要按条件出属性就先在 @verbatim`@php`@endverbatim 里拼好数组再 `$attributes->merge()`。


### 上传：三处入口共用一条链路

素材管理页、选择弹窗、FilePicker 字段的上传**只有一条链路**，别再给某个入口单独开一条：

| 层 | 唯一实现 | 说明 |
|---|---|---|
| 传输 | Livewire 上传 | 页面 / 弹窗用自己的 `uploads` 属性；字段用宿主表单的 `componentFileAttachments.{statePath}[.{key}]` |
| 落库 | `src/Support/Concerns/UploadsMedia.php` | `uploadMediaFile()` / `storeMediaFile()` / `uploadFailed()` / `notifyMediaUploaded()` / `maxUploadKb()` |
| 进度 UI | `x-sn-media::filament.components.upload-progress` | `variant="overlay"`（页面/弹窗）或 `variant="trigger"`（字段，盖住触发器、只画条） |
| 进度状态 | `resources/js/support/upload-progress.js` | 两个 Alpine 对象都 spread 它 |

- **进度只能来自回调**：@verbatim`$wire.uploadMultiple('uploads', files, finish, error, progress)`@endverbatim。**不要监听 `livewire-upload-*` DOM 事件**——那四个事件只由 `wire:model` 绑定的 file input 派发，程序化上传的默认回调全是 no-op，监听它们等于永远收不到（进度条消失就是这么来的）。
- **字段的上传只走 Livewire，不要再开 HTTP 端点**：`FilePicker` 是 `Field` 不是 Livewire 组件，文件落到宿主的附件槽（Filament v5 的 `InteractsWithSchemas` 自带 `WithFileUploads`，所有 Filament 表单宿主都有），再由 `storeUploadedMedia()` 交给 `MediaManager`。包里**不存在**独立的上传路由（旧的 `POST /sn-media/upload`、`MediaUploadController`、`AuthenticateMediaRequest` 已全部删除）——新增入口也只能走这条链路，否则进度、上限预检、失败通知会各写一份。
- **失败通知由"有 `uploadFailed` 的那个对象"发**：页面 / 弹窗的 `$wire` 就是自己，直接 @verbatim`$wire.uploadFailed(...)`@endverbatim；字段的 `$wire` 是宿主表单（它不知道这个包），必须 @verbatim`$wire.callSchemaComponentMethod(fieldKey, 'notifyUploadRejected', [message])`@endverbatim。
- **前端的单文件上限一律用 `maxUploadKb()`**（PHP ini / Livewire 规则 / 包配置的最小值）。只读 `config('sn-media.max_size')` 是错的：本机 PHP 常见 `upload_max_filesize=2M`，前端会放行 20MB 以内的文件、然后被 PHP 静默掐断。
- 字段 `storeUploadedMedia()` / `notifyUploadRejected()` 必须同时带 @verbatim`#[ExposedLivewireMethod]`@endverbatim 与 @verbatim`#[Renderless]`@endverbatim：前者是 `callSchemaComponentMethod` 的白名单，后者避免每次上传都触发一次 schema 局部重渲染。

### 演示页 `MediaDemo`：字段新增选项必须同步补进去

`src/Filament/Pages/MediaDemo.php`（视图 `resources/views/filament/pages/media-demo.blade.php`，slug `media-demo`）是这个包**唯一的功能清单**，通过 @verbatim`config('sn-media.panel_register.pages')`@endverbatim 注册（正式发布时注释掉那一行即可从面板与路由消失）。

覆盖点：单值 / 多值、网格 / 列表、上限、比例与固定尺寸、`previewInfo` 与 `imagePreview` 开关、触发器 icon/label、仅 PDF / 全格式、弹层形态与初始视图排序、`storePath`、`directory`、私有盘、`storageOnly`、Repeater 嵌套（含嵌套内 FilePicker 上传）。

- **给字段加新选项时，必须同时在演示页加一个演示字段、并在 `MediaDemoPageTest` 的能力清单里加一行**——否则等于没有文档。
- 页面代价：20+ 个 `MediaPicker` 各自挂一个 picker Livewire 组件（每个都渲染目录树 + 素材列表），首屏约 5s / 2MB HTML。这是演示页的固有成本，不要再给它加字段之外的花样；正式项目里如果要挂很多 picker，考虑把 picker 内容改成「首次打开才渲染」。

### 进度覆盖层与字段内交互的两个约定

- **FilePicker 的进度条盖在触发器上**（`upload-progress variant="trigger"`），不是触发器下面：`media-field-trigger` 自带 `relative` 且接受 `showsUploadProgress`，single / grid / list 三个形态把它继续往下传（默认 false，MediaPicker 不受影响）。覆盖层**只画进度条、不显示任何文字**；需要文字时用组件的 `showText` / `showFilename` 开关。
- **成功提示与素材页 / 选择弹窗同节奏**：`storeUploadedMedia($attachment, $isLastOfBatch, $batchCount)` 只在「这一批最后一个文件」落库后调 `notifyMediaUploaded($batchCount)`。字段是一个文件一次请求，服务端自己看不出批次边界，所以批次信息必须由前端带上来；不带就静默成功。
- **单值 / 多值的悬停按钮统一在底部满宽半透明黑条里**（`bg-gray-950/60` + 图标白色、居中、悬停才出现）。别再把它们分开放（一个右上角一个右下角）——图片底色深浅不一，散落的按钮很容易看不见；黑条 + 白图标才稳。序号仍在左上。
