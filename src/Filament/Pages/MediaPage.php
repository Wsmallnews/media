<?php

namespace Wsmallnews\Media\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use UnitEnum;
use Wsmallnews\FilamentNestedset\Forms\Fields\KalnoyNestedsetSelectTree;
use Wsmallnews\Media\Filament\Components\Concerns\InteractsWithMedia;
use Wsmallnews\Media\Models\Media;
use Wsmallnews\Media\Models\MediaDirectory;
use Wsmallnews\Media\Support\MediaValue;
use Wsmallnews\Media\Support\Utils;
use Wsmallnews\Support\Filament\Concerns\CanBeConfigured;
use Wsmallnews\Support\Filament\Pages\PageConfiguration;

final class MediaPage extends Page implements HasActions
{
    use CanBeConfigured;
    use InteractsWithActions;
    use InteractsWithMedia;
    use WithFileUploads;

    protected static ?string $configurationClass = PageConfiguration::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $slug = 'media';

    protected static ?int $navigationSort = 1;

    protected string $view = 'sn-media::filament.pages.media';

    #[Url]
    public string $viewMode = 'grid';

    #[Url]
    public string $sortBy = 'created_desc';

    /** @var int|string Page size selected in the pagination component. */
    #[Url]
    public int | string $perPage = 12;

    public ?int $previewId = null;

    public string $metaName = '';

    public string $metaAlt = '';

    public function getTitle(): string | Htmlable
    {
        return self::$title ?? __('sn-media::media.page.title');
    }

    public static function getNavigationLabel(): string
    {
        return self::$navigationLabel ?? __('sn-media::media.page.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return self::getConfigurationValue('navigationGroup') ?? __('sn-media::media.global_default.navigation_group');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label(__('sn-media::media.toolbar.upload'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->alpineClickHandler("\$dispatch('sn-media-open-upload')"),
        ];
    }

    // Row actions (tile / list "..." menu, triggered via mountAction with {id/ids})

    public function snMediaPreviewAction(): Action
    {
        return Action::make('snMediaPreview')
            ->label(__('sn-media::media.list.preview'))
            ->icon(Heroicon::OutlinedEye)
            ->action(function (array $arguments): void {
                $this->openPreview((int) ($arguments['id'] ?? 0));
            });
    }

    public function snMediaCopyPathAction(): Action
    {
        return Action::make('snMediaCopyPath')
            ->label(__('sn-media::media.list.copy_path'))
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->action(function (array $arguments): void {
                $media = Utils::getMediaModel()::query()
                    ->scopeTenant()
                    ->find($arguments['id'] ?? 0);
                if ($media) {
                    $this->js('navigator.clipboard?.writeText(' . Js::from($media->path) . ')');
                    Notification::make()->title(__('sn-media::media.notifications.path_copied'))->success()->send();
                }
            });
    }

    public function snMediaMoveAction(): Action
    {
        return Action::make('snMediaMove')
            ->label(__('sn-media::media.list.move'))
            ->icon(Heroicon::OutlinedFolderArrowDown)
            ->modalWidth(Width::Large)
            ->schema([
                KalnoyNestedsetSelectTree::make('directory_id')
                    ->label(__('sn-media::media.tree.directories'))
                    ->searchable()
                    ->enableBranchNode()
                    ->withCount()
                    ->query(fn () => MediaDirectory::query()->scopeTenant(), titleAttribute: 'name', parentAttribute: 'parent_id'),
            ])
            ->action(function (array $arguments, array $data): void {
                $ids = $arguments['ids'] ?? [$arguments['id'] ?? null];
                $this->move(array_filter($ids), ($data['directory_id'] ?? '') === '' ? null : (int) $data['directory_id']);

                // The preview modal still shows the old directory: close it.
                $this->clearPreview();
            });
    }

    public function snMediaDeleteAction(): Action
    {
        return Action::make('snMediaDelete')
            ->label(__('sn-media::media.list.delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('sn-media::media.notifications.delete_hint'))
            ->action(function (array $arguments): void {
                $ids = $arguments['ids'] ?? [$arguments['id'] ?? null];
                $this->delete(array_filter($ids));
            });
    }

    /**
     * Row "..." menu: preview, copy path, download, move, delete.
     */
    public function mediaMenuActions(Media $media): ActionGroup
    {
        return ActionGroup::make([
            ($this->snMediaPreviewAction)(['id' => $media->id]),
            ($this->snMediaCopyPathAction)(['id' => $media->id]),
            Action::make('snMediaDownload')
                ->label(__('sn-media::media.list.download'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                // With SPA mode a plain href is intercepted by wire:navigate as a fetch and the
                // binary response would be rendered as text; JS navigation keeps it a download.
                ->alpineClickHandler('window.location.href = ' . Js::from(MediaValue::make($media)->downloadUrl())),
            ($this->snMediaMoveAction)(['id' => $media->id]),
            ($this->snMediaDeleteAction)(['id' => $media->id]),
        ])
            ->iconButton()
            ->icon(Heroicon::OutlinedEllipsisVertical)
            ->color('gray')
            ->tooltip(__('sn-media::media.list.more_actions'))
            // Do not use ->dropdownTeleport(): it only switches x-float to fixed, while the
            // @container wrappers here create a fixed containing block and drag the panel away.
            ->dropdownPlacement('bottom-end');
    }

    /**
     * Type filter; combines with the directory filter.
     */
    public function filterByType(string $type = ''): void
    {
        $this->setLibraryType($type);
    }

    /**
     * Directory filter; combines with the type filter. Also receives the tree click events
     * (the payload key matches the parameter name).
     */
    #[On('sn-filament-nestedset-leaf-click')]
    #[On('sn-filament-nestedset-node-click')]
    public function filterByDir(?int $recordId = null): void
    {
        $this->setLibraryDir($recordId);
    }

    /**
     * Re-render after tree CRUD so breadcrumbs, counts and the list follow.
     */
    #[On('sn-filament-nestedset-updated')]
    public function refreshLibrary(): void
    {
    }

    // File operations

    /**
     * Move to a directory (logical ownership only; path is unchanged).
     *
     * @param  array<int, int>  $ids
     */
    public function move(array $ids, ?int $targetDirId = null): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        if ($targetDirId !== null) {
            MediaDirectory::query()->scopeTenant()->findOrFail($targetDirId);
        }

        Utils::getMediaModel()::query()
            ->scopeTenant()
            ->whereIn('id', $ids)
            ->update(['directory_id' => $targetDirId]);
        unset($this->medias);

        // Directory counts changed; the tree is a separate component and refetches on its own.
        $this->dispatch('refresh-page');
        Notification::make()
            ->title(__('sn-media::media.notifications.moved', ['count' => count($ids)]))
            ->success()
            ->send();
    }

    /**
     * Remove the records only; physical files are kept.
     *
     * @param  array<int, int>  $ids
     */
    public function delete(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $count = Utils::getMediaModel()::query()
            ->scopeTenant()
            ->whereIn('id', $ids)
            ->delete();

        if ($this->previewId && in_array($this->previewId, $ids, true)) {
            $this->clearPreview();
        }
        unset($this->medias);

        // Deleting also changes the per-directory counts.
        $this->dispatch('refresh-page');

        Notification::make()
            ->title(__('sn-media::media.notifications.deleted', ['count' => $count]))
            ->body(__('sn-media::media.notifications.delete_hint'))
            ->success()
            ->send();
    }

    // Preview

    public function openPreview(int $id): void
    {
        $media = Utils::getMediaModel()::query()
            ->scopeTenant()
            ->find($id);
        if (! $media) {
            return;
        }

        $this->previewId = $id;
        $this->metaName = $media->name;
        $this->metaAlt = (string) $media->alt;
        $this->dispatch('open-modal', id: 'sn-media-preview');
    }

    public function saveMeta(): void
    {
        $this->validate([
            'metaName' => ['nullable', 'string', 'max:255'],
            'metaAlt' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $this->previewId
            ? Utils::getMediaModel()::query()->scopeTenant()->find($this->previewId)
            : null;
        if (! $media) {
            return;
        }

        $media->update([
            'name' => trim($this->metaName) !== '' ? trim($this->metaName) : $media->name,
            'alt' => trim($this->metaAlt) !== '' ? trim($this->metaAlt) : null,
        ]);
        unset($this->medias);

        Notification::make()->title(__('sn-media::media.notifications.meta_saved'))->success()->send();
    }

    public function clearPreview(): void
    {
        $this->previewId = null;
        $this->dispatch('close-modal', id: 'sn-media-preview');
    }

    #[Computed]
    public function previewMedia(): ?Media
    {
        return $this->previewId
            ? Utils::getMediaModel()::query()->scopeTenant()->with('directory')->find($this->previewId)
            : null;
    }

    // Listing

    #[Computed]
    public function medias()
    {
        return $this->mediaQuery()
            ->with('directory')
            ->paginate($this->resolvePerPage($this->perPage));
    }
}
