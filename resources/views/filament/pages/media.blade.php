@php
    use Filament\Support\Icons\Heroicon;
    use Wsmallnews\Media\Enums\MediaType;
@endphp

<x-filament-panels::page>
    <div
        {{-- Alpine 资产组件需显式 x-load 触发脚本加载，否则 x-data 函数未定义会连坐整个子树 --}}
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('sn-media-manager', 'wsmallnews/media') }}"
        x-data="snMediaManager({{ \Illuminate\Support\Js::from([
            'uploadingLabel' => __('sn-media::media.notifications.uploading'),
            'rejectedLabel' => __('sn-media::media.notifications.upload_rejected_default'),
            'tooLargeLabel' => __('sn-media::media.notifications.upload_too_large', ['name' => '__NAME__', 'max' => '__MAX__']),
            // 与选择弹窗同源：取 PHP / Livewire / 包配置的最小值（见 UploadsMedia::maxUploadKb）
            'maxUploadKb' => $this->maxUploadKb(),
        ]) }})"
        @sn-media-open-upload.window="$refs.fileInput.click()"
        class="sn-media-page">

        {{-- 卡片固定为视口内高度（扣除页头/边距），内容区竖向内滚，页面本身不滚动 --}}
        <div class="@container flex h-[calc(100vh-230px)] min-h-[480px] flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <x-sn-media::filament.components.media-library-toolbar
                :breadcrumb="$this->directoryBreadcrumb()"
                :library-dir="$libraryDir"
                :library-type="$libraryType"
                :sort-options="$this->sortOptions()"
                :view-mode="$viewMode"
            />

            {{-- 批量操作条（Alpine 本地选中态） --}}
            <div x-show="selected.length" x-cloak
                class="flex flex-wrap items-center gap-2 border-b border-primary-100 bg-primary-50 px-3 py-2 text-sm dark:border-primary-500/20 dark:bg-primary-500/10">
                <span class="font-medium text-primary-700 dark:text-primary-300">
                    {{ __('sn-media::media.list.selected_prefix') }} <span x-text="selected.length"></span>
                </span>
                <span class="mx-1 h-4 w-px bg-primary-200 dark:bg-primary-500/30"></span>
                <x-filament::button type="button" size="sm" color="primary" outlined x-on:click="selectAllPage()" x-text="allPageSelected() ? '{{ __('sn-media::media.list.deselect_all') }}' : '{{ __('sn-media::media.list.select_all_page') }}'" />
                <x-filament::button type="button" size="sm" color="primary" outlined :icon="Heroicon::OutlinedArrowPath" x-on:click="$wire.mountAction('snMediaMove', { ids: selected })">
                    {{ __('sn-media::media.list.move_to_directory') }}
                </x-filament::button>
                <x-filament::button type="button" size="sm" color="danger" outlined :icon="Heroicon::OutlinedTrash" x-on:click="$wire.mountAction('snMediaDelete', { ids: selected })">
                    {{ __('sn-media::media.list.delete_batch') }}
                </x-filament::button>
                <x-filament::button type="button" size="sm" color="gray" outlined class="ml-auto" x-on:click="selected = []">
                    {{ __('sn-media::media.list.clear_selection') }}
                </x-filament::button>
            </div>

            {{-- 主体：吃掉工具栏/状态栏以外的全部高度，树和内容各自内滚。
                 断点走容器查询（@3xl = 48rem）：Filament 侧边栏折叠会改变内容区宽度而视口不变，
                 视口断点（md:）在那时不响应。目录树外壳已同步改为 @3xl，两者同源不会错配。 --}}
            <div class="flex min-h-0 min-w-0 flex-1 flex-col @3xl:flex-row">
                {{-- 左侧：目录树 + 「全部 / 未归类」，与选择弹窗共用 media-library-sidebar
                    （aside 外壳、断点、滚动都在组件里，这里只喂数据） --}}
                <x-sn-media::filament.components.media-library-sidebar
                    :active-dir="$libraryDir"
                    :all-count="$this->typeCounts['all'] ?? 0"
                    :uncategorized-count="$this->uncategorizedCount"
                />

                {{-- 右侧：内容区（chips 固定，列表/瓦片区自身滚动——横向滚动条钉在可视区底部） --}}
                <div class="relative flex min-h-0 min-w-0 flex-1 flex-col p-3"
                    data-page-ids='@json($this->medias->getCollection()->pluck('id')->values())'
                    @dragenter.prevent="dragOver = true"
                    @dragover.prevent
                    @dragleave.prevent="if (event.target === this) dragOver = false"
                    @drop.prevent="onDrop($event)">

                    <div class="mb-3 flex flex-wrap items-center gap-1.5">
                        <x-filament::button type="button" size="sm" :color="$libraryType === '' ? 'primary' : 'gray'" :outlined="$libraryType !== ''" wire:click="filterByType('')" class="sn-media-chip">
                            {{ __('sn-media::media.tree.all') }} <span class="ml-0.5 opacity-70">{{ $this->typeCounts['all'] ?? 0 }}</span>
                        </x-filament::button>
                        @foreach (MediaType::cases() as $type)
                            @continue(! $type->isFilterable())
                            <x-filament::button type="button" size="sm" :color="$libraryType === $type->value ? 'primary' : 'gray'" :outlined="$libraryType !== $type->value" wire:click="filterByType('{{ $type->value }}')" class="sn-media-chip">
                                <x-filament::icon :icon="$type->getIcon()" class="size-3.5" />
                                {{ $type->getLabel() }} <span class="opacity-70">{{ $this->typeCounts[$type->value] ?? 0 }}</span>
                            </x-filament::button>
                        @endforeach
                    </div>

                    {{-- 拖拽上传覆盖层 --}}
                    <div x-show="dragOver" x-cloak
                        class="pointer-events-none absolute inset-2 z-10 grid place-items-center rounded-xl border-2 border-dashed border-primary-400 bg-primary-50/90 dark:bg-primary-500/10">
                        <div class="text-center">
                            <x-filament::icon :icon="Heroicon::OutlinedArrowUpTray" class="mx-auto size-10 text-primary-500" />
                            <p class="mt-2 text-sm font-medium text-primary-700 dark:text-primary-300">{{ __('sn-media::media.picker.drop_here') }}</p>
                        </div>
                    </div>

                    {{-- 上传进度（与选择弹窗、FilePicker 共用同一个组件） --}}
                    <x-sn-media::filament.components.upload-progress />

                    @if (count($this->medias) === 0)
                        {{-- 空态分两种：库里没东西 vs 筛选没结果。混用「暂无素材」会让用户以为库是空的。 --}}
                        <div class="grid min-h-0 flex-1 place-items-center">
                            @if ($this->hasActiveLibraryFilter())
                                <x-filament::empty-state :contained="false" :icon="Heroicon::OutlinedMagnifyingGlass"
                                    :heading="__('sn-media::media.list.empty_filtered_heading')"
                                    :description="__('sn-media::media.list.empty_filtered')" />
                            @else
                                <x-filament::empty-state :contained="false" :icon="Heroicon::OutlinedPhoto"
                                    :heading="__('sn-media::media.list.empty_heading')"
                                    :description="__('sn-media::media.list.empty')" />
                            @endif
                        </div>
                    @elseif ($viewMode === 'grid')
                        {{-- 大图标视图（自身竖向滚动；p-1 给选中 ring 留空间防止被 overflow 裁掉） --}}
                        <div class="min-h-0 flex-1 overflow-y-auto p-1">
                            <div class="grid gap-2 [grid-template-columns:repeat(auto-fill,minmax(112px,1fr))]">
                            @foreach ($this->medias as $media)
                                {{-- 叠层用 grid 而不是 absolute：absolute 的小容器会成为 dropdown 面板的
                                     containing block，面板的 bottom-end 只能相对那 40px 盒子算，右对齐失效。
                                     grid item 允许 z-index 但不算 positioned，面板会相对本瓦片定位。 --}}
                                <div class="group relative grid" wire:key="sn-media-grid-{{ $media->id }}">
                                    <x-filament::button
                                        type="button"
                                        color="gray"
                                        outlined
                                        aria-label="{{ $media->name }}"
                                        x-on:click="toggle({{ $media->id }})"
                                        x-on:dblclick="$wire.openPreview({{ $media->id }})"
                                        x-bind:aria-pressed="isSelected({{ $media->id }})"
                                        x-bind:class="isSelected({{ $media->id }}) ? 'sn-ring-selected' : ''"
                                        class="sn-media-tile col-start-1 row-start-1 transition"
                                    >
                                    <span class="relative block w-full">
                                        <span class="grid aspect-square w-full place-items-center overflow-hidden rounded-md bg-gray-50 dark:bg-gray-800">
                                            @if ($media->isImage())
                                                <img src="{{ $media->url(200) }}" alt="" class="size-full object-cover" loading="lazy" draggable="false">
                                            @else
                                                <img src="{{ $media->mediaType->getTileUrl() }}" alt="" class="size-20 rounded-xl">
                                            @endif
                                        </span>
                                    </span>
                                    <span class="line-clamp-2 w-full break-all text-center text-xs leading-4 text-gray-700 dark:text-gray-300">{{ $media->name }}</span>
                                    </x-filament::button>
                                    {{-- 菜单与选择按钮同级，避免在按钮内嵌套交互元素。
                                         外层是 grid item（不定位）只负责右上角对齐与点击穿透，内层保留原视觉。 --}}
                                    <div
                                        class="pointer-events-none z-10 col-start-1 row-start-1 flex items-start justify-end p-1"
                                        @click.stop
                                    >
                                        <div class="pointer-events-auto rounded-lg bg-white/95 shadow-sm ring-1 ring-gray-950/10 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100 dark:bg-gray-800/95 dark:ring-white/15">
                                            {{ $this->mediaMenuActions($media) }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            </div>
                        </div>
                    @else
                        {{-- 详细信息视图（含目录列）：双向滚动容器吃满剩余高度，横向滚动条钉在可视区底部 --}}
                        <div class="min-h-0 flex-1 overflow-auto text-sm">
                            <div class="min-w-[720px] px-1">
                                {{-- 表头 px-3 = 行容器 px-1 + 行外壳 px-2，两边对齐才能让列头和行内容落在同一条竖线上 --}}
                                <div class="flex items-center gap-3 border-b border-gray-200 px-3 pb-2 text-xs font-medium text-gray-400 dark:border-gray-700">
                                    <span class="w-10 shrink-0"></span>
                                    <span class="min-w-[180px] max-w-[420px] flex-1">{{ __('sn-media::media.list.name') }}</span>
                                    <span class="w-24 shrink-0">{{ __('sn-media::media.tree.directories') }}</span>
                                    <span class="w-24 shrink-0">{{ __('sn-media::media.list.created_at') }}</span>
                                    <span class="w-24 shrink-0">{{ __('sn-media::media.list.type') }}</span>
                                    <span class="w-20 shrink-0 text-right">{{ __('sn-media::media.list.size') }}</span>
                                    <span class="w-12 shrink-0 text-right">{{ __('sn-media::media.list.actions') }}</span>
                                </div>
                                <div class="mt-1 space-y-0.5">
                                    @foreach ($this->medias as $media)
                                        {{-- 选中环挂在外壳上：菜单是 button 的兄弟节点，放不进 button（嵌套交互元素非法），
                                             只有把 hover / ring 提到外壳才能让选中框覆盖整行、把操作列包进来 --}}
                                        <div
                                            class="sn-media-row-shell transition"
                                            wire:key="sn-media-row-{{ $media->id }}"
                                            x-bind:class="isSelected({{ $media->id }}) ? 'sn-ring-selected-inset' : ''"
                                        >
                                            <x-filament::button
                                                type="button"
                                                color="gray"
                                                aria-label="{{ $media->name }}"
                                                x-on:click="toggle({{ $media->id }})"
                                                x-on:dblclick="$wire.openPreview({{ $media->id }})"
                                                x-bind:aria-pressed="isSelected({{ $media->id }})"
                                                class="sn-media-row-hit"
                                            >
                                            <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-md bg-gray-50 dark:bg-gray-800">
                                                @if ($media->isImage())
                                                    <img src="{{ $media->url(80) }}" alt="" class="size-full object-cover" loading="lazy" draggable="false">
                                                @else
                                                    <img src="{{ $media->mediaType->getTileUrl() }}" alt="" class="size-8 rounded-md">
                                                @endif
                                            </span>
                                            <span class="min-w-[180px] max-w-[420px] flex-1 truncate font-medium text-gray-800 dark:text-gray-200">{{ $media->name }}</span>
                                            <span class="w-24 shrink-0 truncate text-xs text-gray-500">{{ $media->directory?->name ?? __('sn-media::media.tree.uncategorized') }}</span>
                                            <span class="w-24 shrink-0 text-xs text-gray-500">{{ $media->created_at->format('m-d') }}</span>
                                            <span class="w-24 shrink-0 text-xs text-gray-500">{{ $media->mediaType->getLabel() }}</span>
                                            <span class="w-20 shrink-0 text-right text-xs text-gray-500">{{ $media->size_for_humans }}</span>
                                            </x-filament::button>
                                            {{-- 行内操作恒显（对齐 Filament 表格行操作行为，不依赖 hover，避免遮罩/触屏环境不可达） --}}
                                            <div class="flex w-12 shrink-0 justify-end" @click.stop>
                                                {{ $this->mediaMenuActions($media) }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 状态栏：分页（容器查询组件，需占满宽度才会呈现常规形态：左概览/每页条数 + 右页码组） --}}
            <div class="border-t border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/50">
                <x-filament::pagination
                    class="w-full"
                    :paginator="$this->medias"
                    :page-options="\Wsmallnews\Media\Filament\Pages\MediaPage::PER_PAGE_OPTIONS"
                    current-page-option-property="perPage"
                />
            </div>
        </div>

        {{-- 预览弹层交给 Filament，保留它的焦点管理、Esc / 点击遮罩关闭和主题样式。 --}}
        <x-filament::modal id="sn-media-preview" width="2xl">
            @if ($this->previewMedia)
                @php($previewMedia = $this->previewMedia)
                <div class="space-y-4">
                    <div class="grid min-h-64 place-items-center rounded-lg bg-gray-900 p-4">
                        @if ($previewMedia->isImage())
                            <img src="{{ $previewMedia->url() }}" alt="{{ $previewMedia->alt ?: $previewMedia->name }}" class="max-h-80 rounded-lg object-contain">
                        @else
                            <img src="{{ $previewMedia->mediaType->getTileUrl() }}" alt="" class="size-24 rounded-2xl">
                        @endif
                    </div>

                    <dl class="space-y-1.5 text-sm">
                        <div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-400">{{ __('sn-media::media.preview.path') }}</dt><dd class="min-w-0 break-all font-mono text-xs leading-5 text-gray-600 dark:text-gray-400">{{ $previewMedia->path }}</dd></div>
                        <div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-400">{{ __('sn-media::media.tree.directories') }}</dt><dd>{{ $previewMedia->directory?->name ?? __('sn-media::media.tree.uncategorized') }}</dd></div>
                        <div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-400">{{ __('sn-media::media.preview.size_label') }}</dt><dd>{{ $previewMedia->width ? $previewMedia->width . ' × ' . $previewMedia->height : __('sn-media::media.preview.size_empty') }}</dd></div>
                        <div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-400">{{ __('sn-media::media.preview.file_size') }}</dt><dd>{{ $previewMedia->size_for_humans }}</dd></div>
                        <div class="flex gap-2"><dt class="w-20 shrink-0 text-gray-400">{{ __('sn-media::media.preview.mime') }}</dt><dd class="font-mono text-xs">{{ $previewMedia->mime }}</dd></div>
                    </dl>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block space-y-1">
                            <span class="text-xs text-gray-500">{{ __('sn-media::media.preview.rename_label') }}</span>
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="metaName" />
                            </x-filament::input.wrapper>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs text-gray-500">{{ __('sn-media::media.preview.alt_label') }}</span>
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="metaAlt" />
                            </x-filament::input.wrapper>
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-filament::button tag="a" :href="\Wsmallnews\Media\Support\MediaValue::make($previewMedia)->downloadUrl()" target="_blank" size="sm" color="gray" outlined :icon="Heroicon::OutlinedArrowDownTray">
                            {{ __('sn-media::media.list.download') }}
                        </x-filament::button>
                        <x-filament::button type="button" size="sm" color="gray" outlined wire:click="mountAction('snMediaCopyPath', { id: {{ $previewMedia->id }} })">
                            {{ __('sn-media::media.list.copy_path') }}
                        </x-filament::button>
                        <x-filament::button type="button" size="sm" color="gray" outlined wire:click="mountAction('snMediaMove', { id: {{ $previewMedia->id }} })">
                            {{ __('sn-media::media.list.move_to_directory') }}
                        </x-filament::button>
                        <x-filament::button type="button" size="sm" color="danger" outlined wire:click="mountAction('snMediaDelete', { id: {{ $previewMedia->id }} })">
                            {{ __('sn-media::media.list.delete') }}
                        </x-filament::button>
                        <x-filament::button type="button" size="sm" color="gray" outlined wire:click="clearPreview">
                            {{ __('sn-media::media.preview.close') }}
                        </x-filament::button>
                        <x-filament::button type="button" size="sm" wire:click="saveMeta">
                            {{ __('sn-media::media.preview.save_meta') }}
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </x-filament::modal>

        {{-- 文件输入（页头上传 + 拖拽共用；JS 预检大小后手动 uploadMultiple） --}}
        <x-filament::input type="file" multiple x-ref="fileInput" class="hidden" x-on:change="onFilePick($event)" />
    </div>

    {{-- 目录/素材操作 Actions（Filament 原生 modal 渲染容器） --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
