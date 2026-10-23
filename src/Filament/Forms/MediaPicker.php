<?php

namespace Wsmallnews\Media\Filament\Forms;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Enums\SlideOverPosition;
use Filament\Support\Enums\Width;
use Wsmallnews\Media\Filament\Forms\Concerns\InteractsWithMediaField;

/**
 * 素材选择字段：打开素材库弹窗选择（统一上传管道 + 素材库复用）。
 *
 * 字段状态（值契约）：
 * - 单值：{path, name, mime, size, width, height} | null
 * - 多值：上述对象的数组，数组序即排序（模型侧建议 cast 为 array）
 *
 * 共同部分（触发器尺寸、上限、列表/网格、状态归一）见 InteractsWithMediaField。
 */
class MediaPicker extends Field
{
    use InteractsWithMediaField;

    protected string $view = 'sn-media::filament.forms.media-picker';

    /** 弹层形态：'modal'（居中）| 'slide-over'（侧滑） */
    protected string | Closure $pickerDisplay = 'modal';

    protected SlideOverPosition | string | Closure $pickerSlideOverPosition = SlideOverPosition::End;

    /** null = 取默认 5xl（居中、侧滑同宽） */
    protected Width | string | Closure | null $pickerWidth = null;

    /** 弹窗初始视图：grid | list（null = grid） */
    protected string | Closure | null $pickerViewMode = null;

    /** 弹窗初始排序（null = created_desc，值域见 Picker::sortOptions()） */
    protected string | Closure | null $pickerSortBy = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (MediaPicker $component, $state) {
            $component->state($component->normalizeHydratedState($state));
        });
    }

    /**
     * 弹窗初始视图：'grid'（默认）| 'list'（文件类字段列表更好选）。
     */
    public function pickerViewMode(string | Closure $mode): static
    {
        $this->pickerViewMode = $mode;

        return $this;
    }

    /**
     * 弹窗初始排序（值域见 Picker::sortOptions()）。
     */
    public function pickerSortBy(string | Closure $sort): static
    {
        $this->pickerSortBy = $sort;

        return $this;
    }

    /**
     * 弹层形态：'modal' 居中（默认，限高、素材区内部滚动）| 'slide-over' 侧滑（满高）。
     */
    public function pickerDisplay(string | Closure $display): static
    {
        $this->pickerDisplay = $display;

        return $this;
    }

    /**
     * 侧滑形态（等价于 pickerDisplay('slide-over')），并把方向一起定掉。
     */
    public function pickerSlideOver(SlideOverPosition | string | Closure $position = SlideOverPosition::End): static
    {
        $this->pickerDisplay = 'slide-over';
        $this->pickerSlideOverPosition = $position;

        return $this;
    }

    /**
     * 弹层宽度（Filament Width 枚举或 '5xl' 这类字符串）。
     */
    public function pickerWidth(Width | string | Closure $width): static
    {
        $this->pickerWidth = $width;

        return $this;
    }

    public function getPickerViewMode(): string
    {
        return (string) $this->evaluate($this->pickerViewMode) === 'list' ? 'list' : 'grid';
    }

    public function getPickerSortBy(): string
    {
        $sort = (string) $this->evaluate($this->pickerSortBy);

        return $sort !== '' ? $sort : 'created_desc';
    }

    public function getPickerDisplay(): string
    {
        return (string) $this->evaluate($this->pickerDisplay) === 'slide-over' ? 'slide-over' : 'modal';
    }

    public function isSlideOver(): bool
    {
        return $this->getPickerDisplay() === 'slide-over';
    }

    public function getSlideOverPosition(): SlideOverPosition
    {
        $position = $this->evaluate($this->pickerSlideOverPosition);

        if ($position instanceof SlideOverPosition) {
            return $position;
        }

        return SlideOverPosition::tryFrom((string) $position) ?? SlideOverPosition::End;
    }

    public function getPickerWidth(): Width | string
    {
        $width = $this->evaluate($this->pickerWidth);

        if ($width === null) {
            return Width::FiveExtraLarge;
        }

        if ($width instanceof Width) {
            return $width;
        }

        return Width::tryFrom((string) $width) ?? Width::FiveExtraLarge;
    }

    /**
     * 传给弹窗组件与字段 Alpine 的 props
     */
    public function getPickerProps(): array
    {
        $pickerId = md5($this->getLivewire()->getId() . ':' . $this->getStatePath());

        return [
            ...$this->mediaFieldProps(),
            'fieldId' => $pickerId,
            'modalId' => 'sn-media-picker-' . $pickerId,
            'viewMode' => $this->getPickerViewMode(),
            'sortBy' => $this->getPickerSortBy(),
        ];
    }
}
