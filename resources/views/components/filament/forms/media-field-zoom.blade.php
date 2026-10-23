{{--
    Full-screen preview layer; nothing is rendered when imagePreview(false).
    Kept separate because it is an overlay unrelated to the field body and is reusable anywhere
    thumbSrc(value) exists. Teleported to body to escape the form's stacking and clipping context.
    Expects previewing / closePreview() / thumbSrc(v).
--}}
@props([
    'enabled' => false,
])

@php
    use Filament\Support\Icons\Heroicon;
@endphp

@if ($enabled)
    <template x-teleport="body">
        <div
            x-show="previewing"
            x-cloak
            x-on:keydown.escape.window="closePreview()"
            x-on:click.self="closePreview()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/80 p-6"
        >
            <img
                :src="previewing ? thumbSrc(previewing) : ''"
                :alt="previewing ? previewing.name : ''"
                class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
            >

            <div class="absolute right-4 top-4">
                <x-filament::icon-button
                    type="button"
                    :icon="Heroicon::OutlinedXMark"
                    color="gray"
                    size="lg"
                    :tooltip="__('sn-media::media.picker.close_preview')"
                    x-on:click="closePreview()"
                />
            </div>
        </div>
    </template>
@endif
