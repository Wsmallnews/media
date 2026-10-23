@php
    use Illuminate\Support\Js;

    $props = $getPickerProps();
@endphp

{{-- 字段外壳交给 Filament 的包装器（label / helperText / 校验错误 / 可访问性都由它渲染），
     这是官方文档里自定义字段的标准写法；不要自己拼 ob_start + wrapEmbeddedHtml（那是 @internal）。 --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" label-tag="div">
    <div
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('sn-media-picker-field', 'wsmallnews/media') }}"
        x-data="snMediaPickerField({{ Js::from($props) }})"
        x-on:sn-media-selected.window="onSelected($event.detail)"
        x-on:sn-media-picker-closed.window="onClosed($event.detail)"
        {{ $attributes->merge(['class' => 'sn-media-picker-field']) }}
    >
        {{-- 触发外观与预览层和 FilePicker 共用同一套组件（差别只有值的来源） --}}
        <div wire:ignore>
            <template x-if="! multiple">
                <x-sn-media::filament.forms.media-field-single
                    :width="$props['width']"
                    :height="$props['height']"
                    :image-preview="$props['imagePreview']"
                    :preview-info="$props['previewInfo']"
                    :trigger-icon="$getTriggerIcon()"
                    :trigger-label="$getTriggerLabel()"
                />
            </template>

            <template x-if="multiple">
                @if ($props['itemsLayout'] === 'list')
                    <x-sn-media::filament.forms.media-field-list
                        :image-preview="$props['imagePreview']"
                        :trigger-icon="$getTriggerIcon()"
                        :trigger-label="$getTriggerLabel()"
                    />
                @else
                    <x-sn-media::filament.forms.media-field-grid
                        :width="$props['width']"
                        :height="$props['height']"
                        :image-preview="$props['imagePreview']"
                        :trigger-icon="$getTriggerIcon()"
                        :trigger-label="$getTriggerLabel()"
                    />
                @endif
            </template>
        </div>

        {{-- 弹层形态由字段自己决定（调用处 ->pickerDisplay() / ->pickerSlideOver() 覆盖，字段有默认值）：
             居中（modal）或侧滑（slide-over + 左/右）。
             sn-filament-modal-flush：去掉内容区自带 p-6（picker 自带工具栏与底栏，要贴边铺满）；
             sn-filament-modal-fill ：仅居中形态需要——给窗口一个确定高度，素材区内部滚动，不出现双滚动条。 --}}
        <x-filament::modal
            :id="$props['modalId']"
            :slide-over="$isSlideOver()"
            :slide-over-position="$isSlideOver() ? $getSlideOverPosition() : null"
            :width="$getPickerWidth()"
            :heading="__('sn-media::media.picker.select')"
            class="sn-filament-modal-flush {{ $isSlideOver() ? '' : 'sn-filament-modal-fill' }}"
        >
            <livewire:sn-media-filament::picker
                :multiple="$props['multiple']"
                :accepted-mimes="$props['acceptedMimes']"
                :ratio="$props['ratio']"
                :max-count="$props['maxCount'] ?? 0"
                :view-mode="$props['viewMode']"
                :sort-by="$props['sortBy']"
                :file-directory="$props['directory']"
                :state-path="$props['statePath']"
                :field-id="$props['fieldId']"
                :initial-values="$props['initialValues']"
                wire:key="sn-media-picker-{{ $props['fieldId'] }}"
            />
        </x-filament::modal>

        <x-sn-media::filament.forms.media-field-zoom :enabled="$props['imagePreview']" />
    </div>
</x-dynamic-component>
