<?php

namespace Wsmallnews\Media\Filament\Forms\Concerns;

use Closure;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Wsmallnews\Media\Support\MediaValue;

/**
 * Shared surface of MediaPicker and FilePicker: value contract, trigger appearance, state
 * normalisation, Alpine props. The only difference is where the value comes from.
 */
trait InteractsWithMediaField
{
    protected bool | Closure $isMultiple = false;

    /** Width / height ratio (same semantics as CSS aspect-ratio) */
    protected int | float | Closure $ratio = 1;

    /** @var array<string> | Closure Mime patterns (image/* or an exact mime); empty = any */
    protected array | Closure $acceptedMimes = [];

    protected string | Closure | null $directory = null;

    /** Store only the path in field state (the value object is still used for display) */
    protected bool | Closure $storesPath = false;

    /** Trigger width in px: empty trigger, single preview and each grid cell share it */
    protected int | Closure $triggerWidth = 144;

    /** Trigger height in px: null = derived from width via ratio */
    protected int | Closure | null $triggerHeight = null;

    /** Show file info next to the single preview (name / dimensions / path / replace) */
    protected bool | Closure $showsPreviewInfo = true;

    /** Click-to-zoom preview (single / grid / list) */
    protected bool | Closure $hasImagePreview = true;

    /**
     * Trigger icon (null = default plus sign).
     *
     * Server-rendered only — **never** put it into mediaFieldProps(): icons render through
     * <x-filament::icon>, and Htmlable objects cannot be serialised by Js::from().
     */
    protected Heroicon | string | Htmlable | Closure | null $triggerIcon = null;

    /**
     * Trigger label (null = icon only; also the aria-label fallback). Server-rendered only.
     */
    protected string | Closure | null $triggerLabel = null;

    /** Multi-value layout: grid (default) | list */
    protected string | Closure $itemsLayout = 'grid';

    /** Max items (null = unlimited); the "+" trigger is disabled once reached */
    protected int | Closure | null $maxCount = null;

    public function multiple(bool | Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    /**
     * Aspect ratio = width / height. Only used to derive the trigger height when
     * triggerSize() is given a width; for irregular sizes call triggerSize(160, 120).
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
     * Images only (sugar)
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
     * Store only the path (default stores the whole value object).
     * For string columns or lightweight inline images.
     */
    public function storePath(bool | Closure $condition = true): static
    {
        $this->storesPath = $condition;

        return $this;
    }

    /**
     * Trigger size in px, shared by the empty trigger, the single preview and each grid cell.
     * Width only => height derived from ratio. Irregular shapes: triggerSize(160, 90).
     */
    public function triggerSize(int | Closure $width, int | Closure | null $height = null): static
    {
        $this->triggerWidth = $width;
        $this->triggerHeight = $height;

        return $this;
    }

    /**
     * Show file info next to the single preview (name / dimensions / path / replace).
     * false keeps the thumbnail only; replace = remove, then click the empty trigger.
     */
    public function previewInfo(bool | Closure $condition = true): static
    {
        $this->showsPreviewInfo = $condition;

        return $this;
    }

    /**
     * Max item count; the "+" trigger is disabled once reached.
     * null / omitted / 0 all mean unlimited (use `required` for a minimum).
     */
    public function maxCount(int | Closure | null $count): static
    {
        $this->maxCount = $count;

        return $this;
    }

    /**
     * Multi-value layout: 'grid' (thumbnails) | 'list' (name left, actions right).
     * Prefer list for non-image files — filenames carry more than extension blocks.
     */
    public function itemsLayout(string | Closure $layout): static
    {
        $this->itemsLayout = $layout;

        return $this;
    }

    /**
     * Click-to-zoom images (default on; the zoom layer renders independently of modals).
     */
    public function imagePreview(bool | Closure $condition = true): static
    {
        $this->hasImagePreview = $condition;

        return $this;
    }

    /**
     * Trigger icon (null = default plus). Overrides all three trigger shapes at once.
     */
    public function triggerIcon(Heroicon | string | Htmlable | Closure | null $icon): static
    {
        $this->triggerIcon = $icon;

        return $this;
    }

    /**
     * Trigger label (null = icon only). No default: the two fields should not be told apart
     * by copy. Wraps under the icon when narrow, truncates when still too wide.
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

        // 0 and null both mean unlimited (0 would otherwise read as "none allowed").
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
     * Normalise hydrated state (afterStateHydrated): drop invalid items, keep contract shapes.
     * storePath mode holds path strings, otherwise value objects — the storage contract is
     * declared by the field itself, views always consume value objects (resolveStateEntries).
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
     * Field state => value objects + preview URLs (single/multiple, object/path string).
     *
     * Path strings **must** be resolved through MediaValue: the frontend relies on mime to
     * choose between an image and an extension block, so a bare path degrades to "PNG" text.
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
     * Alpine props shared by both fields (values + appearance). Modal-only options
     * (MediaPicker) and direct-upload options (FilePicker) are appended by each field,
     * so neither ships keys it never reads.
     */
    protected function mediaFieldProps(): array
    {
        $values = [];
        $urls = [];

        // One lookup yields both the value object and the preview URL (no double query).
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
