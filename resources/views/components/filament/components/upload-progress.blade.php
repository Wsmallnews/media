{{--
    Upload progress bar shared by the page, the picker overlay and the FilePicker trigger.
    State (uploading / progress / uploadFilename / uploadLabel()) comes from support/upload-progress.js,
    spread into all three Alpine objects, so the same expressions work everywhere.
    variant: overlay floats over the media area, inline sits under the field,
    trigger covers the trigger itself (parent needs relative) and shows no text by default.
--}}
@props([
    'variant' => 'overlay',
    'showText' => null,
    'showFilename' => null,
])

@php
    $isTrigger = $variant === 'trigger';
    $showsText = $showText ?? ! $isTrigger;
    $showsFilename = $showFilename ?? ! $isTrigger;

    $boxClass = match ($variant) {
        // No padding: the bar spans the full trigger width.
        'trigger' => 'absolute inset-0 z-10 flex items-center rounded-lg bg-white/75 backdrop-blur-[1px] dark:bg-gray-900/70',
        'inline' => 'mt-2 rounded-lg border border-primary-200 bg-primary-50/70 p-2.5 dark:border-primary-500/30 dark:bg-primary-500/10',
        default => 'absolute inset-x-3 top-3 z-20 rounded-lg border border-primary-200 bg-white/95 p-2.5 shadow-lg backdrop-blur dark:border-primary-500/30 dark:bg-gray-900/95',
    };

    // Trigger variant also drops the radius so the bar stays edge to edge.
    $fillClass = $isTrigger
        ? 'h-full bg-primary-500 transition-all duration-150'
        : 'h-full rounded-full bg-primary-500 transition-all duration-150';

    $barClass = $isTrigger
        ? 'h-1.5 w-full overflow-hidden bg-primary-100/80 dark:bg-primary-500/20'
        : 'mt-1.5 h-1.5 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-500/20';
@endphp

<div
    x-show="uploading"
    x-cloak
    @if ($isTrigger)
        {{-- Covering the trigger: an uploading trigger must not reopen the file dialog. --}}
        x-on:click.stop.prevent
    @endif
    {{ $attributes->merge(['class' => $boxClass]) }}
>
    @if ($showsText)
        <div class="flex items-center justify-between gap-2 text-xs">
            <span class="font-medium text-primary-700 dark:text-primary-300" x-text="uploadLabel()"></span>
            <span class="shrink-0 font-mono text-primary-600 dark:text-primary-400" x-text="progress + '%'"></span>
        </div>
    @endif

    <div class="{{ $barClass }}">
        {{-- Floor at 4% so a fresh upload still shows a sliver instead of looking stalled. --}}
        <div class="{{ $fillClass }}" :style="'width:' + Math.max(progress, 4) + '%'"></div>
    </div>

    @if ($showsFilename)
        <p x-show="uploadFilename" x-cloak class="mt-1 truncate text-[11px] text-gray-400" x-text="uploadFilename"></p>
    @endif
</div>
