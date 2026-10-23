{{--
    Multi-value grid body: one tile per value, index badge, hover zoom / remove, dashed "+" at the end.
    The caller must wrap it in <template x-if="multiple"> (Alpine x-if needs a single root).
    Expects the Alpine object to provide values / openPicker() / removeAt(index) /
    openPreview(value) / isFull() / dragStart / dragOver / dragEnd / dragIndex /
    isImageValue(v) / extLabel(v) / thumbSrc(v)
--}}
@props([
    'width' => 144,
    'height' => 144,
    'imagePreview' => false,
    'triggerIcon' => null,
    'triggerLabel' => null,
    'showsUploadProgress' => false,
])

@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div class="flex flex-wrap gap-2">
    <template x-for="(value, index) in values" :key="value.path">
        <div
            class="group relative shrink-0 cursor-move overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
            style="width: {{ $width }}px; height: {{ $height }}px"
            draggable="true"
            x-on:dragstart="dragStart(index)"
            x-on:dragenter.prevent="dragOver(index)"
            x-on:dragover.prevent
            x-on:drop.prevent="dragEnd()"
            x-on:dragend="dragEnd()"
            x-bind:class="dragIndex === index ? 'opacity-50' : ''"
        >
            <template x-if="isImageValue(value)">
                <img :src="thumbSrc(value)" class="size-full object-cover" alt="" draggable="false">
            </template>
            <template x-if="! isImageValue(value)">
                <div class="grid size-full place-items-center bg-gray-50 dark:bg-gray-800">
                    <span class="text-xs font-bold tracking-wider text-gray-400" x-text="extLabel(value)"></span>
                </div>
            </template>

            <span class="absolute left-1 top-1 grid size-5 place-items-center rounded-full bg-gray-950/60 text-[10px] font-medium text-white" x-text="index + 1"></span>

            <div class="pointer-events-none absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 bg-gray-950/60 py-0.5 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                @if ($imagePreview)
                    <x-filament::icon-button
                        type="button"
                        :icon="Heroicon::OutlinedMagnifyingGlassPlus"
                        color="gray"
                        size="sm"
                        style="color: #fff"
                        class="pointer-events-auto"
                        :tooltip="__('sn-media::media.picker.preview')"
                        x-show="isImageValue(value)"
                        x-on:click="openPreview(value)"
                    />
                @endif

                <x-filament::icon-button
                    type="button"
                    :icon="Heroicon::OutlinedXMark"
                    color="danger"
                    size="sm"
                    class="pointer-events-auto"
                    :tooltip="__('sn-media::media.picker.remove')"
                    x-on:click="removeAt(index)"
                />
            </div>
        </div>
    </template>

    <x-sn-media::filament.forms.media-field-trigger
        :width="$width"
        :height="$height"
        :icon="$triggerIcon"
        :label="$triggerLabel"
        :shows-upload-progress="$showsUploadProgress"
        x-on:click="openPicker()"
        x-bind:disabled="isFull()"
    />
</div>
