{{--
    字段触发器：单值空态、多值网格「+」瓦片、多值列表「+ 添加」行**共用同一个外观**，
    不允许哪个视图再手写一份「+ 按钮」——三处走散过一轮，代价是每次改触发器都要改三遍。

    本组件只有外观，不绑定行为：事件与禁用态由调用方经 $attributes 传入
    （x-on:click="openPicker()" / x-bind:disabled="isFull()"）。

    icon + label 横排居中，靠 flex-wrap 自动换行：
    - 一行放得下 → icon 在左、label 在右；
    - 一行放不下 → label 被挤到第二行（视觉上就是 icon 在上、label 在下）；
    - 独占一行仍超宽 → truncate 出省略号（用户自己控制字数）。
    所以：**别给 label 加 min-w-0**（会压在同一行里直接缩没、永远换不了行），
    **也别用 line-clamp-***（与 truncate 互斥，且只能一行）。

    width 决定形态：
    - 传 width → 固定盒子（方形虚线框；height 不传按 ratio 换算后的值传入）；
    - 只传 width 不传 height → 通栏行（list 用，高度由 py-2 撑开）。
    两种形态版式一致，差别只有盒子尺寸。
--}}
@props([
    'icon' => null,
    'label' => null,
    'width' => null,
    'height' => null,
    'showsUploadProgress' => false,
])

@php
    use Filament\Support\Icons\Heroicon;

    $isFixedBox = (bool) $width;

    // 内联 px 不能写成动态 Tailwind 类（JIT 生成不了）；style 在 @php 里拼好，
    // 别在组件标签中间夹 @if —— 标签内解析不了指令，会编出语法错误的视图。
    $boxAttributes = array_filter([
        // relative：让「上传进度」这类覆盖层能盖在触发器上（FilePicker 上传时用）
        'class' => $isFixedBox
            ? 'relative flex items-center justify-center border-dashed p-2 text-gray-400'
            : 'relative flex w-full items-center justify-center border-dashed py-2 text-gray-400',
        'style' => $isFixedBox
            ? 'width: ' . (int) $width . 'px;' . ($height ? ' height: ' . (int) $height . 'px' : '')
            : null,
    ]);
@endphp

<x-filament::button
    type="button"
    color="gray"
    outlined
    {{ $attributes->merge($boxAttributes) }}
    aria-label="{{ $label ?: __('sn-media::media.picker.trigger') }}"
>
    {{-- content-center 不能省：inner 是 100% 高、换行后是多行 flex，align-content 默认 stretch
         会把图标和文案分别顶到盒子上下的两端（截图验收踩到过），要的是两行整体居中。 --}}
    <span class="flex h-full w-full flex-wrap content-center items-center justify-center gap-1.5 overflow-hidden">
        <x-filament::icon :icon="$icon ?? Heroicon::OutlinedPlus" class="size-5 shrink-0" />

        @if (filled($label))
            <span class="max-w-full truncate">{{ $label }}</span>
        @endif
    </span>

    {{-- 上传进度覆盖层：只有 FilePicker 需要（它的上传发生在字段内部） --}}
    @if ($showsUploadProgress)
        <x-sn-media::filament.components.upload-progress variant="trigger" />
    @endif
</x-filament::button>
