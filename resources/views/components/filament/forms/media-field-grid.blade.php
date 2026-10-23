{{--
    字段主体的「多值网格」形态：每格一张缩略图（或扩展名），左上角序号，悬停出现放大/移除，末尾是虚线「+」。

    调用方需用 <template x-if="multiple"> 包住本组件（Alpine 的 x-if 要求单个根元素，本组件根是下方 div）。
    itemsLayout('grid') 时用它，'list' 时用 media-field-list。

    要求调用方的 Alpine 对象提供：
    values / openPicker() / removeAt(index) / openPreview(value) / isFull() /
    dragStart(i) / dragOver(i) / dragEnd() / dragIndex /
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

            {{-- 放大镜 / 移除：底部满宽半透明黑条，图标居中、白色，悬停才出现（与单值一致） --}}
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
