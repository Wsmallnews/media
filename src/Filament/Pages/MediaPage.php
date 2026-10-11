<?php

namespace Wsmallnews\Media\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use UnitEnum;
use Wsmallnews\Media\Enums\MediaType;
use Wsmallnews\Media\Support\MediaManager;
use Wsmallnews\Media\Support\Utils;
use Wsmallnews\Support\Filament\Concerns\CanBeConfigured;
use Wsmallnews\Support\Filament\Pages\PageConfiguration;

final class MediaPage extends Page
{
    use CanBeConfigured;
    use WithFileUploads;
    use WithPagination;

    protected static ?string $configurationClass = PageConfiguration::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $slug = 'media';

    protected static ?int $navigationSort = 1;

    protected string $view = 'sn-media::filament.pages.media';

    #[Url]
    public string $viewMode = 'grid';

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterType = '';

    #[Url]
    public string $filterDir = '';

    public string $sortBy = 'created_desc';

    /** @var array<int, mixed> */
    public array $uploads = [];

    public ?int $previewId = null;

    public string $metaName = '';

    public string $metaAlt = '';

    public static function getModelLabel(): string
    {
        return __('sn-media::media.media.model_label');
    }

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

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDir(): void
    {
        $this->resetPage();
    }

    /**
     * 素材查询：租户隔离 + 类型/目录过滤 + 搜索 + 排序 + 分页
     */
    protected function mediaQuery()
    {
        $query = Utils::getMediaModel()::query();

        $tenant = rescue(fn () => filament()?->getTenant(), null, false);
        if ($tenant) {
            $query->where('team_id', $tenant->getKey());
        }

        if ($type = MediaType::tryFrom($this->filterType)) {
            $query->ofMediaType($type);
        }

        if ($this->filterDir !== '') {
            $query->directory($this->filterDir);
        }

        if ($this->search !== '') {
            $query->search($this->search);
        }

        return match ($this->sortBy) {
            'name_asc' => $query->orderBy('name')->orderByDesc('id'),
            'size_desc' => $query->orderByDesc('size')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };
    }

    #[Computed]
    public function medias()
    {
        return $this->mediaQuery()->paginate(24);
    }

    /**
     * 类型计数（左树）
     */
    #[Computed]
    public function typeCounts(): array
    {
        $query = Utils::getMediaModel()::query();

        $tenant = rescue(fn () => filament()?->getTenant(), null, false);
        if ($tenant) {
            $query->where('team_id', $tenant->getKey());
        }

        $counts = ['all' => (clone $query)->count()];
        foreach (MediaType::cases() as $type) {
            if ($type->isFilterable()) {
                $counts[$type->value] = (clone $query)->ofMediaType($type)->count();
            }
        }

        return $counts;
    }

    /**
     * 虚拟目录树：path 前缀（dirname）去重排序，平铺 + 深度缩进
     *
     * @return array<int, array{prefix: string, label: string, depth: int, count: int}>
     */
    #[Computed]
    public function directories(): array
    {
        $query = Utils::getMediaModel()::query();

        $tenant = rescue(fn () => filament()?->getTenant(), null, false);
        if ($tenant) {
            $query->where('team_id', $tenant->getKey());
        }

        $dirnames = $query->pluck('path')
            ->map(fn (string $path) => dirname($path))
            ->filter(fn (string $dir) => $dir !== '.' && $dir !== '')
            ->unique()
            ->sort()
            ->values();

        // 补齐中间路径节点（sn/shop → sn/shop/product 全部出现）
        $prefixes = collect($dirnames)
            ->flatMap(fn (string $dirname) => $this->pathPrefixes($dirname))
            ->unique()
            ->sort()
            ->values();

        return $prefixes
            ->map(function (string $prefix) use ($dirnames) {
                $segments = explode('/', $prefix);

                return [
                    'prefix' => $prefix,
                    'label' => end($segments),
                    'depth' => count($segments) - 1,
                    'count' => $dirnames->filter(fn (string $d) => str_starts_with($d, $prefix))->count(),
                ];
            })
            ->all();
    }

    /**
     * path 的全部祖先前缀（sn/a/b => [sn, sn/a, sn/a/b]）
     *
     * @return array<int, string>
     */
    protected function pathPrefixes(string $dirname): array
    {
        $segments = explode('/', $dirname);
        $prefixes = [];
        $current = '';
        foreach ($segments as $segment) {
            $current = $current === '' ? $segment : $current . '/' . $segment;
            $prefixes[] = $current;
        }

        return $prefixes;
    }

    /**
     * 上传入账（统一管道）；完成后清空队列并通知
     */
    public function updatedUploads(): void
    {
        $manager = app(MediaManager::class);
        $count = 0;

        foreach ($this->uploads as $file) {
            try {
                $manager->upload($file);
                $count++;
            } catch (\Throwable $e) {
                Notification::make()
                    ->title(__('sn-media::media.notifications.upload_failed', ['reason' => $e->getMessage()]))
                    ->danger()
                    ->send();
            }
        }

        $this->uploads = [];

        if ($count > 0) {
            unset($this->medias, $this->typeCounts, $this->directories);
            Notification::make()->title(__('sn-media::media.notifications.uploaded', ['count' => $count]))->success()->send();
        }
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
        unset($this->medias, $this->typeCounts, $this->directories);

        Notification::make()
            ->title(__('sn-media::media.notifications.deleted', ['count' => $count]))
            ->body(__('sn-media::media.notifications.delete_hint'))
            ->success()
            ->send();
    }

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

    /**
     * 预览中的素材
     */
    #[Computed]
    public function previewMedia()
    {
        return $this->previewId ? Utils::getMediaModel()::query()->find($this->previewId) : null;
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
