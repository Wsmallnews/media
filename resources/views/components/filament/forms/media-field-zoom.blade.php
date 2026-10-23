{{--
    字段的放大预览层（->imagePreview(false) 时整段不渲染）。

    做成独立组件而不是塞进主体里：它是叠在最上层的浮层，与「字段主体长什么样」无关，
    任何展示 thumbSrc(value) 的地方都能直接引。

    teleport 到 body：避免被表单容器的层叠上下文/裁剪影响；点背景或 Esc 关闭。

    要求调用方的 Alpine 对象提供：
    previewing（当前预览的值对象或 null）/ closePreview() / thumbSrc(v)
--}}
@props([
    'enabled' => false,
])

@php
    use Filament\Support\Icons\Heroicon;
@endphp

@if ($enabled)
    <template x-teleport="body">
        <div
            x-show="previewing"
            x-cloak
            x-on:keydown.escape.window="closePreview()"
            x-on:click.self="closePreview()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/80 p-6"
        >
            <img
                :src="previewing ? thumbSrc(previewing) : ''"
                :alt="previewing ? previewing.name : ''"
                class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
            >

            <div class="absolute right-4 top-4">
                <x-filament::icon-button
                    type="button"
                    :icon="Heroicon::OutlinedXMark"
                    color="gray"
                    size="lg"
                    :tooltip="__('sn-media::media.picker.close_preview')"
                    x-on:click="closePreview()"
                />
            </div>
        </div>
    </template>
@endif
