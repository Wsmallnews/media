@php
    use Filament\Support\Icons\Heroicon;
    use Illuminate\Support\Js;
    use Wsmallnews\Media\Enums\MediaType;

    $medias = $this->medias;
@endphp

{{--
    Media picker modal body.
    Height is left to the shell: the centered modal pins a window height so only the media
    area scrolls, the slide-over is h-dvh and needs no second scrollbar.
    Selection is front-end only — a round-trip per click makes the modal flicker; the server
    ships initial entries once via selectionEntries.
    @container sits on the root so toolbar, body and tree share one query context.
--}}
<div
    class="@container flex min-h-0 min-w-0 flex-1 flex-col"
    {{-- Key must be sourced from $fieldId like the field side; md5(statePath) collides across hosts sharing a state path. --}}
    wire:key="sn-media-picker-{{ $fieldId }}"
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('sn-media-manager', 'wsmallnews/media') }}"
    x-data="snMediaManager({{ Js::from([
        'uploadingLabel' => __('sn-media::media.notifications.uploading'),
        'rejectedLabel' => __('sn-media::media.notifications.upload_rejected_default'),
        'tooLargeLabel' => __('sn-media::media.notifications.upload_too_large', ['name' => '__NAME__', 'max' => '__MAX__']),
        'maxUploadKb' => $this->maxUploadKb(),
        // Seed of the local selection: ordered ids, plus id => {value, url}.
        'multiple' => $multiple,
        'statePath' => $statePath,
        'fieldId' => $fieldId,
        'selection' => array_values($selection),
        'entries' => $this->selectionEntries,
        // 0 = unlimited; otherwise the client caps toggles by the remaining slots.
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
        {{-- The very same sidebar as the media page; selection is server-driven via active-id. --}}
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
                    {{-- Two empty states: empty library vs. no filter match; mixing them reads as an empty library. --}}
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
                        {{-- Same row layout as the media page; toggling stays front-end, no request.
                             Secondary columns collapse as the container narrows. --}}
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

            {{-- Upload progress, shared with the media page and the FilePicker. --}}
            <x-sn-media::filament.components.upload-progress />
        </div>
    </div>

    {{-- Pagination in its own bar, identical to the media page. --}}
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
