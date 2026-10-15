<?php

namespace Wsmallnews\Media\Livewire\Components;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Wsmallnews\Media\Livewire\Concerns\InteractsWithMediaLibrary;
use Wsmallnews\Media\Support\Utils;

/**
 * 素材选择弹窗（内嵌组件，与管理页同构：工具栏搜索/上传 + 左侧目录树 + 类型 chips + 组合筛选）。
 * 约束一律经 props 注入；字段不配目录（选素材全库选，上传归当前浏览目录）。
 * 确认后 dispatch sn-media-selected（statePath + values + urls），由 MediaPicker 字段 Alpine 回填。
 */
class Picker extends Component
{
    use InteractsWithMediaLibrary;
    use WithFileUploads;

    /**
     * MediaDirectories 树组件的选择事件（叶子/非叶子统一处理：切换目录筛选）
     */
    #[On('sn-filament-nestedset-leaf-click')]
    #[On('sn-filament-nestedset-node-click')]
    public function onTreeClick(int $recordId): void
    {
        $this->setLibraryDir($recordId);
    }

    /**
     * 目录树 CRUD 后刷新本页（空监听触发重渲染，让面包屑/列表跟随树变更）
     */
    #[On('sn-filament-nestedset-updated')]
    public function refreshFromTree(): void
    {
        //
    }

    public bool $multiple = false;

    /** @var array<int, string> mime 模式（image/* 等前缀或精确 mime），空 = 不限 */
    public array $acceptedMimes = [];

    public float $ratio = 1;

    public string $statePath = '';

    /** 初始选中（值对象数组，保持既有顺序） */
    public array $initialValues = [];

    /** @var array<int, int> 选中的素材 id（顺序即保存顺序） */
    public array $selection = [];

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

    /**
     * 上传成功后自动选中（秒传复用的也选中）
     */
    protected function afterMediaUploaded($media): void
    {
        $this->selection = $this->multiple
            ? [...$this->selection, $media->id]
            : [$media->id];
    }

    /**
     * 字段约束转 SQL（前缀模式 → like，精确 → 等值），分页计数随之正确
     */
    protected function mediaQueryWithAccept()
    {
        $query = $this->mediaQuery();

        if ($this->acceptedMimes !== []) {
            $query->where(function ($q) {
                foreach ($this->acceptedMimes as $pattern) {
                    if (str_ends_with($pattern, '/*')) {
                        $q->orWhere('mime', 'like', substr($pattern, 0, -1) . '%');
                    } else {
                        $q->orWhere('mime', $pattern);
                    }
                }
            });
        }

        return $query;
    }

    public function medias()
    {
        return $this->applySort($this->mediaQueryWithAccept())->paginate(18);
    }

    public function render(): View
    {
        return view('sn-media::livewire.components.picker');
    }
}
