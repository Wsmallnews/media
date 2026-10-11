@php
    use Filament\Support\Icons\Heroicon;
    use Wsmallnews\Media\Enums\MediaType;
@endphp

<div
    {{-- Alpine 资产组件需显式 x-load 触发脚本加载，否则 x-data 函数未定义会连坐整个子树 --}}
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('sn-media-manager', 'wsmallnews/media') }}"
    x-data="snMediaManager()"
    class="sn-media-page">
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

        {{-- 工具栏 --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 p-3 dark:border-gray-700">
            {{-- 面包屑 --}}
            <nav class="flex min-w-0 items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                @if ($filterType !== '' || $filterDir !== '')
                    <button wire:click="$set('filterType', '') $set('filterDir', '')" class="hover:text-primary-600">{{ __('sn-media::media.global_default.navigation_group') }}</button>
                    <x-filament::icon :icon="Heroicon::OutlinedChevronRight" class="size-3.5" />
                @endif
                @if ($filterType !== '')
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ MediaType::from($filterType)->getLabel() }}</span>
                @elseif ($filterDir !== '')
                    <span class="truncate font-mono text-xs font-medium text-gray-900 dark:text-gray-100">{{ $filterDir }}</span>
                @else
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('sn-media::media.tree.all') }}</span>
                @endif
            </nav>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <select wire:model.live="sortBy" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="created_desc">{{ __('sn-media::media.toolbar.sort.created_desc') }}</option>
                    <option value="name_asc">{{ __('sn-media::media.toolbar.sort.name_asc') }}</option>
                    <option value="size_desc">{{ __('sn-media::media.toolbar.sort.size_desc') }}</option>
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
                    <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('sn-media::media.toolbar.search_placeholder') }}"
                        class="w-52 rounded-lg border border-gray-300 py-1.5 pl-8 pr-3 text-sm outline-none focus:border-primary-500 dark:border-gray-600 dark:bg-gray-800">
                </div>

                <button type="button" @click="$refs.fileInput.click()"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500">
                    <x-filament::icon :icon="Heroicon::OutlinedArrowUpTray" class="size-4" />
                    {{ __('sn-media::media.toolbar.upload') }}
                </button>
                <input type="file" multiple x-ref="fileInput" class="hidden" wire:model="uploads">
            </div>
        </div>

        {{-- 批量操作条（Alpine 本地选中态） --}}
        <div x-show="selected.length" x-cloak
            class="flex flex-wrap items-center gap-2 border-b border-primary-100 bg-primary-50 px-3 py-2 text-sm dark:border-primary-500/20 dark:bg-primary-500/10">
            <span class="font-medium text-primary-700 dark:text-primary-300">
                {{ __('sn-media::media.list.selected_prefix') }} <span x-text="selected.length"></span>
            </span>
            <span class="mx-1 h-4 w-px bg-primary-200 dark:bg-primary-500/30"></span>
            <button @click="batchDelete()" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-danger-600 hover:bg-white dark:hover:bg-gray-800">
                <x-filament::icon :icon="Heroicon::OutlinedTrash" class="size-4" />
                {{ __('sn-media::media.list.delete_batch') }}
            </button>
            <button @click="selected = []" class="ml-auto rounded-md px-2 py-1 text-gray-500 hover:bg-white dark:hover:bg-gray-800">{{ __('sn-media::media.list.clear_selection') }}</button>
        </div>

        <div class="flex min-h-[480px]">
            {{-- 左侧：类型 + 目录树 --}}
            <nav class="hidden w-56 shrink-0 space-y-1 overflow-y-auto border-r border-gray-200 p-3 text-sm md:block dark:border-gray-700">
                <p class="px-2 pb-1 text-xs font-semibold tracking-wide text-gray-400">{{ __('sn-media::media.tree.types') }}</p>

                <button wire:click="$set('filterType', '') $set('filterDir', '')"
                    class="flex w-full items-center justify-between rounded-md px-2 py-1.5 {{ $filterType === '' && $filterDir === '' ? 'bg-primary-50 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                    <span class="flex items-center gap-2">
                        <x-filament::icon :icon="Heroicon::OutlinedFolderOpen" class="size-4 text-gray-400" />
                        {{ __('sn-media::media.tree.all') }}
                    </span>
                    <span class="text-xs text-gray-400">{{ $this->typeCounts['all'] }}</span>
                </button>

                @foreach (MediaType::cases() as $type)
                    @continue(! $type->isFilterable())
                    <button wire:click="$set('filterType', '{{ $type->value }}') $set('filterDir', '')"
                        class="flex w-full items-center justify-between rounded-md px-2 py-1.5 {{ $filterType === $type->value ? 'bg-primary-50 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                        <span class="flex items-center gap-2">
                            <x-filament::icon :icon="$type->getIcon()" class="size-4 text-gray-400" />
                            {{ $type->getLabel() }}
                        </span>
                        <span class="text-xs text-gray-400">{{ $this->typeCounts[$type->value] ?? 0 }}</span>
                    </button>
                @endforeach

                @if (count($this->directories) > 0)
                    <p class="px-2 pb-1 pt-3 text-xs font-semibold tracking-wide text-gray-400">{{ __('sn-media::media.tree.directories') }}</p>
                    @foreach ($this->directories as $dir)
                        <button wire:click="$set('filterDir', '{{ $dir['prefix'] }}') $set('filterType', '')"
                            class="flex w-full items-center justify-between rounded-md px-2 py-1.5 font-mono text-xs {{ $filterDir === $dir['prefix'] ? 'bg-primary-50 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800' }}"
                            style="padding-left: {{ 8 + $dir['depth'] * 12 }}px">
                            <span class="truncate">{{ $dir['label'] }}</span>
                            <span class="text-[10px] text-gray-400">{{ $dir['count'] }}</span>
                        </button>
                    @endforeach
                @endif

                <div class="mt-4 rounded-lg bg-gray-50 p-2.5 text-[11px] leading-5 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    {{ __('sn-media::media.tree.tree_hint') }}
                </div>
            </nav>

            {{-- 右侧：内容区（拖拽上传 + 大图标/详细信息） --}}
            <div class="relative flex-1 p-3"
                @dragenter.prevent="dragOver = true"
                @dragover.prevent
                @dragleave.prevent="if (event.target === this) dragOver = false"
                @drop.prevent="onDrop($event)">

                {{-- 拖拽上传覆盖层 --}}
                <div x-show="dragOver" x-cloak
                    class="pointer-events-none absolute inset-2 z-10 grid place-items-center rounded-xl border-2 border-dashed border-primary-400 bg-primary-50/90 dark:bg-primary-500/10">
                    <div class="text-center">
                        <x-filament::icon :icon="Heroicon::OutlinedArrowUpTray" class="mx-auto size-10 text-primary-500" />
                        <p class="mt-2 text-sm font-medium text-primary-700 dark:text-primary-300">{{ __('sn-media::media.picker.drop_here') }}</p>
                    </div>
                </div>

                {{-- 上传中 --}}
                <div wire:loading.delay wire:target="uploads"
                    class="absolute inset-x-3 top-3 z-10 rounded-lg bg-primary-600/90 px-3 py-2 text-center text-xs font-medium text-white">
                    {{ __('sn-media::media.notifications.uploaded', ['count' => '...']) }}
                </div>

                {{-- 空状态 --}}
                @if (count($this->medias) === 0)
                    <div class="grid h-full place-items-center">
                        <div class="rounded-xl border-2 border-dashed border-gray-200 px-10 py-12 text-center text-sm text-gray-400 dark:border-gray-700">
                            {{ __('sn-media::media.list.empty') }}
                        </div>
                    </div>
                @elseif ($viewMode === 'grid')
                    {{-- 大图标视图 --}}
                    <div class="grid gap-2 [grid-template-columns:repeat(auto-fill,minmax(112px,1fr))]">
                        @foreach ($this->medias as $media)
                            @php
                                $tile = match ($media->mediaType) {
                                    MediaType::Video => ['bg-purple-500', 'MP4'],
                                    MediaType::Audio => ['bg-pink-500', 'MP3'],
                                    MediaType::Pdf => ['bg-red-500', 'PDF'],
                                    MediaType::Word => ['bg-blue-600', 'DOC'],
                                    MediaType::Excel => ['bg-green-600', 'XLS'],
                                    MediaType::Ppt => ['bg-orange-500', 'PPT'],
                                    MediaType::Zip => ['bg-amber-500', 'ZIP'],
                                    MediaType::File => ['bg-gray-400', 'FILE'],
                                    default => ['', ''],
                                };
                            @endphp
                            <div class="group flex cursor-pointer flex-col items-center gap-1.5 rounded-lg p-2 transition"
                                :class="isSelected({{ $media->id }}) ? 'bg-primary-50 ring-2 ring-primary-500 dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                                data-path="{{ $media->path }}"
                                @click="toggle({{ $media->id }})"
                                x-on:dblclick="$wire.openPreview({{ $media->id }})">
                                <div class="relative w-full">
                                    <div class="grid aspect-square w-full place-items-center overflow-hidden rounded-md bg-gray-50 dark:bg-gray-800">
                                        @if ($media->isImage())
                                            <img src="{{ $media->url(200) }}" alt="{{ $media->alt ?: $media->name }}" class="size-full object-cover" loading="lazy" draggable="false">
                                        @else
                                            <div class="grid size-20 place-items-center rounded-xl {{ $tile[0] }}">
                                                <span class="text-sm font-bold tracking-wider text-white">{{ $tile[1] }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- hover 快捷操作：预览 / 复制路径 / 下载 --}}
                                    <div class="absolute right-1 top-1 flex flex-col gap-1 opacity-0 transition group-hover:opacity-100">
                                        <button @click.stop="$wire.openPreview({{ $media->id }})" title="{{ __('sn-media::media.list.preview') }}"
                                            class="grid size-6 place-items-center rounded-full bg-white/95 text-gray-600 shadow ring-1 ring-gray-200 hover:text-primary-600 dark:bg-gray-900/95 dark:ring-gray-600">
                                            <x-filament::icon :icon="Heroicon::OutlinedEye" class="size-3.5" />
                                        </button>
                                        <button @click.stop="copyPath($event)" title="{{ __('sn-media::media.list.copy_path') }}"
                                            class="grid size-6 place-items-center rounded-full bg-white/95 text-gray-600 shadow ring-1 ring-gray-200 hover:text-primary-600 dark:bg-gray-900/95 dark:ring-gray-600">
                                            <x-filament::icon :icon="Heroicon::OutlinedClipboardDocument" class="size-3.5" />
                                        </button>
                                        <a href="{{ route('sn-media.download', ['media' => $media]) }}" @click.stop title="{{ __('sn-media::media.list.download') }}"
                                            class="grid size-6 place-items-center rounded-full bg-white/95 text-gray-600 shadow ring-1 ring-gray-200 hover:text-primary-600 dark:bg-gray-900/95 dark:ring-gray-600">
                                            <x-filament::icon :icon="Heroicon::OutlinedArrowDownTray" class="size-3.5" />
                                        </a>
                                    </div>
                                </div>
                                <p class="line-clamp-2 w-full break-all text-center text-xs leading-4 text-gray-700 dark:text-gray-300">{{ $media->name }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- 详细信息视图 --}}
                    <div class="text-sm">
                        <div class="flex items-center gap-3 border-b border-gray-200 px-2 pb-2 text-xs font-medium text-gray-400 dark:border-gray-700">
                            <span class="w-10 shrink-0"></span>
                            <span class="min-w-0 flex-1">{{ __('sn-media::media.list.name') }}</span>
                            <span class="w-24 shrink-0">{{ __('sn-media::media.list.created_at') }}</span>
                            <span class="w-24 shrink-0">{{ __('sn-media::media.list.type') }}</span>
                            <span class="w-20 shrink-0 text-right">{{ __('sn-media::media.list.size') }}</span>
                            <span class="w-[88px] shrink-0 text-right">{{ __('sn-media::media.list.actions') }}</span>
                        </div>
                        <div class="mt-1 space-y-0.5">
                            @foreach ($this->medias as $media)
                                @php
                                    $tile = match ($media->mediaType) {
                                        MediaType::Video => ['bg-purple-500', 'MP4'],
                                        MediaType::Audio => ['bg-pink-500', 'MP3'],
                                        MediaType::Pdf => ['bg-red-500', 'PDF'],
                                        MediaType::Word => ['bg-blue-600', 'DOC'],
                                        MediaType::Excel => ['bg-green-600', 'XLS'],
                                        MediaType::Ppt => ['bg-orange-500', 'PPT'],
                                        MediaType::Zip => ['bg-amber-500', 'ZIP'],
                                        MediaType::File => ['bg-gray-400', 'FILE'],
                                        default => ['', ''],
                                    };
                                @endphp
                                <div class="group flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 transition"
                                    :class="isSelected({{ $media->id }}) ? 'bg-primary-50 ring-1 ring-primary-400 dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                                    data-path="{{ $media->path }}"
                                    @click="toggle({{ $media->id }})"
                                    x-on:dblclick="$wire.openPreview({{ $media->id }})">
                                    <div class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-md bg-gray-50 dark:bg-gray-800">
                                        @if ($media->isImage())
                                            <img src="{{ $media->url(80) }}" alt="{{ $media->alt ?: $media->name }}" class="size-full object-cover" loading="lazy" draggable="false">
                                        @else
                                            <div class="grid size-8 place-items-center rounded-md {{ $tile[0] }}">
                                                <span class="text-[8px] font-bold text-white">{{ $tile[1] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <span class="min-w-0 flex-1 truncate font-medium text-gray-800 dark:text-gray-200">{{ $media->name }}</span>
                                    <span class="w-24 shrink-0 text-xs text-gray-500">{{ $media->created_at->format('m-d') }}</span>
                                    <span class="w-24 shrink-0 text-xs text-gray-500">{{ $media->mediaType->getLabel() }}</span>
                                    <span class="w-20 shrink-0 text-right text-xs text-gray-500">{{ $this->getFileSize((int) $media->size) }}</span>
                                    <div class="flex w-[88px] shrink-0 justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                        <button @click.stop="$wire.openPreview({{ $media->id }})" title="{{ __('sn-media::media.list.preview') }}"
                                            class="grid size-6 place-items-center rounded-md text-gray-400 hover:bg-white hover:text-primary-600 dark:hover:bg-gray-900">
                                            <x-filament::icon :icon="Heroicon::OutlinedEye" class="size-3.5" />
                                        </button>
                                        <button @click.stop="copyPath($event)" title="{{ __('sn-media::media.list.copy_path') }}"
                                            class="grid size-6 place-items-center rounded-md text-gray-400 hover:bg-white hover:text-primary-600 dark:hover:bg-gray-900">
                                            <x-filament::icon :icon="Heroicon::OutlinedClipboardDocument" class="size-3.5" />
                                        </button>
                                        <a href="{{ route('sn-media.download', ['media' => $media]) }}" @click.stop title="{{ __('sn-media::media.list.download') }}"
                                            class="grid size-6 place-items-center rounded-md text-gray-400 hover:bg-white hover:text-primary-600 dark:hover:bg-gray-900">
                                            <x-filament::icon :icon="Heroicon::OutlinedArrowDownTray" class="size-3.5" />
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- 状态栏 + 分页 --}}
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">
            <div>
                {{ __('sn-media::media.list.total_count', ['count' => $this->medias->total()]) }}
                ({{ $this->medias->currentPage() }}/{{ $this->medias->lastPage() }})
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="previousPage" @if ($this->medias->onFirstPage()) disabled @endif
                    class="rounded-md border border-gray-200 bg-white px-2 py-1 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800">‹</button>
                <button wire:click="nextPage" @if (! $this->medias->hasMorePages()) disabled @endif
                    class="rounded-md border border-gray-200 bg-white px-2 py-1 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800">›</button>
                <span class="hidden md:inline">{{ __('sn-media::media.page.description') }}</span>
            </div>
        </div>
    </div>

    {{-- 预览 + 信息编辑弹层 --}}
    @if ($this->previewMedia)
        <div class="fixed inset-0 z-50 grid place-items-center bg-gray-950/60 p-4" wire:click.self="clearPreview">
            <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900">
                <div class="grid min-h-64 place-items-center bg-gray-900 p-4">
                    @if ($this->previewMedia->isImage())
                        <img src="{{ $this->previewMedia->url() }}" alt="{{ $this->previewMedia->alt ?: $this->previewMedia->name }}" class="max-h-80 rounded-lg object-contain">
                    @else
                        <div class="grid place-items-center gap-3 py-6">
                            <x-filament::icon :icon="$this->previewMedia->mediaType->getIcon()" class="size-16 text-white" />
                            <p class="text-sm text-gray-300">{{ $this->previewMedia->name }}</p>
                        </div>
                    @endif
                </div>
                <div class="space-y-1.5 p-4 text-sm">
                    <div class="flex gap-2"><span class="w-20 shrink-0 text-gray-400">{{ __('sn-media::media.preview.path') }}</span><span class="min-w-0 break-all font-mono text-xs leading-5 text-gray-600 dark:text-gray-400">{{ $this->previewMedia->path }}</span></div>
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

                    <div class="flex items-center justify-end gap-2 pt-3">
                        <a href="{{ route('sn-media.download', ['media' => $this->previewMedia]) }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800">
                            <x-filament::icon :icon="Heroicon::OutlinedArrowDownTray" class="size-3.5" />
                            {{ __('sn-media::media.list.download') }}
                        </a>
                        <button wire:click="saveMeta" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800">
                            {{ __('sn-media::media.preview.save_meta') }}
                        </button>
                        <button wire:click="delete([{{ $this->previewMedia->id }}])" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-danger-600 hover:bg-danger-50 dark:border-gray-600 dark:hover:bg-danger-500/10">
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
</div>
