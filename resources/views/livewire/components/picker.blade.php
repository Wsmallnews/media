@php
    use Filament\Support\Icons\Heroicon;
    use Wsmallnews\Media\Enums\MediaType;
@endphp

<div class="flex max-h-[70vh] flex-col" wire:key="sn-media-picker-{{ md5($statePath) }}">
    {{-- header + tabs --}}
    <div class="flex items-center gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
        <h3 class="text-sm font-semibold">{{ __('sn-media::media.picker.select') }}</h3>
        <div class="ml-auto flex items-center gap-1 rounded-lg bg-gray-100 p-1 text-sm dark:bg-gray-800">
            <button wire:click="$set('tab', 'library')"
                class="rounded-md px-3 py-1 {{ $tab === 'library' ? 'bg-white font-medium shadow-sm dark:bg-gray-700' : 'text-gray-500' }}">
                {{ __('sn-media::media.picker.tab_library') }}
            </button>
            <button wire:click="$set('tab', 'upload')"
                class="rounded-md px-3 py-1 {{ $tab === 'upload' ? 'bg-white font-medium shadow-sm dark:bg-gray-700' : 'text-gray-500' }}">
                {{ __('sn-media::media.picker.tab_upload') }}
            </button>
        </div>
    </div>

    {{-- body --}}
    <div class="flex-1 overflow-y-auto p-4">
        @if ($tab === 'library')
            <p class="mb-2 text-xs text-gray-400">{{ __('sn-media::media.picker.library_hint') }}</p>

            {{-- 类型 / 搜索过滤 --}}
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <div class="flex flex-wrap gap-1">
                    <button wire:click="$set('filterType', '')"
                        class="rounded-full px-2.5 py-1 text-xs {{ $filterType === '' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400' }}">
                        {{ __('sn-media::media.tree.all') }}
                    </button>
                    @foreach (MediaType::cases() as $type)
                        @continue(! $type->isFilterable())
                        <button wire:click="$set('filterType', '{{ $type->value }}')"
                            class="rounded-full px-2.5 py-1 text-xs {{ $filterType === $type->value ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400' }}">
                            {{ $type->getLabel() }}
                        </button>
                    @endforeach
                </div>
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('sn-media::media.toolbar.search_placeholder') }}"
                    class="ml-auto w-44 rounded-lg border border-gray-300 px-2.5 py-1 text-sm dark:border-gray-600 dark:bg-gray-800">
            </div>

            @if (count($this->medias) === 0)
                <p class="py-10 text-center text-sm text-gray-400">{{ __('sn-media::media.picker.library_empty') }}</p>
            @else
                <div class="grid gap-2 [grid-template-columns:repeat(auto-fill,minmax(96px,1fr))]">
                    @foreach ($this->medias as $media)
                        @php
                            $selected = in_array($media->id, $selection, true);
                            $allowed = $this->allowsMime((string) $media->mime);
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
                        <div class="group relative flex cursor-pointer flex-col items-center gap-1 rounded-lg p-1.5 transition {{ $selected ? 'bg-primary-50 ring-2 ring-primary-500 dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800' }} {{ $allowed ? '' : 'cursor-not-allowed opacity-30' }}"
                            @click="{{ $allowed ? "\$wire.toggle({$media->id})" : 'void 0' }}">
                            <div class="relative grid w-full place-items-center overflow-hidden rounded-md bg-gray-50 dark:bg-gray-800" style="aspect-ratio: {{ $ratio }}">
                                @if ($media->isImage())
                                    <img src="{{ $media->url(200) }}" alt="{{ $media->alt ?: $media->name }}" class="size-full object-cover" loading="lazy" draggable="false">
                                @else
                                    <div class="grid size-14 place-items-center rounded-lg {{ $tile[0] }}">
                                        <span class="text-xs font-bold text-white">{{ $tile[1] }}</span>
                                    </div>
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

                @if ($this->medias->hasPages())
                    <div class="mt-3 flex items-center justify-center gap-2 text-xs">
                        <button wire:click="previousPage" @if ($this->medias->onFirstPage()) disabled @endif
                            class="rounded-md border border-gray-200 bg-white px-2 py-1 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800">‹</button>
                        <span class="text-gray-500">{{ $this->medias->currentPage() }} / {{ $this->medias->lastPage() }}</span>
                        <button wire:click="nextPage" @if (! $this->medias->hasMorePages()) disabled @endif
                            class="rounded-md border border-gray-200 bg-white px-2 py-1 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800">›</button>
                    </div>
                @endif
            @endif
        @else
            {{-- 本地上传 tab --}}
            <label class="grid cursor-pointer place-items-center rounded-xl border-2 border-dashed border-gray-300 px-6 py-12 text-center transition hover:border-primary-400 dark:border-gray-600"
                x-data
                @dragenter.prevent @dragover.prevent @drop.prevent="$wire.uploadMultiple('uploads', $event.dataTransfer.files)">
                <x-filament::icon :icon="Heroicon::OutlinedArrowUpTray" class="size-10 text-gray-300" />
                <p class="mt-2 text-sm font-medium text-gray-600 dark:text-gray-300">{{ __('sn-media::media.picker.drop_here') }}</p>
                <span class="mt-2 rounded-lg bg-primary-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-primary-500">{{ __('sn-media::media.picker.browse') }}</span>
                <p class="mt-3 text-xs text-gray-400">{{ __('sn-media::media.picker.upload_hint') }}</p>
                <input type="file" multiple class="hidden" wire:model="uploads">
                <div wire:loading.delay wire:target="uploads" class="mt-3 text-xs font-medium text-primary-600">
                    {{ __('sn-media::media.notifications.uploaded', ['count' => '...']) }}
                </div>
            </label>
        @endif
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
