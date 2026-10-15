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
use Wsmallnews\Media\Livewire\Concerns\InteractsWithMediaLibrary;
use Wsmallnews\Media\Models\Media;
use Wsmallnews\Media\Models\MediaDirectory;
use Wsmallnews\Media\Support\Utils;
use Wsmallnews\Support\Filament\Concerns\CanBeConfigured;
use Wsmallnews\Support\Filament\Pages\PageConfiguration;

final class MediaPage extends Page implements HasActions
{
    use CanBeConfigured;
    use InteractsWithActions;
    use InteractsWithMediaLibrary;
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

    /** @var int|string 每页条数（'all' = 不分页） */
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

    // ===== 素材操作 Actions（瓦片/列表行 ⋯ 下拉菜单项，mountAction + {id/ids} 触发） =====

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
                $media = Utils::getMediaModel()::query()->find($arguments['id'] ?? 0);
                if ($media) {
                    $this->js("navigator.clipboard?.writeText('{$media->path}')");
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
                    ->query(fn () => MediaDirectory::query(), titleAttribute: 'name', parentAttribute: 'parent_id'),
            ])
            ->action(function (array $arguments, array $data): void {
                $ids = $arguments['ids'] ?? [$arguments['id'] ?? null];
                $this->move(array_filter($ids), ($data['directory_id'] ?? '') === '' ? null : (int) $data['directory_id']);

                // 预览弹层中的目录列已过期，统一关闭
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
     * 素材行 ⋯ 下拉菜单（ActionGroup：预览 / 复制路径 / 下载 / 移动 / 删除）
     */
    public function mediaMenuActions(Media $media): ActionGroup
    {
        return ActionGroup::make([
            ($this->snMediaPreviewAction)(['id' => $media->id]),
            ($this->snMediaCopyPathAction)(['id' => $media->id]),
            Action::make('snMediaDownload')
                ->label(__('sn-media::media.list.download'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                // 面板启用 SPA 后带 href 的动作会被 wire:navigate 拦截成 fetch（二进制内容直接渲染成文本），
                // 这里改为 JS 导航：attachment 响应会原地触发浏览器下载，不离开当前页
                ->alpineClickHandler('window.location.href = ' . Js::from(route('sn-media.download', ['media' => $media]))),
            ($this->snMediaMoveAction)(['id' => $media->id]),
            ($this->snMediaDeleteAction)(['id' => $media->id]),
        ])
            ->iconButton()
            ->icon(Heroicon::OutlinedEllipsisVertical)
            ->color('gray')
            ->tooltip(__('sn-media::media.list.more_actions'))
            // 同目录树菜单：内容区滚动容器会裁剪内嵌面板，传送到 body 并右对齐
            ->dropdownTeleport()
            ->dropdownPlacement('bottom-end');
    }

    /**
     * 未归类文件数（左树"未归类"行的计数）
     */
    #[Computed]
    public function uncategorizedCount(): int
    {
        return Utils::getMediaModel()::query()
            ->where('team_id', $this->resolveTeamId())
            ->whereNull('directory_id')
            ->count();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
    }

    /**
     * 类型筛选（组合语义：不清空目录）
     */
    public function filterByType(string $type = ''): void
    {
        $this->setLibraryType($type);
    }

    /**
     * 目录筛选（组合语义：不清空类型）。
     * 同时接收 MediaDirectories 树组件的选择事件（payload 键 recordId 与参数名一致）；
     * 面包屑与窄屏下拉仍直接调用。
     */
    #[On('sn-filament-nestedset-leaf-click')]
    #[On('sn-filament-nestedset-node-click')]
    public function filterByDir(?int $recordId = null): void
    {
        $this->setLibraryDir($recordId);
    }

    /**
     * 目录树 CRUD（MediaDirectories 组件内完成）后刷新本页：
     * 空监听触发重渲染，让面包屑/计数/列表跟随树变更；
     * 删除当前选中目录时树组件已派发 recordId = null 回「全部」。
     */
    #[On('sn-filament-nestedset-updated')]
    public function refreshLibrary(): void
    {
        //
    }

    // ============ 文件操作 ============

    /**
     * 批量移动到目录（纯逻辑归属，path 不变）
     *
     * @param  array<int, int>  $ids
     */
    public function move(array $ids, ?int $targetDirId = null): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        Utils::getMediaModel()::query()->whereIn('id', $ids)->update(['directory_id' => $targetDirId]);
        unset($this->medias, $this->libraryDirectories);
        Notification::make()
            ->title(__('sn-media::media.notifications.moved', ['count' => count($ids)]))
            ->success()
            ->send();
    }

    /**
     * 删除仅移除素材记录，物理文件保留
     *
     * @param  array<int, int>  $ids
     */
    public function delete(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $count = Utils::getMediaModel()::query()->whereIn('id', $ids)->delete();

        if ($this->previewId && in_array($this->previewId, $ids, true)) {
            $this->previewId = null;
        }
        unset($this->medias, $this->libraryDirectories);

        Notification::make()
            ->title(__('sn-media::media.notifications.deleted', ['count' => $count]))
            ->body(__('sn-media::media.notifications.delete_hint'))
            ->success()
            ->send();
    }

    // ============ 预览 ============

    public function openPreview(int $id): void
    {
        $media = Utils::getMediaModel()::query()->find($id);
        if (! $media) {
            return;
        }

        $this->previewId = $id;
        $this->metaName = $media->name;
        $this->metaAlt = (string) $media->alt;
    }

    public function saveMeta(): void
    {
        $media = $this->previewId ? Utils::getMediaModel()::query()->find($this->previewId) : null;
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
    }

    #[Computed]
    public function previewMedia()
    {
        return $this->previewId ? Utils::getMediaModel()::query()->find($this->previewId) : null;
    }

    // ============ 列表 ============

    #[Computed]
    public function medias()
    {
        return $this->applySort($this->mediaQuery(), $this->sortBy)->paginate(is_numeric($this->perPage) ? (int) $this->perPage : 24);
    }

    public function getFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1073741824, 2) . ' GB';
    }
}
