@props([
    'breadcrumb' => [],
    'libraryDir' => null,
    'libraryType' => '',
    'showSort' => true,
    'showViewToggle' => true,
    'sortOptions' => [],
    'viewMode' => 'grid',
])

@php
    use Filament\Support\Icons\Heroicon;
    use Wsmallnews\Media\Enums\MediaType;
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 border-b border-gray-200 p-3 dark:border-gray-700']) }}>
    <nav
        aria-label="{{ __('sn-media::media.toolbar.breadcrumb') }}"
        class="order-1 flex min-w-0 grow items-center gap-1 overflow-hidden text-sm text-gray-500 dark:text-gray-400"
    >
        @if ($libraryDir === null)
            <span class="truncate font-medium text-gray-900 dark:text-gray-100">{{ __('sn-media::media.tree.all') }}</span>
        @else
            <x-filament::link tag="button" type="button" size="sm" color="gray" wire:click="setLibraryDir(null)" class="shrink-0">
                {{ __('sn-media::media.tree.all') }}
            </x-filament::link>
            <x-filament::icon :icon="Heroicon::OutlinedChevronRight" class="size-3.5 shrink-0" />
            @if ($libraryDir === 0)
                <span class="truncate font-medium text-gray-900 dark:text-gray-100">{{ __('sn-media::media.tree.uncategorized') }}</span>
            @else
                @foreach ($breadcrumb as $crumb)
                    @if (! $loop->first)
                        <x-filament::icon :icon="Heroicon::OutlinedChevronRight" class="size-3.5 shrink-0" />
                    @endif
                    @if ($crumb['id'] === $libraryDir)
                        <span class="min-w-0 max-w-40 truncate font-medium text-gray-900 dark:text-gray-100">{{ $crumb['name'] }}</span>
                    @else
                        <x-filament::link tag="button" type="button" size="sm" color="gray" wire:click="setLibraryDir({{ $crumb['id'] }})" class="min-w-0 max-w-32 truncate">
                            {{ $crumb['name'] }}
                        </x-filament::link>
                    @endif
                @endforeach
            @endif
        @endif
        @if ($libraryType !== '')
            <x-filament::icon :icon="Heroicon::OutlinedChevronRight" class="size-3.5 shrink-0" />
            <x-filament::link tag="button" type="button" size="sm" color="gray" wire:click="setLibraryType('')" class="shrink-0">
                {{ MediaType::tryFrom($libraryType)?->getLabel() }}
            </x-filament::link>
        @endif
    </nav>

    {{-- Picker extras (upload / close); reordered per container width. --}}
    @if (filled(trim((string) $slot)))
        <div class="order-2 flex shrink-0 items-center gap-2 @3xl:order-4">
            {{ $slot }}
        </div>
    @endif

    {{-- View toggle; the active mode is filled with primary. --}}
    @if ($showViewToggle)
        <div class="sn-media-view-toggle order-3 shrink-0 @3xl:order-5">
            <x-filament::button.group class="h-8 p-px">
                <x-filament::button
                    type="button"
                    size="sm"
                    :color="$viewMode === 'grid' ? 'primary' : 'gray'"
                    :icon="Heroicon::OutlinedSquares2x2"
                    :tooltip="__('sn-media::media.toolbar.view_grid')"
                    :aria-pressed="$viewMode === 'grid' ? 'true' : 'false'"
                    wire:click="setViewMode('grid')"
                />
                <x-filament::button
                    type="button"
                    size="sm"
                    :color="$viewMode === 'list' ? 'primary' : 'gray'"
                    :icon="Heroicon::OutlinedBars3"
                    :tooltip="__('sn-media::media.toolbar.view_list')"
                    :aria-pressed="$viewMode === 'list' ? 'true' : 'false'"
                    wire:click="setViewMode('list')"
                />
            </x-filament::button.group>
        </div>
    @endif

    {{-- Sort options are supplied by the trait's sortOptions(). --}}
    @if ($showSort)
        <div class="order-4 basis-full @3xl:order-2 @3xl:basis-auto @3xl:w-44">
            <x-filament::input.wrapper class="sn-filament-input-sm">
                <x-filament::input.select
                    wire:model.live="sortBy"
                    aria-label="{{ __('sn-media::media.toolbar.sort.label') }}"
                >
                    @foreach ($sortOptions as $sortValue => $sortLabel)
                        <option value="{{ $sortValue }}">{{ $sortLabel }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    @endif

    {{-- inline-prefix renders the icon without Filament's divider or background block. --}}
    <div class="order-5 basis-full @3xl:order-3 @3xl:basis-auto @3xl:w-52">
        <x-filament::input.wrapper inline-prefix :prefix-icon="Heroicon::OutlinedMagnifyingGlass" class="sn-filament-input-sm">
            <x-filament::input
                type="search"
                inline-prefix
                wire:model.live.debounce.400ms="librarySearch"
                aria-label="{{ __('sn-media::media.toolbar.search_placeholder') }}"
                placeholder="{{ __('sn-media::media.toolbar.search_placeholder') }}"
            />
        </x-filament::input.wrapper>
    </div>
</div>
