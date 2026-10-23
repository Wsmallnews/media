@props([
    'activeDir' => null,
    'allCount' => 0,
    'uncategorizedCount' => 0,
])

@php
use Filament\Support\Icons\Heroicon;
@endphp

{{--
    Media library sidebar: directory tree shell plus the All / Uncategorized rows.
    Shared by the page and the picker modal; selection is server-driven via active-id,
    so no client-side mirror is kept.
--}}

<aside class="contents @3xl:flex @3xl:w-56 @3xl:shrink-0 @3xl:flex-col @3xl:overflow-y-auto @3xl:border-r @3xl:border-gray-200 dark:@3xl:border-gray-700 [scrollbar-gutter:stable] @3xl:relative">
    <livewire:sn-media-filament::media-directories :active-id="$activeDir">
        <livewire:slot name="mobileTrigger">
            <span class="flex w-full items-center justify-start gap-1.5 bg-gray-100 px-3 py-1.5 text-left text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <x-filament::icon :icon="Heroicon::OutlinedFolderOpen" class="size-4" />
                {{ __('sn-media::media.tree.directories') }}
            </span>
        </livewire:slot>

        <p class="px-3 pb-1 pt-2 text-xs font-semibold tracking-wide text-gray-400">{{ __('sn-media::media.tree.directories') }}</p>

        <div class="px-2">
            <div @class(['group sn-nestedset-row', 'is-active' => $activeDir === null])>
                <span class="size-4 shrink-0"></span>
                <a
                    class="sn-nestedset-row-label"
                    href="javascript:;"
                    wire:click="setLibraryDir(null)"
                >
                    <span class="min-w-0 truncate">{{ __('sn-media::media.tree.all') }}</span>
                    <span class="sn-media-tree-count">{{ $allCount }}</span>
                </a>
            </div>

            <div @class(['group sn-nestedset-row', 'is-muted', 'is-active' => $activeDir === 0])>
                <span class="size-4 shrink-0"></span>
                <a
                    class="sn-nestedset-row-label"
                    href="javascript:;"
                    wire:click="setLibraryDir(0)"
                >
                    <span class="min-w-0 truncate">{{ __('sn-media::media.tree.uncategorized') }}</span>
                    <span class="sn-media-tree-count">{{ $uncategorizedCount }}</span>
                </a>
            </div>
        </div>
    </livewire:sn-media-filament::media-directories>
</aside>
