@php
    use Illuminate\Support\Js;

    $props = $getPickerProps();
@endphp

{{-- Field shell is rendered by Filament's wrapper view (standard custom field setup). --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" label-tag="div">
    <div
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('sn-media-file-picker-field', 'wsmallnews/media') }}"
        x-data="snFilePickerField({{ Js::from($props) }})"
        {{ $attributes->merge(['class' => 'sn-file-picker-field']) }}
    >
        {{-- Shares the trigger and preview components with MediaPicker, but clicking here opens the native file dialog. --}}
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

        {{-- Native on purpose: a hidden file input is plumbing, not a form control. --}}
        <input
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
