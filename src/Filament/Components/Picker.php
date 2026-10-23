<?php

namespace Wsmallnews\Media\Filament\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Wsmallnews\Media\Filament\Components\Concerns\InteractsWithMedia;
use Wsmallnews\Media\Support\Utils;

/**
 * Media picker modal, mirroring the media page: search, upload, directory tree, type chips.
 * Selection is client-side state; the server only resolves the initial values to entries.
 */
class Picker extends Component
{
    use InteractsWithMedia;
    use WithFileUploads;

    #[Locked]
    public bool $multiple = false;

    /** @var array<int, string> MIME patterns (image/* prefix or exact value); empty = any */
    #[Locked]
    public array $acceptedMimes = [];

    #[Locked]
    public float $ratio = 1;

    #[Locked]
    public string $statePath = '';

    #[Locked]
    public string $fieldId = '';

    #[Locked]
    public ?string $fileDirectory = null;

    /** Initial selection as value objects, order preserved */
    #[Locked]
    public array $initialValues = [];

    /** Max selections (0 = unlimited); the UI limits picks by remaining slots */
    #[Locked]
    public int $maxCount = 0;

    /** @var array<int, int> Selected media ids in save order; seeds the initial selection only */
    public array $selection = [];

    // perPage / sortBy / viewMode come from the trait; the modal overrides them in mount()

    public function mount(int $maxCount = 0, ?string $viewMode = null, ?string $sortBy = null): void
    {
        $this->maxCount = max(0, $maxCount);

        // Denser pagination than the media page: more candidates per screen.
        $this->perPage = 24;

        if ($viewMode !== null) {
            $this->viewMode = $viewMode === 'list' ? 'list' : 'grid';
        }

        if ($sortBy !== null && array_key_exists($sortBy, $this->sortOptions())) {
            $this->sortBy = $sortBy;
        }

        $modelClass = Utils::getMediaModel();

        // Seed the selection in the stored order.
        $this->selection = collect($this->initialValues)
            ->map(fn ($value) => $value['path'] ?? null)
            ->filter()
            ->map(fn (string $path) => $modelClass::findByPath($path)?->id)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Directory tree click; leaf and branch nodes both just change the filter.
     */
    #[On('sn-filament-nestedset-leaf-click')]
    #[On('sn-filament-nestedset-node-click')]
    public function onTreeClick(?int $recordId): void
    {
        $this->setLibraryDir($recordId);
    }

    /**
     * Re-render after tree CRUD so breadcrumbs and the list follow.
     */
    #[On('sn-filament-nestedset-updated')]
    public function refreshFromTree(): void
    {
    }

    /**
     * Value object and preview URL per selected id, sent once so later toggles stay local.
     *
     * @return array<int, array{value: array<string, mixed>, url: string}>
     */
    #[Computed]
    public function selectionEntries(): array
    {
        if ($this->selection === []) {
            return [];
        }

        $modelClass = Utils::getMediaModel();

        $medias = $modelClass::query()
            ->scopeTenant()
            ->whereIn('id', $this->selection)
            ->get()
            ->keyBy('id');

        $entries = [];

        foreach ($this->selection as $id) {
            $media = $medias->get($id);

            if (! $media) {
                continue;
            }

            $entries[$id] = [
                'value' => $media->toValue(),
                'url' => $media->url(200),
            ];
        }

        return $entries;
    }

    /**
     * Same query chain as the media page, plus the field-level MIME constraint. The constraint
     * comes from resolveAcceptedMimes(), the same source the upload validation uses.
     */
    #[Computed]
    public function medias(): LengthAwarePaginator
    {
        $query = $this->mediaQuery();

        if ($acceptedMimes = $this->resolveAcceptedMimes()) {
            $query->ofMimePatterns($acceptedMimes);
        }

        return $query->paginate($this->resolvePerPage($this->perPage));
    }

    /**
     * Hand the new media to Alpine so it can add it to the local selection.
     *
     * The event is window-level, so every picker receives it: statePath and fieldId let each
     * one ignore uploads made in another field.
     */
    protected function afterMediaUploaded($media): void
    {
        $this->dispatch(
            'sn-media-uploaded',
            statePath: $this->statePath,
            fieldId: $this->fieldId,
            id: $media->id,
            value: $media->toValue(),
            url: $media->url(200),
        );
    }

    protected function resolveFileDirectory(): ?string
    {
        return $this->fileDirectory;
    }

    /**
     * The field's MIME constraint, normalised to null so the trait sees only two states.
     *
     * @return array<int, string>|null
     */
    protected function resolveAcceptedMimes(): ?array
    {
        return $this->acceptedMimes === [] ? null : $this->acceptedMimes;
    }

    public function render(): View
    {
        return view('sn-media::filament.components.picker');
    }
}
