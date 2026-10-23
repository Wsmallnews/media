<?php

namespace Wsmallnews\Media\Filament\Forms\Concerns;

use Closure;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Wsmallnews\Media\Support\MediaValue;

/**
 * MediaPicker / FilePicker 的共同部分：值契约、触发器外观、状态归一与 props 组装。
 *
 * 两者的差别只有「值从哪来」：
 * - MediaPicker 从素材库弹窗选（追加 fieldId / modalId / viewMode / sortBy 等弹窗选项）；
 * - FilePicker 直接拉系统文件选择器上传（追加 disk / uploadUrl / csrfToken 等直传选项）。
 * 除此之外——值对象契约、初始状态归一、预览框尺寸、多值排序、上限、列表/网格——完全一致，
 * 所以只在这里写一遍，避免两个字段的交互慢慢走散。
 */
trait InteractsWithMediaField
{
    protected bool | Closure $isMultiple = false;

    /** 宽 / 高（与 CSS aspect-ratio 同口径） */
    protected int | float | Closure $ratio = 1;

    /** @var array<string> | Closure mime 模式（image/* 前缀或精确 mime），空 = 不限 */
    protected array | Closure $acceptedMimes = [];

    protected string | Closure | null $directory = null;

    /** 只把 path 写进字段状态（值对象仅用于展示与预览） */
    protected bool | Closure $storesPath = false;

    /** 触发器宽度（px）：空触发器、单值预览、多值每格共用 */
    protected int | Closure $triggerWidth = 144;

    /** 触发器高度（px）：null = 按 ratio 由宽度换算 */
    protected int | Closure | null $triggerHeight = null;

    /** 单值预览是否显示右侧文件信息（名称/尺寸/路径/替换按钮） */
    protected bool | Closure $showsPreviewInfo = true;

    /** 图片点击放大预览（single / grid / list 通用） */
    protected bool | Closure $hasImagePreview = true;

    /**
     * 触发器图标（null = 默认加号）。
     *
     * 只服务服务端渲染，**不进 mediaFieldProps()**：那份数组要 Js::from 下发到 Alpine，
     * Heroicon 是 enum、Htmlable 根本不可序列化。
     */
    protected Heroicon | string | Htmlable | Closure | null $triggerIcon = null;

    /**
     * 触发器文案（null = 只显示图标；同时作为 aria-label 的兜底）。
     *
     * 同样只服务服务端渲染，不进 mediaFieldProps()。
     */
    protected string | Closure | null $triggerLabel = null;

    /** 多值项目排布：grid（默认，缩略图网格）| list（文件列表：左名称右操作） */
    protected string | Closure $itemsLayout = 'grid';

    /** 多值上限（null = 不限）；到达上限后「+」触发器禁用 */
    protected int | Closure | null $maxCount = null;

    public function multiple(bool | Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    /**
     * 宽高比 = 宽 / 高（与 CSS aspect-ratio 同口径）。
     *
     * 常见写法：4:3 传 `ratio(4 / 3)`（= 1.3333），16:9 传 `ratio(16 / 9)`，正方形 `ratio(1)`。
     * 只在 triggerSize() 没传高度时用于换算触发器高度；不规则尺寸直接 triggerSize(160, 120) 更直观。
     */
    public function ratio(int | float | Closure $ratio): static
    {
        $this->ratio = $ratio;

        return $this;
    }

    /**
     * @param  array<string> | Closure  $mimes
     */
    public function acceptedMimes(array | Closure $mimes): static
    {
        $this->acceptedMimes = $mimes;

        return $this;
    }

    /**
     * 仅图片（常用语法糖）
     */
    public function acceptImages(): static
    {
        return $this->acceptedMimes(['image/*']);
    }

    public function directory(string | Closure | null $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * 只把 path 存进字段（默认存完整值对象）。
     *
     * 用途：目标列是 string 的字段、或内联轻量图——存储轨道保持「路径直存」不变。
     */
    public function storePath(bool | Closure $condition = true): static
    {
        $this->storesPath = $condition;

        return $this;
    }

    /**
     * 触发器尺寸（px）：空触发器、单值预览、多值每格共用。
     *
     * - 只传宽度 → 高度按 ratio 换算（ratio 1 = 正方形）；
     * - 不规则图传两个值，如 triggerSize(160, 90)。
     */
    public function triggerSize(int | Closure $width, int | Closure | null $height = null): static
    {
        $this->triggerWidth = $width;
        $this->triggerHeight = $height;

        return $this;
    }

    /**
     * 单值预览是否显示文件信息（名称 / 尺寸 / 路径 / 替换按钮）。
     *
     * false：只留缩略图，操作收进 hover 遮罩的移除按钮——替换 = 移除后重新点空触发器。
     */
    public function previewInfo(bool | Closure $condition = true): static
    {
        $this->showsPreviewInfo = $condition;

        return $this;
    }

    /**
     * 多值上限：到达后「+」触发器禁用。
     *
     * 不传 / 传 null / 传 0 都表示**不限**（0 张上限没有实际意义，最小张数交给 required）。
     */
    public function maxCount(int | Closure | null $count): static
    {
        $this->maxCount = $count;

        return $this;
    }

    /**
     * 多值项目排布：'grid'（默认，缩略图网格）| 'list'（文件列表：左名称、右操作，窄屏满宽）。
     *
     * 非图片的多文件场景用 list 更好认（文件名比扩展名色块信息量大）。
     */
    public function itemsLayout(string | Closure $layout): static
    {
        $this->itemsLayout = $layout;

        return $this;
    }

    /**
     * 是否允许点图片放大预览（默认开；预览层独立渲染，不依赖任何弹窗）。
     */
    public function imagePreview(bool | Closure $condition = true): static
    {
        $this->hasImagePreview = $condition;

        return $this;
    }

    /**
     * 触发器图标（null = 默认加号）。传入即覆盖，三处触发器（单值 / 网格瓦片 / 列表行）同步生效。
     */
    public function triggerIcon(Heroicon | string | Htmlable | Closure | null $icon): static
    {
        $this->triggerIcon = $icon;

        return $this;
    }

    /**
     * 触发器文案（null = 只显示图标）。
     *
     * 不设默认值：MediaPicker 与 FilePicker 在表单上不该靠文案区分用途，需要的话由调用处自己写。
     * 横向放不下会自动换行（icon 在上、文案在下），仍超宽则省略号截断。
     */
    public function triggerLabel(string | Closure | null $label): static
    {
        $this->triggerLabel = $label;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function getRatio(): float
    {
        return (float) $this->evaluate($this->ratio);
    }

    /**
     * @return array<int, string>
     */
    public function getAcceptedMimes(): array
    {
        $mimes = $this->evaluate($this->acceptedMimes);

        return array_values((array) $mimes);
    }

    public function getDirectory(): ?string
    {
        $directory = $this->evaluate($this->directory);

        return is_string($directory) && $directory !== '' ? $directory : null;
    }

    public function storesPath(): bool
    {
        return (bool) $this->evaluate($this->storesPath);
    }

    public function getTriggerWidth(): int
    {
        return max(1, (int) $this->evaluate($this->triggerWidth));
    }

    public function getTriggerHeight(): int
    {
        $height = $this->evaluate($this->triggerHeight);

        if ($height !== null) {
            return max(1, (int) $height);
        }

        $ratio = $this->getRatio();

        return max(1, (int) round($this->getTriggerWidth() / ($ratio > 0 ? $ratio : 1)));
    }

    public function showsPreviewInfo(): bool
    {
        return (bool) $this->evaluate($this->showsPreviewInfo);
    }

    public function getMaxCount(): ?int
    {
        $count = $this->evaluate($this->maxCount);

        // 0 与 null 同义：都表示「不限」（否则调用处写 0 会误以为一张都不能选）
        return ($count === null || (int) $count <= 0) ? null : (int) $count;
    }

    public function getItemsLayout(): string
    {
        return (string) $this->evaluate($this->itemsLayout) === 'list' ? 'list' : 'grid';
    }

    public function hasImagePreview(): bool
    {
        return (bool) $this->evaluate($this->hasImagePreview);
    }

    public function getTriggerIcon(): Heroicon | string | Htmlable | null
    {
        return $this->evaluate($this->triggerIcon);
    }

    public function getTriggerLabel(): ?string
    {
        $label = $this->evaluate($this->triggerLabel);

        return is_string($label) && $label !== '' ? $label : null;
    }

    /**
     * 初始状态归一（afterStateHydrated 用）：丢掉无效项，只留契约内的形状。
     *
     * storePath 模式的值是路径字符串，其余是值对象——两种契约都能吃，是因为
     * 存储契约由字段自己声明，视图侧永远只按值对象消费（见 resolveStateEntries）。
     */
    protected function normalizeHydratedState(mixed $state): mixed
    {
        if ($this->storesPath()) {
            return $this->isMultiple()
                ? (is_array($state) ? array_values(array_filter($state, fn ($item) => is_string($item) && $item !== '')) : [])
                : ((is_string($state) && $state !== '') ? $state : null);
        }

        if ($this->isMultiple()) {
            return is_array($state) ? array_values(array_filter($state, fn ($item) => is_array($item) && ($item['path'] ?? null))) : [];
        }

        return (is_array($state) && ($state['path'] ?? null)) ? $state : null;
    }

    /**
     * 字段状态 → 值对象 + 预览地址（单值/多值、值对象/路径字符串都能吃）。
     *
     * 路径字符串**必须**经 MediaValue 反查素材表补齐 mime / 尺寸：前端靠 mime 判断
     * 是渲染图片还是扩展名标签，只给 path 的话预览会退化成「PNG」文字块。
     *
     * @return array<int, array{value: array<string, mixed>, url: ?string}>
     */
    protected function resolveStateEntries(mixed $state): array
    {
        $items = $this->isMultiple()
            ? (is_array($state) ? $state : [])
            : (filled($state) ? [$state] : []);

        return collect($items)
            ->map(function (mixed $item): ?MediaValue {
                if (is_string($item) && $item !== '') {
                    return MediaValue::make($item);
                }

                return is_array($item) && filled($item['path'] ?? null) ? MediaValue::make($item) : null;
            })
            ->filter()
            ->map(fn (MediaValue $value): array => [
                'value' => $value->toArray(),
                'url' => $value->url(200),
            ])
            ->values()
            ->all();
    }

    /**
     * 两个字段共用的 Alpine props（值 + 外观）。
     *
     * 弹窗选项（MediaPicker）与直传选项（FilePicker）各自追加，不混进这里——
     * 否则每个字段都会往下发一批自己永远不用的键。
     */
    protected function mediaFieldProps(): array
    {
        $values = [];
        $urls = [];

        // 一次反查同时产出值对象与预览地址，避免同一条 path 查两次素材表
        foreach ($this->resolveStateEntries($this->getState()) as $entry) {
            $path = $entry['value']['path'] ?? null;

            if (! $path) {
                continue;
            }

            $values[] = $entry['value'];
            $urls[$path] = $entry['url'];
        }

        return [
            'multiple' => $this->isMultiple(),
            'acceptedMimes' => $this->getAcceptedMimes(),
            'ratio' => $this->getRatio(),
            'directory' => $this->getDirectory(),
            'statePath' => $this->getStatePath(),
            'storePath' => $this->storesPath(),
            'width' => $this->getTriggerWidth(),
            'height' => $this->getTriggerHeight(),
            'previewInfo' => $this->showsPreviewInfo(),
            'maxCount' => $this->getMaxCount(),
            'itemsLayout' => $this->getItemsLayout(),
            'imagePreview' => $this->hasImagePreview(),
            'initialValues' => $values,
            'urls' => $urls,
        ];
    }
}
