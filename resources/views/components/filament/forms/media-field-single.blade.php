{{--
    Single-value body: preview with meta when a value is set, dashed trigger otherwise.
    The caller must wrap it in <template x-if="! multiple"> (Alpine x-if needs a single root).
    Expects values / openPicker() / removeSingle() / openPreview(value) /
    isImageValue / extLabel / valueMeta / thumbSrc / isFull()
--}}
@props([
    'width' => 144,
    'height' => 144,
    'imagePreview' => false,
    'previewInfo' => true,
    'triggerIcon' => null,
    'triggerLabel' => null,
    'showsUploadProgress' => false,
])

@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div>
    <template x-if="values">
        <div class="flex items-start gap-3">
            {{-- Inline size: width/height are runtime props, and dynamic Tailwind classes would not be JIT-compiled. --}}
            <div class="group relative shrink-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700" style="width: {{ $width }}px; height: {{ $height }}px">
                <template x-if="isImageValue(values)">
                    <img :src="thumbSrc(values)" class="size-full object-cover" alt="">
                </template>
                <template x-if="! isImageValue(values)">
                    <div class="grid size-full place-items-center bg-gray-50 dark:bg-gray-800">
                        <span class="text-xs font-bold tracking-wider text-gray-400" x-text="extLabel(values)"></span>
                    </div>
                </template>

                @if ($imagePreview || ! $previewInfo)
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
                                x-show="isImageValue(values)"
                                x-on:click="openPreview(values)"
                            />
                        @endif

                        @unless ($previewInfo)
                            {{-- No file info means only remove; replacing equals remove then click the empty trigger. --}}
                            <x-filament::icon-button
                                type="button"
                                :icon="Heroicon::OutlinedXMark"
                                color="gray"
                                size="sm"
                                style="color: #fff"
                                class="pointer-events-auto"
                                :tooltip="__('sn-media::media.picker.remove')"
                                x-on:click="removeSingle()"
                            />
                        @endunless
                    </div>
                @endif
            </div>

            @if ($previewInfo)
                <div class="min-w-0 flex-1 text-sm">
                    <p class="truncate font-medium" x-text="values.name"></p>
                    <p class="mt-1 text-xs text-gray-500" x-text="valueMeta(values)"></p>
                    <p class="mt-0.5 truncate font-mono text-[11px] text-gray-400" x-text="values.path"></p>
                    <div class="mt-2 flex gap-2">
                        <x-filament::button type="button" size="sm" color="gray" outlined x-on:click="openPicker()">
                            {{ __('sn-media::media.picker.replace') }}
                        </x-filament::button>
                        <x-filament::button type="button" size="sm" color="danger" outlined x-on:click="removeSingle()">
                            {{ __('sn-media::media.picker.remove') }}
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </div>
    </template>

    <template x-if="! values">
        <x-sn-media::filament.forms.media-field-trigger
            :width="$width"
            :height="$height"
            :icon="$triggerIcon"
            :label="$triggerLabel"
            :shows-upload-progress="$showsUploadProgress"
            x-on:click="openPicker()"
            x-bind:disabled="isFull()"
        />
    </template>
</div>
