{{--
    字段主体的「单值」形态：有值时是预览（缩略图 / 扩展名 + 文件名 / 尺寸·大小·类型 / 路径 / 替换·移除），
    无值时是虚线触发器。多值走 media-field-grid / media-field-list。

    调用方需用 <template x-if="! multiple"> 包住本组件（Alpine 的 x-if 要求单个根元素，本组件根是下方 div）。

    要求调用方的 Alpine 对象提供：
    values / openPicker() / removeSingle() / openPreview(value) /
    isImageValue(v) / extLabel(v) / valueMeta(v) / thumbSrc(v) / isFull()
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
            {{-- 尺寸走内联样式：调用处传 px，避免动态 Tailwind 类（JIT 生成不了） --}}
            <div class="group relative shrink-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700" style="width: {{ $width }}px; height: {{ $height }}px">
                <template x-if="isImageValue(values)">
                    <img :src="thumbSrc(values)" class="size-full object-cover" alt="">
                </template>
                <template x-if="! isImageValue(values)">
                    <div class="grid size-full place-items-center bg-gray-50 dark:bg-gray-800">
                        <span class="text-xs font-bold tracking-wider text-gray-400" x-text="extLabel(values)"></span>
                    </div>
                </template>

                {{-- 放大镜 / 移除：底部满宽半透明黑条，图标居中、白色，悬停才出现 --}}
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
                            {{-- 不显示文件信息时只留移除：替换 = 移除后重新点空触发器 --}}
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
