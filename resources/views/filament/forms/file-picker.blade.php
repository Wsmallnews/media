@php
    use Filament\Support\Icons\Heroicon;

    $props = $getPickerProps();
@endphp

<div
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('sn-media-file-picker-field', 'wsmallnews/media') }}"
    x-data="snFilePickerField({{ \Illuminate\Support\Js::from($props) }})"
    {{ $attributes->merge(['class' => 'sn-file-picker-field']) }}>
    {{-- 本地状态承载区（wire:ignore）：变更经 Alpine 本地状态 + $wire.set 回写 --}}
    <div wire:ignore>

        {{-- 单值形态 --}}
        <template x-if="!multiple">
            <div>
                <template x-if="values">
                    <div class="flex items-start gap-3">
                        <div class="relative w-36 shrink-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700" :style="'aspect-ratio:' + ratio">
                            <template x-if="isImageValue(values)">
                                <img :src="thumbSrc(values)" class="size-full object-cover" alt="">
                            </template>
                            <template x-if="!isImageValue(values)">
                                <div class="grid size-full place-items-center bg-gray-50 dark:bg-gray-800">
                                    <span class="text-xs font-bold tracking-wider text-gray-400" x-text="extLabel(values)"></span>
                                </div>
                            </template>
                        </div>
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="truncate font-medium" x-text="values.name"></p>
                            <p class="mt-1 text-xs text-gray-500" x-text="valueMeta(values)"></p>
                            <p class="mt-0.5 truncate font-mono text-[11px] text-gray-400" x-text="values.path"></p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" @click="pick()" class="rounded-md border border-gray-300 px-2.5 py-1 text-xs hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800">{{ __('sn-media::media.file_picker.replace') }}</button>
                                <button type="button" @click="removeSingle()" class="rounded-md border border-gray-300 px-2.5 py-1 text-xs text-danger-600 hover:bg-danger-50 dark:border-gray-600 dark:hover:bg-danger-500/10">{{ __('sn-media::media.picker.remove') }}</button>
                            </div>
                        </div>
                    </div>
                </template>
                <template x-if="!values">
                    <button type="button" @click="pick()"
                        class="grid w-36 place-items-center rounded-lg border-2 border-dashed border-gray-300 text-sm text-gray-400 transition hover:border-primary-400 hover:text-primary-500 dark:border-gray-600"
                        :style="'aspect-ratio:' + ratio">
                        + {{ __('sn-media::media.file_picker.trigger') }}
                    </button>
                </template>
            </div>
        </template>

        {{-- 多值形态 --}}
        <template x-if="multiple">
            <div class="flex flex-wrap gap-2">
                <template x-for="(value, index) in values" :key="value.path">
                    <div class="relative">
                        <template x-if="isImageValue(value)">
                            <img :src="thumbSrc(value)" class="w-24 rounded-lg border border-gray-200 object-cover dark:border-gray-700" :style="'aspect-ratio:' + ratio">
                        </template>
                        <template x-if="!isImageValue(value)">
                            <div class="grid w-24 place-items-center rounded-lg border border-gray-200 bg-gray-50 text-xs font-bold tracking-wider text-gray-400 dark:border-gray-700 dark:bg-gray-800" :style="'aspect-ratio:' + ratio" x-text="extLabel(value)"></div>
                        </template>
                        <span class="absolute left-1 top-1 grid size-5 place-items-center rounded-full bg-gray-950/60 text-[10px] font-medium text-white" x-text="index + 1"></span>
                        <div class="absolute inset-x-0 bottom-0 flex justify-center gap-0.5 rounded-b-lg bg-gray-950/60 py-0.5 opacity-0 transition hover:opacity-100">
                            <button type="button" @click="move(index, -1)" title="{{ __('sn-media::media.picker.move_up') }}" class="grid size-5 place-items-center rounded text-white hover:bg-white/20">↑</button>
                            <button type="button" @click="move(index, 1)" title="{{ __('sn-media::media.picker.move_down') }}" class="grid size-5 place-items-center rounded text-white hover:bg-white/20">↓</button>
                            <button type="button" @click="removeAt(index)" title="{{ __('sn-media::media.picker.remove') }}" class="grid size-5 place-items-center rounded text-white hover:bg-white/20">×</button>
                        </div>
                    </div>
                </template>
                <button type="button" @click="pick()"
                    class="grid w-24 place-items-center rounded-lg border-2 border-dashed border-gray-300 text-gray-400 transition hover:border-primary-400 hover:text-primary-500 dark:border-gray-600"
                    :style="'aspect-ratio:' + ratio">
                    <x-filament::icon :icon="Heroicon::OutlinedPlus" class="size-5" />
                </button>
            </div>
        </template>

        {{-- 上传中 / 错误提示 --}}
        <div x-show="uploading" x-cloak class="mt-2 inline-flex items-center gap-1.5 rounded-md bg-primary-600/10 px-2.5 py-1 text-xs font-medium text-primary-700 dark:text-primary-300">
            <x-filament::icon :icon="Heroicon::OutlinedArrowUpTray" class="size-3.5 animate-pulse" />
            {{ __('sn-media::media.notifications.uploading') }}
        </div>
        <p x-show="error" x-cloak class="mt-2 text-xs text-danger-600" x-text="error"></p>
    </div>

    {{-- 文件输入（pick() 触发） --}}
    <input type="file" x-ref="input" class="hidden" :multiple="multiple" @change="onFiles($event)">
</div>
