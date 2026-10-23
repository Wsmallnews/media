@php
    use Illuminate\Support\Js;

    $props = $getPickerProps();
@endphp

{{-- Field shell comes from Filament's wrapper view (label / helper text / validation / a11y).
     Do not hand-roll ob_start + wrapEmbeddedHtml, that API is @internal. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" label-tag="div">
    <div
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('sn-media-picker-field', 'wsmallnews/media') }}"
        x-data="snMediaPickerField({{ Js::from($props) }})"
        x-on:sn-media-selected.window="onSelected($event.detail)"
        x-on:sn-media-picker-closed.window="onClosed($event.detail)"
        {{ $attributes->merge(['class' => 'sn-media-picker-field']) }}
    >
        {{-- Shares the trigger and preview components with FilePicker; only the value source differs. --}}
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

        {{-- Shape is decided by the field (modal, or slide-over with a side):
             flush drops the content padding so the picker fills edge to edge; fill applies only
             to the centered modal and pins a window height, keeping scrolling inside the media area. --}}
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
