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
 * 素材选择弹窗（内嵌组件，与管理页同构：工具栏搜索/上传 + 左侧目录树 + 类型 chips + 组合筛选）。
 * 约束一律经 props 注入；字段不配目录（选素材全库选，上传归当前浏览目录）。
 *
 * 选中态是**前端状态**：勾选/取消/确认都在 Alpine 本地数组里完成，不往返后端
 * （每点一次都发请求会让弹层出现 loading 抖动，本地站点尤其明显）。
 * 服务端只做两件事：把 initialValues 解析成初始选中 id，以及把这些 id 换成
 * 值对象 + 预览地址（selectionEntries）一次性下发。
 */
class Picker extends Component
{
    use InteractsWithMedia;
    use WithFileUploads;

    #[Locked]
    public bool $multiple = false;

    /** @var array<int, string> mime 模式（image/* 等前缀或精确 mime），空 = 不限 */
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

    /** 初始选中（值对象数组，保持既有顺序） */
    #[Locked]
    public array $initialValues = [];

    /** 多值上限（0 = 不限）：前端按剩余张数限制勾选 */
    #[Locked]
    public int $maxCount = 0;

    /**
     * @var array<int, int> 选中的素材 id（顺序即保存顺序）
     *
     * 只作为「初始选中」的种子：勾选/取消是纯前端状态（Alpine 本地数组），
     * 每次点击都往返后端会让弹层出现 loading 抖动、本地站点明显卡顿。
     * 服务端只负责把 id 换成值对象 + 预览地址，一次性下发给前端。
     */
    public array $selection = [];

    // perPage / sortBy / viewMode 由 trait 声明；弹窗在 mount() 里覆盖默认值

    public function mount(int $maxCount = 0, ?string $viewMode = null, ?string $sortBy = null): void
    {
        $this->maxCount = max(0, $maxCount);

        // 弹窗的分页默认比管理页更密（一屏尽量多给候选）
        $this->perPage = 24;

        if ($viewMode !== null) {
            $this->viewMode = $viewMode === 'list' ? 'list' : 'grid';
        }

        if ($sortBy !== null && array_key_exists($sortBy, $this->sortOptions())) {
            $this->sortBy = $sortBy;
        }

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

    /**
     * MediaDirectories 树组件的选择事件（叶子/非叶子统一处理：切换目录筛选）
     */
    #[On('sn-filament-nestedset-leaf-click')]
    #[On('sn-filament-nestedset-node-click')]
    public function onTreeClick(?int $recordId): void
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

    /**
     * 初始选中项的值对象与预览地址（id => {value, url}），一次性下发前端，
     * 之后勾选/翻页都不需要再问后端要这批数据。
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
     * 与管理页同一条查询链，差别只有字段级 mime 约束（这里才有：字段只允许图片等）。
     *
     * 约束值取 resolveAcceptedMimes()，与上传校验共用同一个来源：
     * 若使用方覆写了该 hook（例如按记录动态算），列表与上传不会各走一套。
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
     * 上传成功后自动选中（秒传复用的也选中）。
     *
     * 选中态在前端，所以这里不下发 $selection，而是把新素材的值对象与预览地址
     * 通过事件交给 Alpine 自己加进本地选中数组（否则前端拿不到 id → value 的映射）。
     *
     * 事件是 window 级的：页面上每个 picker 都会收到，所以 payload 必须带
     * statePath + fieldId，前端按这两项双重过滤后才并入——否则在 A 字段的弹层里
     * 上传的图会悄悄进 B 字段的选中态（保存时多出幽灵图片）。
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
     * 弹窗的 mime 约束来自表单字段（MediaPicker::acceptedMimes()），
     * 空数组归一成 null，让 trait 侧只有「有约束 / 没约束」两种状态。
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
