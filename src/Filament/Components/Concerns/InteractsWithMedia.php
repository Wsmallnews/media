<?php

namespace Wsmallnews\Media\Filament\Components\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Wsmallnews\Media\Enums\MediaType;
use Wsmallnews\Media\Models\Media;
use Wsmallnews\Media\Models\MediaDirectory;
use Wsmallnews\Media\Support\Concerns\UploadsMedia;
use Wsmallnews\Media\Support\LibraryCounts;
use Wsmallnews\Media\Support\Utils;

/**
 * 素材库通用逻辑（管理页 MediaPage 与选择弹窗 Picker 共享，保证同构）：
 * - 组合筛选：目录（含子孙）× 类型 × 搜索，互不清空
 * - 目录树：实体嵌套集目录（含累计文件数），与管理页共用
 * - 上传管道：内容哈希路径 + 当天秒传 + 归入当前目录
 *
 * 视图模式 / 排序 / 每页条数由 trait 统一声明；管理页用相同默认值重声明
 * 并加 #[Url]（Livewire 的 Url 只能挂在具体类的属性上），弹窗不需要写地址栏。
 *
 * 使用方需提供：
 * - $this->uploads 属性（WithFileUploads）
 *
 * 使用方按需覆写（hook，默认「无约束」）：
 * - resolveFileDirectory()  落盘目录（字段/业务自定义）
 * - resolveAcceptedMimes()  字段级 mime 约束（列表过滤 + 上传校验共用同一来源）
 */
trait InteractsWithMedia
{
    use UploadsMedia;
    use WithPagination;

    /**
     * 每页条数候选（管理页与选择弹窗共用，保证两个入口的分页控件长得一样）。
     * 放在 trait 里是因为两侧都要给 x-filament::pagination 的 :page-options 传同一份值。
     */
    public const PER_PAGE_OPTIONS = [12, 24, 48, 96];

    /** 视图模式：grid | list */
    public string $viewMode = 'grid';

    /** 排序方式（值域见 sortOptions()） */
    public string $sortBy = 'created_desc';

    /** 每页条数（值域见 PER_PAGE_OPTIONS） */
    public int | string $perPage = 12;

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
        return Utils::getMediaModel()::query()->scopeTenant();
    }

    /**
     * 组合条件查询（租户 + 目录(含子孙) + 类型 + 搜索 + 排序）
     *
     * 筛选维度按「一个维度一段 if」平铺，不再互相套壳。
     * 调用方拿到的是可继续追加条件的 builder（使用方再补 with() / 字段级约束）。
     *
     * @return Builder<Media>
     */
    protected function mediaQuery()
    {
        $query = $this->baseQuery();

        if ($this->libraryDir === 0) {
            $query->uncategorized();
        } elseif ($this->libraryDir !== null && $this->libraryDir > 0) {
            $query->inDirectories($this->libraryDirIds($this->libraryDir));
        }

        if ($type = MediaType::tryFrom($this->libraryType)) {
            $query->ofMediaType($type);
        }

        if ($this->librarySearch !== '') {
            $query->search($this->librarySearch);
        }

        return $this->applySort($query);
    }

    /**
     * 排序（七档）。由 mediaQuery() 直接调用：两个入口的排序需求完全一致，
     * 没有「同一份筛选要另一种排序」的场景，所以不单独外挂在 medias() 上。
     *
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    protected function applySort($query)
    {
        return match ($this->sortBy) {
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
     * 目录筛选命中的目录 id 集合（自身 + 所有子孙）。
     * 目录不存在时返回 [-1]（空集），避免变成「不加条件 = 全库」。
     *
     * @return array<int, int>
     */
    protected function libraryDirIds(int $directoryId): array
    {
        $directory = MediaDirectory::query()
            ->scopeTenant()
            ->find($directoryId);

        return $directory ? $directory->selfAndDescendantIds() : [-1];
    }

    // ============ 排序 / 分页选项 ============

    /**
     * 七档排序（值 => 文案），工具栏 select 统一由此渲染，
     * 避免管理页与选择弹窗各写一份导致 option 漂移。
     *
     * @return array<string, string>
     */
    public function sortOptions(): array
    {
        return [
            'created_desc' => __('sn-media::media.toolbar.sort.created_desc'),
            'created_asc' => __('sn-media::media.toolbar.sort.created_asc'),
            'name_asc' => __('sn-media::media.toolbar.sort.name_asc'),
            'name_desc' => __('sn-media::media.toolbar.sort.name_desc'),
            'size_desc' => __('sn-media::media.toolbar.sort.size_desc'),
            'size_asc' => __('sn-media::media.toolbar.sort.size_asc'),
            'type' => __('sn-media::media.toolbar.sort.type'),
        ];
    }

    /**
     * 每页条数归一：分页控件回传的是字符串，非候选值一律回落到第一档
     * （否则 perPage 会被拼进 SQL，既是注入面也会让分页信息算错）。
     */
    protected function resolvePerPage(int | string $perPage): int
    {
        $perPage = filter_var($perPage, FILTER_VALIDATE_INT);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];
    }

    // ============ 属性更新钩子 ============
    //
    // Livewire 约定：属性 xxx 被赋值后自动调用 updatedXxx()。
    // 五个筛选/分页维度的钩子集中在这里而不是散落各处 —— 它们都只做「回到第一页」，
    // 新增维度时照着这一段补，就不会漏掉 resetPage（漏了会停在空白的第 N 页）。

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->resolvePerPage($this->perPage);
        $this->resetPage();
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

    // ============ 受控 setter ============
    //
    // 视图里 wire:click="setViewMode('list')" 这类调用：先校验值域，再写属性。
    // （排序/分页走 wire:model.live 双向绑定，由上面的 updatedXxx 接管，不需要 setter。）

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
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

    /**
     * 类型计数（单查询条件聚合，与 scopeOfMediaType 条件同步）。
     * 基于 baseQuery（仅租户、不含筛选），保证「全部」计数恒定不随目录/类型筛选漂移。
     *
     * @return array<string, int>
     */
    #[Computed]
    public function typeCounts(): array
    {
        // 同一请求里多个 picker 算的是同一份计数：走请求内缓存（见 Support/LibraryCounts），
        // 避免 N 个 picker 各跑一次 count(*) + 8 个 sum(case ...)。
        $cache = app(LibraryCounts::class);
        $cacheKey = (string) (current_tenant()?->getKey() ?? '');

        if (isset($cache->typeCounts[$cacheKey])) {
            return $cache->typeCounts[$cacheKey];
        }

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

        return $cache->typeCounts[$cacheKey] = $counts;
    }

    /**
     * 未归类文件数（左树「未归类」行的计数）。
     * 管理页与选择弹窗共用，避免只有管理页有计数、picker 那行是空的。
     */
    #[Computed]
    public function uncategorizedCount(): int
    {
        $cache = app(LibraryCounts::class);
        $cacheKey = (string) (current_tenant()?->getKey() ?? '');

        return $cache->uncategorized[$cacheKey] ??= $this->baseQuery()->uncategorized()->count();
    }

    // ============ 空态判据 ============

    /**
     * 当前是否处于筛选态（搜索 / 类型 / 目录任一生效）。
     * 空态文案判据：筛选没结果 ≠ 库里没素材，两种提示必须分开。
     */
    public function hasActiveLibraryFilter(): bool
    {
        return $this->librarySearch !== '' || $this->libraryType !== '' || $this->libraryDir !== null;
    }

    // ============ 上传 ============

    /**
     * 上传即入账（hash 路径 + 当天秒传），归入当前目录
     */
    public function updatedUploads(): void
    {
        $count = 0;

        foreach ($this->uploads as $file) {
            try {
                $media = $this->uploadMediaFile(
                    $file,
                    directory: $this->uploadDirectoryId(),
                    fileDirectory: $this->resolveFileDirectory(),
                    acceptedMimes: $this->resolveAcceptedMimes(),
                );
                $this->afterMediaUploaded($media);
                $count++;
            } catch (\Throwable $e) {
                $this->uploadFailed($e->getMessage());
            }
        }

        $this->uploads = [];
        $this->resetPage();

        // 左树是独立组件（MediaDirectories），父组件重渲染不会带它一起重算，
        // 而目录上的累计计数已经变了 —— 用嵌套集的通用刷新事件让它自己重新取数。
        // （以前这里是 unset($this->libraryDirectories)，树还在本组件里渲染时才有意义。）
        $this->dispatch('refresh-page');

        $this->notifyMediaUploaded($count);
    }

    /**
     * 上传目标目录（当前浏览的目录；全部/未归类 → 未归类 null）。
     *
     * 唯一一个不需要 hook 的上传要素：它由 trait 自己的浏览态（$libraryDir）推导，
     * 两个入口的规则完全一致，没有「我这边的上传该进哪个目录」的差异化空间。
     */
    protected function uploadDirectoryId(): ?int
    {
        if ($this->libraryDir !== null && $this->libraryDir > 0) {
            return $this->libraryDir;
        }

        return null;
    }

    /**
     * 落盘目录（hook，使用方按需覆写）：{fileDirectory}/{Ymd}/{hash}.{ext}。
     * 与「逻辑目录」（directory_id）无关——搬目录不会改变 path。
     *
     * 默认 null → 走 config('sn-media.file_directory')，日期由 Utils::withDate() 幂等补齐。
     * 之所以是可覆写方法而不是 trait 属性：管理页没有「字段级落盘目录」这个概念，
     * 若把属性提到 trait 里，管理页就得挂一个它永远不用的公开属性（还会被当成可绑定状态）。
     */
    protected function resolveFileDirectory(): ?string
    {
        return null;
    }

    /**
     * 字段级 mime 约束（hook，使用方按需覆写）：空 = 不限。
     *
     * 同一个值管三处，所以名字不带 upload —— 它不只约束上传：
     * 1. 上传校验（MediaManager::upload 的 acceptedMimes）；
     * 2. 选择弹窗的候选列表过滤（Picker::medias 的 ofMimePatterns）；
     * 3. 前端 file input 的 accept 属性（picker.blade.php 读的就是本 hook）。
     *
     * 三处都只认 hook、不认具体属性：使用方覆写后三处同时生效，不会各走一套。
     *
     * @return array<int, string>|null
     */
    protected function resolveAcceptedMimes(): ?array
    {
        return null;
    }

    /**
     * 上传成功后钩子（使用方覆写：Picker 自动选中）
     */
    protected function afterMediaUploaded($media): void
    {
        //
    }

    // ============ 通用 ============

    /**
     * Directory breadcrumb: ancestor chain from root to the selected directory
     * (each ancestor is clickable in the breadcrumb to switch directory).
     *
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function directoryBreadcrumb(): array
    {
        if (is_null($this->libraryDir) || $this->libraryDir <= 0) {
            return [];
        }

        $dir = MediaDirectory::query()
            ->scopeTenant()
            ->find($this->libraryDir);

        if (! $dir) {
            return [];
        }

        return $dir->ancestors()
            ->orderBy('_lft')
            ->get()
            ->map(fn (MediaDirectory $ancestor) => ['id' => $ancestor->id, 'name' => $ancestor->name])
            ->push(['id' => $dir->id, 'name' => $dir->name])
            ->all();
    }
}
