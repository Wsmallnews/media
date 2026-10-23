{{--
    Shared trigger look for the single empty state, the grid "+" tile and the list "+ add" row.
    Appearance only: events and disabled state are passed in by the caller via $attributes.
    It wraps instead of truncating, so never add min-w-0 or line-clamp-* to the label
    (it would collapse on one line and could never wrap).
    width + height = fixed box; width only = full-width row whose height comes from py-2.
--}}
@props([
    'icon' => null,
    'label' => null,
    'width' => null,
    'height' => null,
    'showsUploadProgress' => false,
])

@php
    use Filament\Support\Icons\Heroicon;

    $isFixedBox = (bool) $width;

    // Inline px cannot be dynamic Tailwind classes (no JIT); build the style here and never
    // put @if inside a component tag — directives are not parsed there and yield a broken view.
    $boxAttributes = array_filter([
        // relative: lets the upload-progress overlay cover the trigger.
        'class' => $isFixedBox
            ? 'relative flex items-center justify-center border-dashed p-2 text-gray-400'
            : 'relative flex w-full items-center justify-center border-dashed py-2 text-gray-400',
        'style' => $isFixedBox
            ? 'width: ' . (int) $width . 'px;' . ($height ? ' height: ' . (int) $height . 'px' : '')
            : null,
    ]);
@endphp

<x-filament::button
    type="button"
    color="gray"
    outlined
    {{ $attributes->merge($boxAttributes) }}
    aria-label="{{ $label ?: __('sn-media::media.picker.trigger') }}"
>
    {{-- content-center is required: once the inner flex wraps, align-content defaults to stretch
         and pushes the icon and label to opposite ends instead of centering both lines. --}}
    <span class="flex h-full w-full flex-wrap content-center items-center justify-center gap-1.5 overflow-hidden">
        <x-filament::icon :icon="$icon ?? Heroicon::OutlinedPlus" class="size-5 shrink-0" />

        @if (filled($label))
            <span class="max-w-full truncate">{{ $label }}</span>
        @endif
    </span>

    {{-- Upload overlay: only the FilePicker needs it, its upload runs inside the field. --}}
    @if ($showsUploadProgress)
        <x-sn-media::filament.components.upload-progress variant="trigger" />
    @endif
</x-filament::button>
