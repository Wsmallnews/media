@php
    use Filament\Support\Icons\Heroicon;
    use Illuminate\Support\Js;
    use Wsmallnews\Media\Enums\MediaType;

    $medias = $this->medias;
@endphp

{{--
    素材选择弹窗内容（面板语境，视图落 views/filament/components）。

    高度：不写死高度，由弹层形态决定——
    - 居中 modal：外层 .sn-filament-modal-fill 给窗口一个固定高度，本组件 min-h-0 flex-1 铺满，
      素材多时只有素材区滚动（换页/切视图窗口不跳）；
    - 侧滑 slide-over：窗口本身 h-dvh，本组件铺满整高，天然没有双滚动条。

    选中态：**纯前端**。勾选/取消/确认都在 Alpine 本地数组里完成，不往返后端——
    每点一次都发请求会让弹层出现 loading 抖动，本地站点尤其明显。服务端只在首次渲染时
    把「初始选中 id」换成值对象 + 预览地址（selectionEntries）一次性下发，之后翻页、
    切目录都不需要再问后端要这批数据。上传完成走 sn-media-uploaded 事件把新素材并进来。

    断点说明：@container 声明在根元素，工具栏与主体（含目录树）都用容器查询；
    目录树组件（sn-filament-nestedset）的「桌面树 / 抽屉」也走容器查询（默认 @md），
    两者同源，不会出现「树按视口、容器按宽度」的错配带。
--}}
<div
    class="@container flex min-h-0 min-w-0 flex-1 flex-col"
    {{-- key 必须跟字段侧同源自 $fieldId（md5(statePath) 会让同状态路径的不同宿主撞 key） --}}
    wire:key="sn-media-picker-{{ $fieldId }}"
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('sn-media-manager', 'wsmallnews/media') }}"
    x-data="snMediaManager({{ Js::from([
        'uploadingLabel' => __('sn-media::media.notifications.uploading'),
        'rejectedLabel' => __('sn-media::media.notifications.upload_rejected_default'),
        'tooLargeLabel' => __('sn-media::media.notifications.upload_too_large', ['name' => '__NAME__', 'max' => '__MAX__']),
        'maxUploadKb' => $this->maxUploadKb(),
        // 本地选中态的种子：初始选中 id（有序）+ id => {value, url}
        'multiple' => $multiple,
        'statePath' => $statePath,
        'fieldId' => $fieldId,
        'selection' => array_values($selection),
        'entries' => $this->selectionEntries,
        // 0 = 不限；否则前端按「还能选几张」拦截勾选
        'maxCount' => $maxCount,
        'remainingLabel' => __('sn-media::media.picker.remaining', ['count' => '__COUNT__']),
        'limitLabel' => __('sn-media::media.picker.limit_reached', ['max' => '__MAX__']),
    ]) }})"
    x-on:sn-media-picker-open.window="syncPickerSelection($event.detail)"
    x-on:sn-media-uploaded.window="onMediaUploaded($event.detail)"
>
    <x-sn-media::filament.components.media-library-toolbar
        :breadcrumb="$this->directoryBreadcrumb()"
        :library-dir="$libraryDir"
        :library-type="$libraryType"
        :sort-options="$this->sortOptions()"
        :view-mode="$viewMode"
    >
        <span class="hidden @sm:flex">
            <x-filament::button type="button" size="sm" :icon="Heroicon::OutlinedArrowUpTray" x-on:click="$refs.fileInput.click()">
                {{ __('sn-media::media.toolbar.upload') }}
            </x-filament::button>
        </span>
        <span class="@sm:hidden">
            <x-filament::icon-button type="button" :icon="Heroicon::OutlinedArrowUpTray" color="gray" :tooltip="__('sn-media::media.toolbar.upload')" x-on:click="$refs.fileInput.click()" />
        </span>

        <input
            x-ref="fileInput"
            type="file"
            class="hidden"
            @if ($multiple) multiple @endif
            accept="{{ implode(',', $this->resolveAcceptedMimes() ?? []) }}"
            x-on:change="onFilePick($event)"
        >
    </x-sn-media::filament.components.media-library-toolbar>

    <div class="flex min-h-0 min-w-0 flex-1 flex-col @3xl:flex-row">
        {{-- 左侧：与素材管理页完全同一个 media-library-sidebar（aside 外壳、触发器、
             自定义条目、计数都在组件里）；选中态走服务端 active-id，不冻结 DOM。 --}}
        <x-sn-media::filament.components.media-library-sidebar
            :active-dir="$libraryDir"
            :all-count="$this->typeCounts['all'] ?? 0"
            :uncategorized-count="$this->uncategorizedCount"
        />

        <div class="relative flex min-h-0 min-w-0 flex-1 flex-col">
            <div class="min-h-0 min-w-0 flex-1 overflow-y-auto p-3">
                <div class="mb-3 flex flex-wrap items-center gap-1.5">
                    <x-filament::button type="button" wire:click="setLibraryType('')" size="sm" :color="$libraryType === '' ? 'primary' : 'gray'" :outlined="$libraryType !== ''" class="sn-media-chip">
                        {{ __('sn-media::media.tree.all') }} <span class="ml-0.5 opacity-70">{{ $this->typeCounts['all'] ?? 0 }}</span>
                    </x-filament::button>
                    @foreach (MediaType::cases() as $type)
                        @continue(! $type->isFilterable())
                        <x-filament::button type="button" wire:click="setLibraryType('{{ $type->value }}')" size="sm" :color="$libraryType === $type->value ? 'primary' : 'gray'" :outlined="$libraryType !== $type->value" class="sn-media-chip">
                            <x-filament::icon :icon="$type->getIcon()" class="size-3.5" />
                            {{ $type->getLabel() }} <span class="opacity-70">{{ $this->typeCounts[$type->value] ?? 0 }}</span>
                        </x-filament::button>
                    @endforeach
                </div>

                @if ($medias->count() === 0)
                    {{-- 空态分两种：库里没素材 vs 筛选没结果（混用会让人以为库是空的） --}}
                    @if ($this->hasActiveLibraryFilter())
                        <x-filament::empty-state
                            :contained="false"
                            :compact="true"
                            :icon="Heroicon::OutlinedMagnifyingGlass"
                            :heading="__('sn-media::media.picker.empty_filtered_heading')"
                            :description="__('sn-media::media.picker.empty_filtered')"
                        />
                    @else
                        <x-filament::empty-state
                            :contained="false"
                            :compact="true"
                            :icon="Heroicon::OutlinedPhoto"
                            :heading="__('sn-media::media.picker.empty_heading')"
                            :description="__('sn-media::media.picker.empty')"
                        />
                    @endif
                @else
                    @if ($viewMode === 'list')
                        {{-- 列表视图与管理页同源：单列行式，点击即切换选中（纯前端，不发请求）。
                             次要列（目录 / 日期 / 大小）按容器宽度逐级收起，窄容器只留名称 + 类型。 --}}
                        <div class="space-y-0.5">
                            @foreach ($medias as $media)
                                <x-filament::button
                                    tag="button"
                                    type="button"
                                    color="gray"
                                    wire:key="sn-media-picker-row-{{ $media->id }}"
                                    x-on:click="togglePicker({{ $media->id }}, {{ Js::from($media->toValue()) }}, {{ Js::from($media->url(200)) }})"
                                    x-bind:aria-pressed="isPickerSelected({{ $media->id }})"
                                    x-bind:class="isPickerSelected({{ $media->id }}) ? 'sn-ring-selected-inset' : ''"
                                    class="sn-media-row transition"
                                >
                                    <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-md bg-gray-50 dark:bg-gray-800">
                                        @if ($media->isImage())
                                            <img src="{{ $media->url(80) }}" alt="" class="size-full object-cover" loading="lazy" draggable="false">
                                        @else
                                            <img src="{{ $media->mediaType->getTileUrl() }}" alt="" class="size-8 rounded-md">
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $media->name }}</span>
                                    <span class="hidden w-24 shrink-0 truncate text-xs text-gray-500 @xl:block">{{ $media->directory?->name ?? __('sn-media::media.tree.uncategorized') }}</span>
                                    <span class="hidden w-16 shrink-0 text-xs text-gray-500 @sm:block">{{ $media->created_at->format('m-d') }}</span>
                                    <span class="w-20 shrink-0 truncate text-right text-xs text-gray-400">{{ $media->mediaType->getLabel() }}</span>
                                    <span class="hidden w-16 shrink-0 text-right text-xs text-gray-500 @sm:block">{{ $media->size_for_humans }}</span>
                                    <span x-cloak x-show="isPickerSelected({{ $media->id }})" class="shrink-0">
                                        <x-filament::icon :icon="Heroicon::Check" class="size-4 text-primary-600 dark:text-primary-400" />
                                    </span>
                                </x-filament::button>
                            @endforeach
                        </div>
                    @else
                        <div class="grid gap-2 [grid-template-columns:repeat(auto-fill,minmax(96px,1fr))]">
                            @foreach ($medias as $media)
                                <x-filament::button
                                    tag="button"
                                    type="button"
                                    color="gray"
                                    wire:key="sn-media-picker-item-{{ $media->id }}"
                                    x-on:click="togglePicker({{ $media->id }}, {{ Js::from($media->toValue()) }}, {{ Js::from($media->url(200)) }})"
                                    x-bind:aria-pressed="isPickerSelected({{ $media->id }})"
                                    x-bind:class="isPickerSelected({{ $media->id }}) ? 'sn-ring-selected' : ''"
                                    class="sn-media-tile transition"
                                >
                                    <div class="relative grid w-full place-items-center overflow-hidden rounded-md bg-gray-50 dark:bg-gray-800" style="aspect-ratio: {{ $ratio }}">
                                        @if ($media->isImage())
                                            <img src="{{ $media->url(200) }}" alt="{{ $media->alt ?: $media->name }}" class="size-full object-cover" loading="lazy" draggable="false">
                                        @else
                                            <img src="{{ $media->mediaType->getTileUrl() }}" alt="{{ $media->mediaType->getLabel() }}" class="size-14 rounded-lg">
                                        @endif

                                        <span x-cloak x-show="isPickerSelected({{ $media->id }})" class="absolute right-1 top-1 grid size-5 place-items-center rounded-full bg-primary-600 text-white">
                                            <x-filament::icon :icon="Heroicon::Check" class="size-3" />
                                        </span>
                                    </div>
                                    <span class="line-clamp-2 w-full break-all text-center text-[11px] leading-4 text-gray-600 dark:text-gray-400">{{ $media->name }}</span>
                                </x-filament::button>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>

            {{-- 上传进度（与素材管理页、FilePicker 共用同一个组件） --}}
            <x-sn-media::filament.components.upload-progress />
        </div>
    </div>

    {{-- 分页独立一栏（与素材管理页完全一致：同一组件、同一候选值、同一栏样式）。 --}}
    @if ($medias->hasPages())
        <div class="border-t border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/50">
            <x-filament::pagination
                class="w-full"
                :paginator="$medias"
                :page-options="\Wsmallnews\Media\Filament\Components\Picker::PER_PAGE_OPTIONS"
                current-page-option-property="perPage"
            />
        </div>
    @endif

    <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('sn-media::media.picker.selected_prefix') }} <span class="font-semibold text-primary-700 dark:text-primary-300" x-text="pickerSelection.length">{{ count($selection) }}</span>
            <span class="ml-2 text-xs text-gray-400">{{ $multiple ? __('sn-media::media.picker.multiple_mode') : __('sn-media::media.picker.single_mode') }}</span>
            @if ($multiple && $maxCount > 0)
                <span
                    class="ml-2 text-xs"
                    x-bind:class="pickerSelection.length >= maxCount ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400'"
                    x-text="pickerRemainingText()"
                ></span>
            @endif
        </p>
        <div class="flex gap-2">
            <x-filament::button type="button" color="gray" outlined x-on:click="cancelPicker()">{{ __('sn-media::media.picker.cancel') }}</x-filament::button>
            <x-filament::button type="button" x-on:click="confirmPicker()">{{ __('sn-media::media.picker.confirm') }}</x-filament::button>
        </div>
    </div>
</div>
