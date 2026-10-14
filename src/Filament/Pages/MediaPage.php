<?php

namespace Wsmallnews\Media\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Modal;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
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

    /**
     * 页头动作：仅上传入口。
     * 目录/素材操作 Action 全部以 `{name}Action()` 公共方法定义（Filament 解析约定），
     * 由视图中的 ActionGroup / mountAction 按记录参数触发，不在页头注册。
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label(__('sn-media::media.toolbar.upload'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->alpineClickHandler("\$dispatch('sn-media-open-upload')"),
        ];
    }

    // ===== 目录 CRUD Actions（目录树行内触发） =====

    public function snMediaDirectoryCreateAction(): Action
    {
        return Action::make('snMediaDirectoryCreate')
            ->label(__('sn-media::media.directories.create'))
            ->icon(Heroicon::OutlinedFolderPlus)
            // mountAction 时按 arguments 重新解析 action，标题需闭包取参才能带上父目录名
            ->modalHeading(function (array $arguments): string {
                $parent = $arguments['parentId'] ?? null;

                return $parent
                    ? __('sn-media::media.directories.create_in', ['name' => MediaDirectory::find($parent)?->name ?? ''])
                    : __('sn-media::media.directories.create');
            })
            ->modalWidth(Width::Medium)
            ->schema([
                TextInput::make('name')
                    ->label(__('sn-media::media.directories.name'))
                    ->maxLength(20)
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->directoryForm = $data;
                $this->createDirectory($arguments['parentId'] ?? null);
            });
    }

    public function snMediaDirectoryRenameAction(): Action
    {
        return Action::make('snMediaDirectoryRename')
            ->label(__('sn-media::media.directories.rename_short'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(__('sn-media::media.directories.rename'))
            ->modalWidth(Width::Medium)
            ->fillForm(fn (array $arguments): array => ['name' => MediaDirectory::find($arguments['id'] ?? 0)?->name ?? ''])
            ->schema([
                TextInput::make('name')
                    ->label(__('sn-media::media.directories.name'))
                    ->maxLength(20)
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->directoryForm = $data;
                $this->renameDirectory((int) ($arguments['id'] ?? 0));
            });
    }

    public function snMediaDirectoryDeleteAction(): Action
    {
        return Action::make('snMediaDirectoryDelete')
            ->label(__('sn-media::media.directories.delete_short'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('sn-media::media.directories.delete'))
            ->modalDescription(function (array $arguments): string {
                $dir = MediaDirectory::find($arguments['id'] ?? 0);
                if (! $dir) {
                    return '';
                }

                $count = Utils::getMediaModel()::query()
                    ->whereIn('directory_id', $dir->selfAndDescendantIds())
                    ->count();

                return $count > 0
                    ? __('sn-media::media.directories.delete_files_hint', ['count' => $count])
                    : __('sn-media::media.directories.delete_empty_hint');
            })
            ->action(function (array $arguments): void {
                $this->deleteDirectory((int) ($arguments['id'] ?? 0));
            });
    }

    /**
     * 目录树行 ⋯ 下拉菜单（ActionGroup：创建子目录 / 重命名 / 删除）。
     * 菜单项用短名（体现子目录语义），modal 标题保持完整描述。
     *
     * @param  array{id: int, name: string, depth: int, count: int}  $dir
     */
    public function directoryMenuActions(array $dir): ActionGroup
    {
        return ActionGroup::make([
            ($this->snMediaDirectoryCreateAction)(['parentId' => $dir['id']])
                ->label(__('sn-media::media.directories.create_child'))
                ->visible(fn (): bool => $dir['depth'] < MediaDirectory::MAX_DEPTH - 1),
            ($this->snMediaDirectoryRenameAction)(['id' => $dir['id']]),
            ($this->snMediaDirectoryDeleteAction)(['id' => $dir['id']]),
        ])
            ->iconButton()
            ->icon(Heroicon::OutlinedEllipsisVertical)
            ->color('gray')
            ->tooltip(__('sn-media::media.list.more_actions'))
            // 面板传送到 body：目录树容器 overflow 会裁剪内嵌面板；右对齐触发器向左下展开
            ->dropdownTeleport()
            ->dropdownPlacement('bottom-end');
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
     * 目录筛选（组合语义：不清空类型）
     */
    public function filterByDir(?int $dir = null): void
    {
        $this->setLibraryDir($dir);
    }

    // ============ 目录 CRUD ============

    /**
     * 新建目录（可选父级；层级 ≤ 3）
     */
    public function createDirectory(?int $parentId = null): void
    {
        $name = trim($this->directoryForm['name'] ?? '');

        if ($name === '' || mb_strlen($name) > 20) {
            Notification::make()->title(__('sn-media::media.directories.name_invalid'))->danger()->send();

            return;
        }

        try {
            $parent = $parentId ? MediaDirectory::find($parentId) : null;
            MediaDirectory::createChild($name, $parent, $this->resolveTeamId());
        } catch (\Throwable $e) {
            Notification::make()->title(__('sn-media::media.directories.depth_limit', ['max' => MediaDirectory::MAX_DEPTH]))->danger()->send();

            return;
        }

        $this->directoryForm = [];
        unset($this->libraryDirectories);
        Notification::make()
            ->title(__('sn-media::media.directories.created'))
            ->body(__('sn-media::media.directories.created_hint'))
            ->success()
            ->send();
    }

    /**
     * 重命名目录（纯逻辑，不影响文件路径）
     */
    public function renameDirectory(int $dirId): void
    {
        $name = trim($this->directoryForm['name'] ?? '');
        $dir = MediaDirectory::find($dirId);

        if (! $dir || $name === '' || mb_strlen($name) > 20) {
            Notification::make()->title(__('sn-media::media.directories.name_invalid'))->danger()->send();

            return;
        }

        $dir->update(['name' => $name]);
        $this->directoryForm = [];
        unset($this->libraryDirectories);
        Notification::make()
            ->title(__('sn-media::media.directories.renamed'))
            ->body(__('sn-media::media.directories.renamed_hint'))
            ->success()
            ->send();
    }

    /**
     * 删除目录（非空 → 文件回落未归类）
     */
    public function deleteDirectory(int $dirId): void
    {
        $dir = MediaDirectory::find($dirId);

        if (! $dir) {
            return;
        }

        $fileCount = Utils::getMediaModel()::query()
            ->whereIn('directory_id', $dir->selfAndDescendantIds())
            ->count();

        $dir->deleteWithDescendants();

        if ($this->libraryDir === $dirId) {
            $this->libraryDir = null;
        }

        unset($this->libraryDirectories, $this->medias);
        Notification::make()
            ->title(__('sn-media::media.directories.deleted'))
            ->body($fileCount > 0 ? __('sn-media::media.directories.deleted_files_fallback', ['count' => $fileCount]) : '')
            ->success()
            ->send();
    }

    /** @var array<string, mixed> 目录表单（新建/重命名共用） */
    public array $directoryForm = [];

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
