<?php

namespace Wsmallnews\Media\Livewire\Concerns;

use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Wsmallnews\Media\Enums\MediaType;
use Wsmallnews\Media\Models\MediaDirectory;
use Wsmallnews\Media\Support\MediaManager;
use Wsmallnews\Media\Support\Utils;

/**
 * 素材库通用逻辑（管理页 MediaPage 与选择弹窗 Picker 共享，保证同构）：
 * - 组合筛选：目录（含子孙）× 类型 × 搜索，互不清空
 * - 目录树：实体嵌套集目录（含累计文件数），与管理页共用
 * - 上传管道：内容哈希路径 + 当天秒传 + 归入当前目录
 *
 * 使用方需提供：$this->uploads 属性（WithFileUploads）。
 */
trait InteractsWithMediaLibrary
{
    use WithPagination;

    /** 搜索关键词 */
    public string $librarySearch = '';

    /** 类型筛选（'' = 全部；与目录组合，不清空目录） */
    public string $libraryType = '';

    /** 目录筛选（null = 全部，0 = 未归类，>0 = 目录 id；与类型组合） */
    public ?int $libraryDir = null;

    /**
     * 上传的临时文件（WithFileUploads）
     *
     * @var array<int, mixed>
     */
    public array $uploads = [];

    // ============ 查询 ============

    /**
     * 基础查询：仅租户隔离（不含目录/类型/搜索筛选）
     * 用于 chips 计数等需要恒定数字的场景
     */
    protected function baseQuery()
    {
        $query = Utils::getMediaModel()::query();

        $tenant = rescue(fn () => filament()?->getTenant(), null, false);
        if ($tenant) {
            $query->where('team_id', $tenant->getKey());
        }

        return $query;
    }

    /**
     * 组合条件查询（租户 + 目录(含子孙) + 类型 + 搜索），排序由使用方追加
     */
    protected function mediaQuery()
    {
        $query = $this->baseQuery();

        if ($this->libraryDir === 0) {
            $query->uncategorized();
        } elseif ($this->libraryDir !== null && $this->libraryDir > 0) {
            $directory = MediaDirectory::find($this->libraryDir);
            if ($directory) {
                $query->inDirectories($directory->selfAndDescendantIds());
            }
        }

        if ($type = MediaType::tryFrom($this->libraryType)) {
            $query->ofMediaType($type);
        }

        if ($this->librarySearch !== '') {
            $query->search($this->librarySearch);
        }

        return $query;
    }

    /**
     * 排序（方案 A 七档，含目录维度由使用方追加）
     */
    protected function applySort($query, string $sortBy = 'created_desc')
    {
        return match ($sortBy) {
            'created_asc' => $query->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderByDesc('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'size_asc' => $query->orderBy('size')->orderByDesc('id'),
            'size_desc' => $query->orderByDesc('size')->orderByDesc('id'),
            'type' => $query->orderBy('mime')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };
    }

    /**
     * 类型计数（单查询条件聚合，与 scopeOfMediaType 条件同步）。
     * 基于 baseQuery（仅租户、不含筛选），保证「全部」计数恒定不随目录/类型筛选漂移。
     *
     * @return array<string, int>
     */
    #[Computed]
    public function typeCounts(): array
    {
        $conditions = [
            'image' => "mime like 'image/%'",
            'video' => "mime like 'video/%'",
            'audio' => "mime like 'audio/%'",
            'pdf' => "(mime = 'application/pdf' or ext = 'pdf')",
            'word' => "(mime like '%word%' or mime like '%wordprocessingml%' or ext in ('doc', 'docx', 'rtf'))",
            'excel' => "(mime like '%excel%' or mime like '%spreadsheetml%' or ext in ('xls', 'xlsx', 'csv'))",
            'ppt' => "(mime like '%presentation%' or mime like '%powerpoint%' or ext in ('ppt', 'pptx'))",
            'zip' => "(mime like '%zip%' or ext in ('zip', 'rar', '7z'))",
        ];

        $selects = collect($conditions)
            ->map(fn (string $cond, string $key) => "sum(case when {$cond} then 1 else 0 end) as c_{$key}")
            ->implode(', ');

        $row = $this->baseQuery()->toBase()->selectRaw("count(*) as c_all, {$selects}")->first();

        $counts = ['all' => (int) $row->c_all];
        foreach (array_keys($conditions) as $key) {
            $counts[$key] = (int) $row->{"c_{$key}"};
        }

        return $counts;
    }

    // ============ 目录树 ============

    /**
     * 实体目录树：嵌套集原生能力组段——
     * withDepth() 单查询追加深度（0 基）、defaultOrder() 按 _lft 树序、toFlatTree() 平铺（父先子后）；
     * 文件数（含子孙）由一次 media 聚合后按 _lft/_rgt 区间向上累加。
     *
     * @return array<int, array{id: int, name: string, depth: int, count: int}>
     */
    #[Computed]
    public function libraryDirectories(): array
    {
        $teamId = $this->resolveTeamId();

        $nodes = MediaDirectory::query()
            ->where('team_id', $teamId)
            ->withDepth()
            ->defaultOrder()
            ->get();

        if ($nodes->isEmpty()) {
            return [];
        }

        // 目录 id → 直接归属文件数（一次查询，向上累加含子孙）
        $counts = Utils::getMediaModel()::query()
            ->where('team_id', $teamId)
            ->whereNotNull('directory_id')
            ->toBase()
            ->selectRaw('directory_id, count(*) as c')
            ->groupBy('directory_id')
            ->pluck('c', 'directory_id');

        return $nodes->toFlatTree()
            ->map(fn (MediaDirectory $dir) => [
                'id' => $dir->id,
                'name' => $dir->name,
                'depth' => (int) $dir->depth,
                'count' => $nodes
                    ->filter(fn (MediaDirectory $n) => $n->_lft >= $dir->_lft && $n->_rgt <= $dir->_rgt)
                    ->sum(fn (MediaDirectory $n) => (int) ($counts[$n->id] ?? 0)),
            ])
            ->values()
            ->all();
    }

    // ============ 上传 ============

    /**
     * 上传即入账（hash 路径 + 当天秒传），归入当前目录
     */
    public function updatedUploads(): void
    {
        $manager = app(MediaManager::class);
        $count = 0;

        foreach ($this->uploads as $file) {
            try {
                $media = $manager->upload($file, directory: $this->uploadDirectoryId());
                $this->afterMediaUploaded($media);
                $count++;
            } catch (\Throwable $e) {
                Notification::make()
                    ->title(__('sn-media::media.notifications.upload_rejected', ['reason' => $e->getMessage()]))
                    ->danger()
                    ->send();
            }
        }

        $this->uploads = [];
        $this->resetPage();
        unset($this->libraryDirectories);

        if ($count > 0) {
            Notification::make()->title(__('sn-media::media.notifications.uploaded', ['count' => $count]))->success()->send();
        }
    }

    /**
     * 上传目标目录（当前浏览的目录；全部/未归类 → 未归类 null）
     */
    protected function uploadDirectoryId(): ?int
    {
        if ($this->libraryDir !== null && $this->libraryDir > 0) {
            return $this->libraryDir;
        }

        return null;
    }

    /**
     * 上传成功后钩子（使用方覆写：Picker 自动选中）
     */
    protected function afterMediaUploaded($media): void
    {
        //
    }

    /**
     * 上传被拒（Livewire upload-error 事件经 JS 转发，可读原因通知）
     */
    public function uploadFailed(string $message = ''): void
    {
        Notification::make()
            ->title(__('sn-media::media.notifications.upload_rejected', [
                'reason' => $message !== '' ? $message : __('sn-media::media.notifications.upload_rejected_default'),
            ]))
            ->danger()
            ->send();
    }

    // ============ 通用 ============

    protected function resolveTeamId(): ?int
    {
        $tenant = rescue(fn () => filament()?->getTenant(), null, false);

        return $tenant?->getKey();
    }

    public function updatedLibrarySearch(): void
    {
        $this->resetPage();
    }

    public function updatedLibraryType(): void
    {
        $this->resetPage();
    }

    public function updatedLibraryDir(): void
    {
        $this->resetPage();
    }

    /**
     * 组合筛选设置（不清空另一维度）
     */
    public function setLibraryType(string $type = ''): void
    {
        $this->libraryType = MediaType::tryFrom($type) ? $type : '';
        $this->resetPage();
    }

    public function setLibraryDir(?int $dir = null): void
    {
        $this->libraryDir = $dir;
        $this->resetPage();
    }
}
