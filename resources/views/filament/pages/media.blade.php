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
            'maxUploadKb' => min(
                (int) (ini_get('upload_max_filesize') ? floor((float) ini_get('upload_max_filesize') * 1024) : 0) ?: 999999,
                (int) config('livewire.temporary_file_upload.rules.1') ?: 999999,
                (int) config('sn-media.max_size') ?: 999999,
            ),
        ]) }})"
        {{-- 页头「上传」动作（不经服务端）转发到本组件的文件输入 --}}
        @sn-media-open-upload.window="$refs.fileInput.click()"
        class="sn-media-page">

        {{-- 卡片固定为视口内高度（扣除页头/边距），内容区竖向内滚，页面本身不滚动 --}}
        <div class="flex h-[calc(100vh-230px)] min-h-[480px] flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

            {{-- 工具栏：面包屑（目录›类型）/ 排序 / 视图 / 搜索 --}}
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 p-3 dark:border-gray-700">
                <nav class="flex min-w-0 flex-wrap items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                    {{-- Breadcrumb: full path from root to current directory, each ancestor clickable to switch --}}
                    @if ($libraryDir === null)
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('sn-media::media.tree.all') }}</span>
                    @else
                        <button wire:click="filterByDir(null)" class="hover:text-primary-600">{{ __('sn-media::media.tree.all') }}</button>
                        <x-filament::icon :icon="Heroicon::OutlinedChevronRight" class="size-3.5" />
                        @if ($libraryDir === 0)
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('sn-media::media.tree.uncategorized') }}</span>
                        @else
                            @foreach ($this->directoryBreadcrumb() as $crumb)
                                @if (! $loop->first)
                                    <x-filament::icon :icon="Heroicon::OutlinedChevronRight" class="size-3.5" />
                                @endif
                                @if ($crumb['id'] === $libraryDir)
                                    <span class="max-w-40 truncate font-medium text-gray-900 dark:text-gray-100">{{ $crumb['name'] }}</span>
                                @else
                                    <button wire:click="filterByDir({{ $crumb['id'] }})" class="max-w-32 truncate hover:text-primary-600">{{ $crumb['name'] }}</button>
                                @endif
                            @endforeach
                        @endif
                    @endif
                    @if ($libraryType !== '')
                        <x-filament::icon :icon="Heroicon::OutlinedChevronRight" class="size-3.5" />
                        <button wire:click="filterByType('')" class="hover:text-primary-600">{{ MediaType::tryFrom($libraryType)?->getLabel() }}</button>
                    @endif
                </nav>

                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <select wire:model.live="sortBy" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="created_desc">{{ __('sn-media::media.toolbar.sort.created_desc') }}</option>
                        <option value="created_asc">{{ __('sn-media::media.toolbar.sort.created_asc') }}</option>
                        <option value="name_asc">{{ __('sn-media::media.toolbar.sort.name_asc') }}</option>
                        <option value="name_desc">{{ __('sn-media::media.toolbar.sort.name_desc') }}</option>
                        <option value="size_desc">{{ __('sn-media::media.toolbar.sort.size_desc') }}</option>
                        <option value="size_asc">{{ __('sn-media::media.toolbar.sort.size_asc') }}</option>
                        <option value="type">{{ __('sn-media::media.toolbar.sort.type') }}</option>
                    </select>

                    {{-- 视图切换 --}}
                    <div class="flex items-center gap-0.5 rounded-lg border border-gray-300 bg-white p-0.5 dark:border-gray-600 dark:bg-gray-800">
                        <button wire:click="setViewMode('grid')" title="{{ __('sn-media::media.toolbar.view_grid') }}"
                            class="grid size-7 place-items-center rounded-md transition {{ $viewMode === 'grid' ? 'bg-primary-600 text-white' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                            <x-filament::icon :icon="Heroicon::OutlinedSquares2x2" class="size-4" />
                        </button>
                        <button wire:click="setViewMode('list')" title="{{ __('sn-media::media.toolbar.view_list') }}"
                            class="grid size-7 place-items-center rounded-md transition {{ $viewMode === 'list' ? 'bg-primary-600 text-white' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                            <x-filament::icon :icon="Heroicon::OutlinedBars3" class="size-4" />
                        </button>
                    </div>

                    <div class="relative">
                        <x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <input type="text" wire:model.live.debounce.400ms="librarySearch" placeholder="{{ __('sn-media::media.toolbar.search_placeholder') }}"
                            class="w-52 rounded-lg border border-gray-300 py-1.5 pl-8 pr-3 text-sm outline-none focus:border-primary-500 dark:border-gray-600 dark:bg-gray-800">
                    </div>
                </div>
            </div>

            {{-- 批量操作条（Alpine 本地选中态） --}}
            <div x-show="selected.length" x-cloak
                class="flex flex-wrap items-center gap-2 border-b border-primary-100 bg-primary-50 px-3 py-2 text-sm dark:border-primary-500/20 dark:bg-primary-500/10">
                <span class="font-medium text-primary-700 dark:text-primary-300">
                    {{ __('sn-media::media.list.selected_prefix') }} <span x-text="selected.length"></span>
                </span>
                <span class="mx-1 h-4 w-px bg-primary-200 dark:bg-primary-500/30"></span>
                <button @click="selectAllPage()" class="rounded-md px-2 py-1 text-primary-700 hover:bg-white dark:text-primary-300 dark:hover:bg-gray-800"
                    x-text="allPageSelected() ? '{{ __('sn-media::media.list.deselect_all') }}' : '{{ __('sn-media::media.list.select_all_page') }}'"></button>
                <button @click="$wire.mountAction('snMediaMove', { ids: selected })" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-primary-700 hover:bg-white dark:text-primary-300 dark:hover:bg-gray-800">
                    <x-filament::icon :icon="Heroicon::OutlinedArrowPath" class="size-4" />
                    {{ __('sn-media::media.list.move_to_directory') }}
                </button>
                <button @click="$wire.mountAction('snMediaDelete', { ids: selected })" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-danger-600 hover:bg-white dark:hover:bg-gray-800">
                    <x-filament::icon :icon="Heroicon::OutlinedTrash" class="size-4" />
                    {{ __('sn-media::media.list.delete_batch') }}
                </button>
                <button @click="selected = []" class="ml-auto rounded-md px-2 py-1 text-gray-500 hover:bg-white dark:hover:bg-gray-800">{{ __('sn-media::media.list.clear_selection') }}</button>
            </div>

            {{-- 主体：吃掉工具栏/状态栏以外的全部高度，树和内容各自内滚 --}}
            <div class="flex min-h-0 min-w-0 flex-1">
                {{-- 左侧：目录树容器（哨兵行 + MediaDirectories 树组件，选中态经 activeId 回传高亮） --}}
                <aside class="hidden w-56 shrink-0 overflow-y-auto border-r border-gray-200 p-2 text-sm md:block dark:border-gray-700">
                    <p class="mb-1 px-1 text-xs font-semibold tracking-wide text-gray-400">{{ __('sn-media::media.tree.directories') }}</p>

                    {{-- 哨兵行：全部 / 未归类（宿主筛选状态，不走事件） --}}
                    <button wire:click="filterByDir(null)"
                        class="flex w-full items-center justify-between rounded-md px-2 py-1.5 {{ $libraryDir === null ? 'bg-primary-50 font-medium text-primary-500 dark:bg-primary-500/10 dark:text-primary-600' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' }}">
                        <span>{{ __('sn-media::media.tree.all') }}</span>
                        <span class="text-xs text-gray-400">{{ $this->typeCounts['all'] ?? 0 }}</span>
                    </button>
                    <button wire:click="filterByDir(0)"
                        class="flex w-full items-center justify-between rounded-md px-2 py-1.5 {{ $libraryDir === 0 ? 'bg-primary-50 font-medium text-primary-500 dark:bg-primary-500/10 dark:text-primary-600' : 'text-gray-400 italic hover:bg-gray-100 dark:text-gray-500 dark:hover:bg-white/5' }}">
                        <span>{{ __('sn-media::media.tree.uncategorized') }}</span>
                        <span class="text-xs text-gray-400">{{ $this->uncategorizedCount ?? 0 }}</span>
                    </button>

                    <livewire:sn-media::components.media-directories :active-id="$libraryDir" />

                    <div class="mt-4 rounded-lg bg-gray-50 p-2.5 text-[11px] leading-5 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        {{ __('sn-media::media.tree.tree_hint') }}
                    </div>
                </aside>

                {{-- 右侧：内容区（chips 固定，列表/瓦片区自身滚动——横向滚动条钉在可视区底部） --}}
                <div class="relative flex min-h-0 min-w-0 flex-1 flex-col p-3"
                    data-page-ids='@json($this->medias->getCollection()->pluck('id')->values())'
                    @dragenter.prevent="dragOver = true"
                    @dragover.prevent
                    @dragleave.prevent="if (event.target === this) dragOver = false"
                    @drop.prevent="onDrop($event)">

                    {{-- 类型 chips + 窄屏目录下拉（flex-wrap 换行） --}}
                    <div class="mb-3 flex flex-wrap items-center gap-1.5">
                        <select wire:change="filterByDir($event.target.value === 'all' ? null : parseInt($event.target.value))"
                            class="rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs md:hidden dark:border-gray-600 dark:bg-gray-800">
                            <option value="all" @selected($libraryDir === null)>{{ __('sn-media::media.tree.all') }}</option>
                            <option value="0" @selected($libraryDir === 0)>{{ __('sn-media::media.tree.uncategorized') }}</option>
                            @foreach ($this->libraryDirectories as $treeDir)
                                <option value="{{ $treeDir['id'] }}" @selected($libraryDir === $treeDir['id'])>
                                    {{ str_repeat('　', $treeDir['depth']) }}{{ $treeDir['name'] }}
                                </option>
                            @endforeach
                        </select>

                        <button wire:click="filterByType('')"
                            class="rounded-full px-2.5 py-1 text-xs {{ $libraryType === '' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                            {{ __('sn-media::media.tree.all') }} <span class="ml-0.5 opacity-70">{{ $this->typeCounts['all'] ?? 0 }}</span>
                        </button>
                        @foreach (MediaType::cases() as $type)
                            @continue(! $type->isFilterable())
                            <button wire:click="filterByType('{{ $type->value }}')"
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs {{ $libraryType === $type->value ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                <x-filament::icon :icon="$type->getIcon()" class="size-3.5" />
                                {{ $type->getLabel() }} <span class="opacity-70">{{ $this->typeCounts[$type->value] ?? 0 }}</span>
                            </button>
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

                    {{-- 上传进度条（livewire-upload-progress 事件驱动） --}}
                    <div x-show="uploading" x-cloak
                        class="absolute inset-x-3 top-3 z-20 rounded-lg border border-primary-200 bg-white/95 p-2.5 shadow-lg backdrop-blur dark:border-primary-500/30 dark:bg-gray-900/95">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-primary-700 dark:text-primary-300">
                                <span x-text="uploadLabel()"></span>
                            </span>
                            <span class="font-mono text-primary-600" x-text="progress + '%'"></span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-500/20">
                            <div class="h-full rounded-full bg-primary-500 transition-all duration-150" :style="'width:' + progress + '%'"></div>
                        </div>
                        <p class="mt-1 truncate text-[11px] text-gray-400" x-text="uploadFilename"></p>
                    </div>

                    @if (count($this->medias) === 0)
                        <div class="grid min-h-0 flex-1 place-items-center">
                            <x-sn-support::empty :contained="false" :icon="Heroicon::OutlinedPhoto"
                                :heading="__('sn-media::media.list.empty_heading')"
                                :description="__('sn-media::media.list.empty')" />
                        </div>
                    @elseif ($viewMode === 'grid')
                        {{-- 大图标视图（自身竖向滚动；p-1 给选中 ring 留空间防止被 overflow 裁掉） --}}
                        <div class="min-h-0 flex-1 overflow-y-auto p-1">
                            <div class="grid gap-2 [grid-template-columns:repeat(auto-fill,minmax(112px,1fr))]">
                            @foreach ($this->medias as $media)
                                <div class="group flex cursor-pointer flex-col items-center gap-1.5 rounded-lg p-2 transition"
                                    wire:key="sn-media-grid-{{ $media->id }}"
                                    :class="isSelected({{ $media->id }}) ? 'bg-primary-50 ring-2 ring-primary-500 dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                                    @click="toggle({{ $media->id }})"
                                    x-on:dblclick="$wire.openPreview({{ $media->id }})">
                                    <div class="relative w-full">
                                        <div class="grid aspect-square w-full place-items-center overflow-hidden rounded-md bg-gray-50 dark:bg-gray-800">
                                            @if ($media->isImage())
                                                <img src="{{ $media->url(200) }}" alt="{{ $media->alt ?: $media->name }}" class="size-full object-cover" loading="lazy" draggable="false">
                                            @else
                                                <img src="{{ $media->mediaType->getTileUrl() }}" alt="{{ $media->mediaType->getLabel() }}" class="size-20 rounded-xl">
                                            @endif
                                        </div>
                                        {{-- ⋯ 菜单（Filament ActionGroup，锚定瓦片右上角；白底/暗底 + 描边保证在图片上可见） --}}
                                        <div class="absolute right-1 top-1 rounded-lg bg-white/95 shadow-sm ring-1 ring-gray-950/10 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100 dark:bg-gray-800/95 dark:ring-white/15" @click.stop>
                                            {{ $this->mediaMenuActions($media) }}
                                        </div>
                                    </div>
                                    <p class="line-clamp-2 w-full break-all text-center text-xs leading-4 text-gray-700 dark:text-gray-300">{{ $media->name }}</p>
                                </div>
                            @endforeach
                            </div>
                        </div>
                    @else
                        {{-- 详细信息视图（含目录列）：双向滚动容器吃满剩余高度，横向滚动条钉在可视区底部 --}}
                        <div class="min-h-0 flex-1 overflow-auto text-sm">
                            <div class="min-w-[720px]">
                                <div class="flex items-center gap-3 border-b border-gray-200 px-2 pb-2 text-xs font-medium text-gray-400 dark:border-gray-700">
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
                                        <div class="group flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 transition"
                                            wire:key="sn-media-row-{{ $media->id }}"
                                            :class="isSelected({{ $media->id }}) ? 'bg-primary-50 ring-1 ring-primary-400 dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                                            @click="toggle({{ $media->id }})"
                                            x-on:dblclick="$wire.openPreview({{ $media->id }})">
                                            <div class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-md bg-gray-50 dark:bg-gray-800">
                                                @if ($media->isImage())
                                                    <img src="{{ $media->url(80) }}" alt="{{ $media->alt ?: $media->name }}" class="size-full object-cover" loading="lazy" draggable="false">
                                                @else
                                                    <img src="{{ $media->mediaType->getTileUrl() }}" alt="{{ $media->mediaType->getLabel() }}" class="size-8 rounded-md">
                                                @endif
                                            </div>
                                            <span class="min-w-[180px] max-w-[420px] flex-1 truncate font-medium text-gray-800 dark:text-gray-200">{{ $media->name }}</span>
                                            <span class="w-24 shrink-0 truncate text-xs text-gray-500">{{ $media->directory?->name ?? __('sn-media::media.tree.uncategorized') }}</span>
                                            <span class="w-24 shrink-0 text-xs text-gray-500">{{ $media->created_at->format('m-d') }}</span>
                                            <span class="w-24 shrink-0 text-xs text-gray-500">{{ $media->mediaType->getLabel() }}</span>
                                            <span class="w-20 shrink-0 text-right text-xs text-gray-500">{{ $this->getFileSize((int) $media->size) }}</span>
                                            {{-- 行内操作恒显（对齐 Filament 表格行操作行为，不依赖 hover，避免遮罩/触屏环境不可达） --}}
                                            <div class="ml-auto flex w-12 shrink-0 justify-end" @click.stop>
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
                    :page-options="[12, 24, 48, 96]"
                    current-page-option-property="perPage"
                />
            </div>
        </div>

        {{-- 预览弹层 --}}
        @if ($this->previewMedia)
            <div class="fixed inset-0 z-50 grid place-items-center bg-gray-950/60 p-4" wire:click.self="clearPreview">
                <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900">
                    <div class="grid min-h-64 place-items-center bg-gray-900 p-4">
                        @if ($this->previewMedia->isImage())
                            <img src="{{ $this->previewMedia->url() }}" alt="{{ $this->previewMedia->alt ?: $this->previewMedia->name }}" class="max-h-80 rounded-lg object-contain">
                        @else
                            <img src="{{ $this->previewMedia->mediaType->getTileUrl() }}" alt="" class="size-24 rounded-2xl">
                        @endif
                    </div>
                    <div class="space-y-1.5 p-4 text-sm">
                        <div class="flex gap-2"><span class="w-20 shrink-0 text-gray-400">{{ __('sn-media::media.preview.path') }}</span><span class="min-w-0 break-all font-mono text-xs leading-5 text-gray-600 dark:text-gray-400">{{ $this->previewMedia->path }}</span></div>
                        <div class="flex gap-2"><span class="w-20 shrink-0 text-gray-400">{{ __('sn-media::media.tree.directories') }}</span><span>{{ $this->previewMedia->directory?->name ?? __('sn-media::media.tree.uncategorized') }}</span></div>
                        <div class="flex gap-2"><span class="w-20 shrink-0 text-gray-400">{{ __('sn-media::media.preview.size_label') }}</span><span>{{ $this->previewMedia->width ? $this->previewMedia->width . ' × ' . $this->previewMedia->height : __('sn-media::media.preview.size_empty') }}</span></div>
                        <div class="flex gap-2"><span class="w-20 shrink-0 text-gray-400">{{ __('sn-media::media.preview.file_size') }}</span><span>{{ $this->getFileSize((int) $this->previewMedia->size) }}</span></div>
                        <div class="flex gap-2"><span class="w-20 shrink-0 text-gray-400">{{ __('sn-media::media.preview.mime') }}</span><span class="font-mono text-xs">{{ $this->previewMedia->mime }}</span></div>

                        <div class="grid gap-2 pt-2 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-xs text-gray-500">{{ __('sn-media::media.preview.rename_label') }}</span>
                                <input type="text" wire:model="metaName" class="w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs text-gray-500">{{ __('sn-media::media.preview.alt_label') }}</span>
                                <input type="text" wire:model="metaAlt" class="w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                            </label>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2 pt-3">
                            <a href="{{ route('sn-media.download', ['media' => $this->previewMedia]) }}"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800">
                                <x-filament::icon :icon="Heroicon::OutlinedArrowDownTray" class="size-3.5" />
                                {{ __('sn-media::media.list.download') }}
                            </a>
                            {{-- 复制/移动/删除复用行内同名 Filament Action：modal 容器在 DOM 后部自然位于预览层之上；移动/删除完成后服务端关闭预览 --}}
                            <button wire:click="mountAction('snMediaCopyPath', { id: {{ $this->previewMedia->id }} })" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800">
                                {{ __('sn-media::media.list.copy_path') }}
                            </button>
                            <button wire:click="mountAction('snMediaMove', { id: {{ $this->previewMedia->id }} })" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800">
                                {{ __('sn-media::media.list.move_to_directory') }}
                            </button>
                            <button wire:click="saveMeta" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-500">
                                {{ __('sn-media::media.preview.save_meta') }}
                            </button>
                            <button wire:click="mountAction('snMediaDelete', { id: {{ $this->previewMedia->id }} })" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-danger-600 hover:bg-danger-50 dark:border-gray-600 dark:hover:bg-danger-500/10">
                                {{ __('sn-media::media.list.delete') }}
                            </button>
                            <button wire:click="clearPreview" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white dark:bg-gray-700">
                                {{ __('sn-media::media.preview.close') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- 文件输入（页头上传 + 拖拽共用；JS 预检大小后手动 uploadMultiple） --}}
        <input type="file" multiple x-ref="fileInput" class="hidden" @change="onFilePick($event)">
    </div>

    {{-- 目录/素材操作 Actions（Filament 原生 modal 渲染容器） --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
