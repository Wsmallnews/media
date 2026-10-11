<?php

namespace Wsmallnews\Media\Livewire\Components;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Wsmallnews\Media\Enums\MediaType;
use Wsmallnews\Media\Support\MediaManager;
use Wsmallnews\Media\Support\Utils;

/**
 * 素材选择弹窗（内嵌组件）：约束一律经 props 注入，不读模块默认值。
 * 确认后 dispatch sn-media-selected（statePath + values + urls），由 MediaPicker 字段的
 * Alpine 监听回填表单状态；取消 dispatch sn-media-picker-closed 关闭弹窗。
 */
class Picker extends Component
{
    use WithFileUploads;
    use WithPagination;

    public bool $multiple = false;

    /** @var array<int, string> mime 模式（image/* 等前缀或精确 mime），空 = 不限 */
    public array $acceptedMimes = [];

    public float $ratio = 1;

    public string $directory = 'sn/media';

    public string $statePath = '';

    /** 初始选中（值对象数组，保持既有顺序） */
    public array $initialValues = [];

    public string $tab = 'library';

    public string $search = '';

    public string $filterType = '';

    /** @var array<int, int> 选中的素材 id（顺序即保存顺序） */
    public array $selection = [];

    /** @var array<int, mixed> */
    public array $uploads = [];

    public function mount(): void
    {
        $modelClass = Utils::getMediaModel();

        // 初始选中：按既有值顺序解析 id
        $this->selection = collect($this->initialValues)
            ->map(fn ($value) => $value['path'] ?? null)
            ->filter()
            ->map(fn (string $path) => $modelClass::findByPath($path)?->id)
            ->filter()
            ->values()
            ->all();
    }

    public function toggle(int $id): void
    {
        if ($this->multiple) {
            $this->selection = in_array($id, $this->selection, true)
                ? array_values(array_diff($this->selection, [$id]))
                : [...$this->selection, $id];
        } else {
            $this->selection = ($this->selection === [$id]) ? [] : [$id];
        }
    }

    /**
     * 约束匹配：acceptedMimes 为空 = 不限；image/* 前缀匹配；其余精确匹配
     */
    public function allowsMime(string $mime): bool
    {
        if ($this->acceptedMimes === []) {
            return true;
        }

        foreach ($this->acceptedMimes as $pattern) {
            if (str_ends_with($pattern, '/*')) {
                if (str_starts_with($mime, substr($pattern, 0, -1))) {
                    return true;
                }
            } elseif ($mime === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * 上传即入库并自动选中
     */
    public function updatedUploads(): void
    {
        $manager = app(MediaManager::class);
        $count = 0;

        foreach ($this->uploads as $file) {
            try {
                $media = $manager->upload($file, $this->directory);
                $this->selection = $this->multiple ? [...$this->selection, $media->id] : [$media->id];
                $count++;
            } catch (\Throwable $e) {
                Notification::make()
                    ->title(__('sn-media::media.notifications.upload_failed', ['reason' => $e->getMessage()]))
                    ->danger()
                    ->send();
            }
        }

        $this->uploads = [];
        $this->resetPage();

        if ($count > 0) {
            Notification::make()->title(__('sn-media::media.notifications.uploaded', ['count' => $count]))->success()->send();
        }
    }

    /**
     * 确认：按 selection 顺序生成值对象数组 + 预览 URL 映射，回填字段
     */
    public function confirm(): void
    {
        $modelClass = Utils::getMediaModel();
        $medias = $modelClass::query()
            ->whereIn('id', $this->selection)
            ->get()
            ->keyBy('id');

        if ($medias->isEmpty()) {
            $this->dispatch('sn-media-picker-closed', statePath: $this->statePath);

            return;
        }

        $values = [];
        $urls = [];
        foreach ($this->selection as $id) {
            $media = $medias->get($id);
            if (! $media) {
                continue;
            }
            $values[] = $media->toValue();
            $urls[$media->path] = $media->url(200);
        }

        $this->dispatch('sn-media-selected', statePath: $this->statePath, multiple: $this->multiple, values: $values, urls: $urls);
    }

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

        if ($this->search !== '') {
            $query->search($this->search);
        }

        // 约束过滤放 SQL 侧有困难（前缀模式），取回后过滤 + 简单分页
        return $query;
    }

    #[Computed]
    public function medias()
    {
        $paginator = $this->mediaQuery()->orderByDesc('id')->paginate(18);

        $filtered = $paginator->setCollection(
            $paginator->getCollection()->filter(fn ($media) => $this->allowsMime((string) $media->mime))->values()
        );

        return $filtered;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('sn-media::livewire.components.picker');
    }
}
