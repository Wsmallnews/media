<?php

namespace Wsmallnews\Media\Filament\Forms;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Enums\SlideOverPosition;
use Filament\Support\Enums\Width;
use Wsmallnews\Media\Filament\Forms\Concerns\InteractsWithMediaField;

/**
 * Picks media from the library modal; the stored value is the selected value object(s).
 * Single: {path, name, mime, size, width, height} | null; multiple: the same objects in order.
 */
class MediaPicker extends Field
{
    use InteractsWithMediaField;

    protected string $view = 'sn-media::filament.forms.media-picker';

    /** Picker form: 'modal' (centered) | 'slide-over' */
    protected string | Closure $pickerDisplay = 'modal';

    protected SlideOverPosition | string | Closure $pickerSlideOverPosition = SlideOverPosition::End;

    /** null = default 5xl (same width for modal and slide-over) */
    protected Width | string | Closure | null $pickerWidth = null;

    /** Initial view: grid | list (null = grid) */
    protected string | Closure | null $pickerViewMode = null;

    /** Initial sort (null = created_desc; see Picker::sortOptions()) */
    protected string | Closure | null $pickerSortBy = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (MediaPicker $component, $state) {
            $component->state($component->normalizeHydratedState($state));
        });
    }

    /**
     * Initial view: 'grid' (default) | 'list', which is easier for file fields.
     */
    public function pickerViewMode(string | Closure $mode): static
    {
        $this->pickerViewMode = $mode;

        return $this;
    }

    /**
     * Initial sort (values in Picker::sortOptions()).
     */
    public function pickerSortBy(string | Closure $sort): static
    {
        $this->pickerSortBy = $sort;

        return $this;
    }

    /**
     * Picker form: 'modal' (centered, capped height) | 'slide-over' (full height).
     */
    public function pickerDisplay(string | Closure $display): static
    {
        $this->pickerDisplay = $display;

        return $this;
    }

    /**
     * Slide-over form (same as pickerDisplay('slide-over')) with its direction.
     */
    public function pickerSlideOver(SlideOverPosition | string | Closure $position = SlideOverPosition::End): static
    {
        $this->pickerDisplay = 'slide-over';
        $this->pickerSlideOverPosition = $position;

        return $this;
    }

    /**
     * Picker width (a Filament Width enum or a string such as '5xl').
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
     * Props passed to the picker component and the field's Alpine component.
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
