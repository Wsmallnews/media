@php
    use Illuminate\Support\Js;

    $props = $getPickerProps();
@endphp

{{-- 字段外壳由 Filament 包装器渲染（官方自定义字段写法） --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" label-tag="div">
    <div
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('sn-media-file-picker-field', 'wsmallnews/media') }}"
        x-data="snFilePickerField({{ Js::from($props) }})"
        {{ $attributes->merge(['class' => 'sn-file-picker-field']) }}
    >
        {{-- 触发外观与预览层和 MediaPicker 共用同一套组件；这里点触发器直接拉系统文件选择器 --}}
        <div wire:ignore>
            <template x-if="! multiple">
                <x-sn-media::filament.forms.media-field-single
                    :width="$props['width']"
                    :height="$props['height']"
                    :image-preview="$props['imagePreview']"
                    :preview-info="$props['previewInfo']"
                    :trigger-icon="$getTriggerIcon()"
                    :trigger-label="$getTriggerLabel()"
                    :shows-upload-progress="true"
                />
            </template>

            <template x-if="multiple">
                @if ($props['itemsLayout'] === 'list')
                    <x-sn-media::filament.forms.media-field-list
                        :image-preview="$props['imagePreview']"
                        :trigger-icon="$getTriggerIcon()"
                        :trigger-label="$getTriggerLabel()"
                        :shows-upload-progress="true"
                    />
                @else
                    <x-sn-media::filament.forms.media-field-grid
                        :width="$props['width']"
                        :height="$props['height']"
                        :image-preview="$props['imagePreview']"
                        :trigger-icon="$getTriggerIcon()"
                        :trigger-label="$getTriggerLabel()"
                        :shows-upload-progress="true"
                    />
                @endif
            </template>

        </div>

        <x-filament::input
            type="file"
            x-ref="input"
            class="hidden"
            x-bind:multiple="multiple"
            accept="{{ implode(',', $props['acceptedMimes']) }}"
            x-on:change="onFiles($event)"
        />

        <x-sn-media::filament.forms.media-field-zoom :enabled="$props['imagePreview']" />
    </div>
</x-dynamic-component>
