<?php

namespace Wsmallnews\Media\Filament\Components\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Wsmallnews\Media\Enums\MediaType;
use Wsmallnews\Media\Filament\Concerns\UploadsMedia;
use Wsmallnews\Media\Models\Media;
use Wsmallnews\Media\Models\MediaDirectory;
use Wsmallnews\Media\Support\LibraryCounts;
use Wsmallnews\Media\Support\Utils;

/**
 * Shared library logic for MediaPage and the picker modal: directory × type × search
 * filters, directory tree, upload pipeline. Host must provide `$this->uploads`.
 * Hooks: resolveFileDirectory() (on-disk dir), resolveAcceptedMimes() (mime constraint).
 */
trait InteractsWithMedia
{
    use UploadsMedia;
    use WithPagination;

    /** Page-size candidates. Shared so both entries render the same pagination control. */
    public const PER_PAGE_OPTIONS = [12, 24, 48, 96];

    /** View mode: grid | list */
    public string $viewMode = 'grid';

    /** Sort key (see sortOptions()) */
    public string $sortBy = 'created_desc';

    /** Page size (see PER_PAGE_OPTIONS) */
    public int | string $perPage = 12;

    /** Search keyword */
    public string $librarySearch = '';

    /** Type filter ('' = all; combines with directory) */
    public string $libraryType = '';

    /** Directory filter (null = all, 0 = uncategorised, >0 = id; combines with type) */
    public ?int $libraryDir = null;

    /**
     * Temporary uploads (WithFileUploads)
     *
     * @var array<int, mixed>
     */
    public array $uploads = [];

    // ============ Queries ============

    /**
     * Tenant-scoped base query, without directory/type/search filters.
     * Used where the number must stay stable (chip counts).
     */
    protected function baseQuery()
    {
        return Utils::getMediaModel()::query()->scopeTenant();
    }

    /**
     * Combined query: tenant + directory (with descendants) + type + search + sort.
     * Each filter is one flat `if`; callers may append their own conditions.
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
     * Sort (seven options). Called directly by mediaQuery(): both entries need exactly
     * the same ordering, so it is not exposed as a separate extension point.
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
     * Directory ids matched by the filter: the directory itself plus descendants.
     * Returns [-1] when it no longer exists — an empty set, not "no condition".
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

    // ============ Sort / pagination options ============

    /**
     * Sort options (value => label) rendered by the toolbar, so both entries stay in sync.
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
     * Normalise the page size: the pagination control sends a string, and anything
     * outside the candidates falls back to the first option (it ends up in SQL).
     */
    protected function resolvePerPage(int | string $perPage): int
    {
        $perPage = filter_var($perPage, FILTER_VALIDATE_INT);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];
    }

    // ============ Property hooks ============
    //
    // Livewire calls updatedXxx() after assignment. Every filter/page-size dimension only
    // has to reset the page — add the hook when adding a dimension, or you stay on page N.

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

    // ============ Controlled setters ============
    //
    // Views call setViewMode('list') etc. through wire:click: validate the value, then
    // write the property. Sort/page size use wire:model.live and the hooks above.

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
    }

    /**
     * Combined filter setter (does not clear the other dimension)
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
     * Type counts in one aggregate query (kept in sync with scopeOfMediaType).
     * Based on baseQuery so the "all" number never drifts with active filters.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function typeCounts(): array
    {
        // Several pickers in one request compute the same counts: cache per request
        // (Support/LibraryCounts) instead of running count(*) + 8 sum(case ...) each.
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
     * Uncategorised count for the "Uncategorised" tree row — shared by both entries.
     */
    #[Computed]
    public function uncategorizedCount(): int
    {
        $cache = app(LibraryCounts::class);
        $cacheKey = (string) (current_tenant()?->getKey() ?? '');

        return $cache->uncategorized[$cacheKey] ??= $this->baseQuery()->uncategorized()->count();
    }

    // ============ Empty state ============

    /**
     * Whether any filter is active. "No results" and "empty library" need different copy.
     */
    public function hasActiveLibraryFilter(): bool
    {
        return $this->librarySearch !== '' || $this->libraryType !== '' || $this->libraryDir !== null;
    }

    // ============ Upload ============

    /**
     * Upload and record (content-hash path + same-day dedupe), filed under the current directory.
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

        // The tree is a separate component (MediaDirectories): a parent re-render does not
        // refresh it, and its counts already changed — ask it to reload itself.
        $this->dispatch('refresh-page');

        $this->notifyMediaUploaded($count);
    }

    /**
     * Target directory for uploads: the one being browsed (all / uncategorised => null).
     * Not a hook — it derives from this trait's own browsing state, identical for both entries.
     */
    protected function uploadDirectoryId(): ?int
    {
        if ($this->libraryDir !== null && $this->libraryDir > 0) {
            return $this->libraryDir;
        }

        return null;
    }

    /**
     * On-disk directory (hook): {fileDirectory}/{Ymd}/{hash}.{ext} — unrelated to the
     * logical directory (directory_id); moving a file between directories keeps its path.
     *
     * null falls back to config('sn-media.file_directory'); the date layer is applied by
     * Utils::withDate() idempotently. A method rather than a property because MediaPage
     * has no notion of a field-level directory.
     */
    protected function resolveFileDirectory(): ?string
    {
        return null;
    }

    /**
     * Field-level mime constraint (hook): empty = unrestricted. One value drives upload
     * validation, the picker candidate list and the file input's accept attribute.
     *
     * @return array<int, string>|null
     */
    protected function resolveAcceptedMimes(): ?array
    {
        return null;
    }

    /**
     * After a successful upload (Picker overrides this to select the new media).
     */
    protected function afterMediaUploaded($media): void
    {
        //
    }

    // ============ Misc ============

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
