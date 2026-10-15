@php
    use Filament\Support\Icons\Heroicon;
    use Wsmallnews\Media\Enums\MediaType;
@endphp

<div class="flex h-[70vh] flex-col" wire:key="sn-media-picker-{{ md5($statePath) }}"
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
    ]) }})">

    {{-- 工具栏：面包屑 + 搜索 + 上传（直接拉起文件选择器） --}}
    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
        {{-- 面包屑：完整路径（根→当前，每级可点击）› 类型——与管理页一致 --}}
        <nav class="flex min-w-0 flex-wrap items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
            @if ($libraryDir === null)
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('sn-media::media.tree.all') }}</span>
            @else
                <button wire:click="setLibraryDir(null)" class="hover:text-primary-600">{{ __('sn-media::media.tree.all') }}</button>
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
                            <button wire:click="setLibraryDir({{ $crumb['id'] }})" class="max-w-32 truncate hover:text-primary-600">{{ $crumb['name'] }}</button>
                        @endif
                    @endforeach
                @endif
            @endif
            @if ($libraryType !== '')
                <x-filament::icon :icon="Heroicon::OutlinedChevronRight" class="size-3.5" />
                <button wire:click="setLibraryType('')" class="hover:text-primary-600">{{ MediaType::tryFrom($libraryType)?->getLabel() }}</button>
            @endif
        </nav>

        <div class="ml-auto flex items-center gap-2">
            <div class="relative">
                <x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                <input type="text" wire:model.live.debounce.400ms="librarySearch" placeholder="{{ __('sn-media::media.toolbar.search_placeholder') }}"
                    class="w-44 rounded-lg border border-gray-300 py-1.5 pl-8 pr-3 text-sm outline-none focus:border-primary-500 dark:border-gray-600 dark:bg-gray-800">
            </div>

            {{-- 上传：直接拉起系统文件选择器，归入当前浏览目录（JS 预检大小后手动 uploadMultiple） --}}
            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500">
                <x-filament::icon :icon="Heroicon::OutlinedArrowUpTray" class="size-4" />
                {{ __('sn-media::media.toolbar.upload') }}
                <input type="file" multiple class="hidden" @change="onFilePick($event)">
            </label>

            <button wire:click="$dispatch('sn-media-picker-closed', { statePath: '{{ $statePath }}' })"
                class="grid size-7 place-items-center rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
                <x-filament::icon :icon="Heroicon::OutlinedXMark" class="size-4" />
            </button>
        </div>
    </div>

    {{-- 上传进度条（连字符事件驱动） --}}
    <div wire:loading.delay wire:target="uploads" wire:key="picker-progress-{{ md5($statePath) }}"
        class="border-b border-primary-100 bg-primary-50/50 px-4 py-2 dark:border-primary-500/20 dark:bg-primary-500/5">
        <div class="flex items-center justify-between text-xs">
            <span class="font-medium text-primary-700 dark:text-primary-300">{{ __('sn-media::media.notifications.uploading') }}</span>
        </div>
        <div class="mt-1 h-1 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-500/20">
            <div class="h-full rounded-full bg-primary-500 animate-pulse transition-all duration-150" style="width: 100%"></div>
        </div>
    </div>

    {{-- body：左目录树 + 右内容 --}}
    <div class="flex min-h-0 flex-1">
        <aside class="hidden w-56 shrink-0 overflow-y-auto border-r border-gray-200 p-2 text-sm sm:block dark:border-gray-700 [scrollbar-gutter:stable]">
            <p class="mb-1 px-1 text-xs font-semibold tracking-wide text-gray-400">{{ __('sn-media::media.tree.directories') }}</p>

            {{-- 哨兵行：全部 / 未归类 --}}
            <button wire:click="setLibraryDir(null)"
                class="flex w-full items-center justify-between rounded-md px-2 py-1.5 {{ $libraryDir === null ? 'bg-primary-50 font-medium text-primary-500 dark:bg-primary-500/10 dark:text-primary-600' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' }}">
                <span>{{ __('sn-media::media.tree.all') }}</span>
            </button>
            <button wire:click="setLibraryDir(0)"
                class="flex w-full items-center justify-between rounded-md px-2 py-1.5 {{ $libraryDir === 0 ? 'bg-primary-50 font-medium text-primary-500 dark:bg-primary-500/10 dark:text-primary-600' : 'text-gray-400 italic hover:bg-gray-100 dark:text-gray-500 dark:hover:bg-white/5' }}">
                <span>{{ __('sn-media::media.tree.uncategorized') }}</span>
            </button>

            <livewire:sn-media::components.media-directories :active-id="$libraryDir" />
        </aside>

        <div class="flex-1 overflow-y-auto p-3">
            {{-- 类型 chips + 窄屏目录下拉 --}}
            <div class="mb-3 flex flex-wrap items-center gap-1.5">
                <select wire:change="setLibraryDir($event.target.value === 'all' ? null : parseInt($event.target.value))"
                    class="rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs sm:hidden dark:border-gray-600 dark:bg-gray-800">
                    <option value="all" @selected($libraryDir === null)>{{ __('sn-media::media.tree.all') }}</option>
                    <option value="0" @selected($libraryDir === 0)>{{ __('sn-media::media.tree.uncategorized') }}</option>
                    @foreach ($this->libraryDirectories as $treeDir)
                        <option value="{{ $treeDir['id'] }}" @selected($libraryDir === $treeDir['id'])>
                            {{ str_repeat('　', $treeDir['depth']) }}{{ $treeDir['name'] }}
                        </option>
                    @endforeach
                </select>

                <button wire:click="setLibraryType('')"
                    class="rounded-full px-2.5 py-1 text-xs {{ $libraryType === '' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                    {{ __('sn-media::media.tree.all') }} <span class="ml-0.5 opacity-70">{{ $this->typeCounts['all'] ?? 0 }}</span>
                </button>
                @foreach (MediaType::cases() as $type)
                    @continue(! $type->isFilterable())
                    <button wire:click="setLibraryType('{{ $type->value }}')"
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs {{ $libraryType === $type->value ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                        {{ $type->getLabel() }} <span class="opacity-70">{{ $this->typeCounts[$type->value] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>

            @if (count($this->medias()) === 0)
                <x-sn-support::empty :contained="false" :compact="true" :icon="Heroicon::OutlinedPhoto"
                    :heading="__('sn-media::media.picker.empty_heading')"
                    :description="__('sn-media::media.picker.empty')" />
            @else
                <div class="grid gap-2 [grid-template-columns:repeat(auto-fill,minmax(96px,1fr))]">
                    @foreach ($this->medias() as $media)
                        @php
                            $selected = in_array($media->id, $selection, true);
                            @endphp
                        <div class="group relative flex cursor-pointer flex-col items-center gap-1 rounded-lg p-1.5 transition {{ $selected ? 'bg-primary-50 ring-2 ring-primary-500 dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800' }}"
                            wire:click="toggle({{ $media->id }})">
                            <div class="relative grid w-full place-items-center overflow-hidden rounded-md bg-gray-50 dark:bg-gray-800" style="aspect-ratio: {{ $ratio }}">
                                @if ($media->isImage())
                                    <img src="{{ $media->url(200) }}" alt="{{ $media->alt ?: $media->name }}" class="size-full object-cover" loading="lazy" draggable="false">
                                @else
                                    <img src="{{ $media->mediaType->getTileUrl() }}" alt="{{ $media->mediaType->getLabel() }}" class="size-14 rounded-lg">
                                @endif

                                @if ($selected)
                                    <div class="absolute right-1 top-1 grid size-5 place-items-center rounded-full bg-primary-600 text-white">
                                        <x-filament::icon :icon="Heroicon::Check" class="size-3" />
                                    </div>
                                @endif
                            </div>
                            <p class="line-clamp-2 w-full break-all text-center text-[11px] leading-4 text-gray-600 dark:text-gray-400">{{ $media->name }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($this->medias()->hasPages())
                    <div class="mt-3 flex items-center justify-center gap-2 text-xs">
                        <button wire:click="previousPage" @if ($this->medias()->onFirstPage()) disabled @endif
                            class="rounded-md border border-gray-200 bg-white px-2 py-1 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800">‹</button>
                        <span class="text-gray-500">{{ $this->medias()->currentPage() }} / {{ $this->medias()->lastPage() }}</span>
                        <button wire:click="nextPage" @if (! $this->medias()->hasMorePages()) disabled @endif
                            class="rounded-md border border-gray-200 bg-white px-2 py-1 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800">›</button>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- footer --}}
    <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('sn-media::media.picker.selected_prefix') }} <span class="font-semibold text-primary-700 dark:text-primary-300">{{ count($selection) }}</span>
            <span class="ml-2 text-xs text-gray-400">{{ $multiple ? __('sn-media::media.picker.multiple_mode') : __('sn-media::media.picker.single_mode') }}</span>
        </p>
        <div class="flex gap-2">
            <button wire:click="$dispatch('sn-media-picker-closed', { statePath: '{{ $statePath }}' })"
                class="rounded-lg border border-gray-300 bg-white px-4 py-1.5 text-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700">
                {{ __('sn-media::media.picker.cancel') }}
            </button>
            <button wire:click="confirm"
                class="rounded-lg bg-primary-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-primary-500">
                {{ __('sn-media::media.picker.confirm') }}
            </button>
        </div>
    </div>
</div>
