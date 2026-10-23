{{--
    字段主体的「多值列表」形态：一行一条（拖拽手柄 · 缩略图 · 名称/尺寸·大小·类型 · 序号 · 放大/移除），
    底部是虚线「添加」。一行一条才像列表，所以窄屏满宽、宽屏限宽。

    调用方需用 <template x-if="multiple"> 包住本组件（Alpine 的 x-if 要求单个根元素，本组件根是下方 div）。
    itemsLayout('list') 时用它，'grid' 时用 media-field-grid。

    要求调用方的 Alpine 对象提供：
    values / openPicker() / removeAt(index) / openPreview(value) / isFull() /
    dragStart(i) / dragOver(i) / dragEnd() / dragIndex /
    isImageValue(v) / extLabel(v) / itemMeta(v) / thumbSrc(v)
--}}
@props([
    'imagePreview' => false,
    'triggerIcon' => null,
    'triggerLabel' => null,
    'showsUploadProgress' => false,
])

@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div class="flex w-full flex-col gap-1.5 sm:max-w-md">
    <template x-for="(value, index) in values" :key="value.path">
        <div
            class="group flex w-full items-center gap-2 rounded-lg border border-gray-200 bg-white px-2 py-1.5 dark:border-gray-700 dark:bg-gray-900"
            draggable="true"
            x-on:dragstart="dragStart(index)"
            x-on:dragenter.prevent="dragOver(index)"
            x-on:dragover.prevent
            x-on:drop.prevent="dragEnd()"
            x-on:dragend="dragEnd()"
            x-bind:class="dragIndex === index ? 'opacity-50' : ''"
        >
            <x-filament::icon :icon="Heroicon::OutlinedBars3" class="size-4 shrink-0 cursor-move text-gray-300 dark:text-gray-600" />

            <span class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-md border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                <template x-if="isImageValue(value)">
                    <img :src="thumbSrc(value)" class="size-full object-cover" alt="" draggable="false">
                </template>
                <template x-if="! isImageValue(value)">
                    <span class="text-[10px] font-bold tracking-wider text-gray-400" x-text="extLabel(value)"></span>
                </template>
            </span>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-200" x-text="value.name"></p>
                <p class="truncate text-xs text-gray-400" x-text="itemMeta(value)"></p>
            </div>

            <span class="shrink-0 text-xs tabular-nums text-gray-400" x-text="index + 1"></span>

            <div class="flex shrink-0 items-center gap-0.5">
                @if ($imagePreview)
                    <x-filament::icon-button
                        type="button"
                        :icon="Heroicon::OutlinedMagnifyingGlassPlus"
                        color="gray"
                        size="sm"
                        :tooltip="__('sn-media::media.picker.preview')"
                        class="pointer-events-auto"
                        x-show="isImageValue(value)"
                        x-on:click="openPreview(value)"
                    />
                @endif

                <x-filament::icon-button
                    type="button"
                    :icon="Heroicon::OutlinedXMark"
                    color="danger"
                    size="sm"
                    :tooltip="__('sn-media::media.picker.remove')"
                    class="pointer-events-auto"
                    x-on:click="removeAt(index)"
                />
            </div>
        </div>
    </template>

    <x-sn-media::filament.forms.media-field-trigger
        :icon="$triggerIcon"
        :label="$triggerLabel"
        :shows-upload-progress="$showsUploadProgress"
        x-on:click="openPicker()"
        x-bind:disabled="isFull()"
    />
</div>
