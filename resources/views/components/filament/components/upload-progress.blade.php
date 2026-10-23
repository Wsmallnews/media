{{--
    上传进度（三个入口共用）：素材管理页、选择弹窗（variant=overlay）、FilePicker 字段（variant=trigger）。

    状态来自 support/upload-progress.js：uploading / progress / uploadFilename / uploadLabel()，
    三个 Alpine 对象都 spread 了同一份实现，所以这里的表达式对三处都成立。

    variant：
    - overlay：绝对定位浮在素材区顶部（页面/弹窗里不占布局高度），带文字；
    - inline ：字段下方一条窄条，带文字；
    - trigger：盖住触发器本身（父级要 relative），默认**只画进度条、不显示任何文字**。

    showText / showFilename 可单独控制文字：只要进度条就都传 false。
--}}
@props([
    'variant' => 'overlay',
    'showText' => null,
    'showFilename' => null,
])

@php
    $isTrigger = $variant === 'trigger';
    $showsText = $showText ?? ! $isTrigger;
    $showsFilename = $showFilename ?? ! $isTrigger;

    $boxClass = match ($variant) {
        // flex + 无内边距：进度条满宽、直接贴到触发器左右边，进度走完与否一眼可见
        'trigger' => 'absolute inset-0 z-10 flex items-center rounded-lg bg-white/75 backdrop-blur-[1px] dark:bg-gray-900/70',
        'inline' => 'mt-2 rounded-lg border border-primary-200 bg-primary-50/70 p-2.5 dark:border-primary-500/30 dark:bg-primary-500/10',
        default => 'absolute inset-x-3 top-3 z-20 rounded-lg border border-primary-200 bg-white/95 p-2.5 shadow-lg backdrop-blur dark:border-primary-500/30 dark:bg-gray-900/95',
    };

    // 满宽形态连圆角也去掉：进度条从触发器左边贴到右边
    $fillClass = $isTrigger
        ? 'h-full bg-primary-500 transition-all duration-150'
        : 'h-full rounded-full bg-primary-500 transition-all duration-150';

    $barClass = $isTrigger
        ? 'h-1.5 w-full overflow-hidden bg-primary-100/80 dark:bg-primary-500/20'
        : 'mt-1.5 h-1.5 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-500/20';
@endphp

<div
    x-show="uploading"
    x-cloak
    @if ($isTrigger)
        {{-- 盖在按钮上：上传中不要让它再触发一次文件选择 --}}
        x-on:click.stop.prevent
    @endif
    {{ $attributes->merge(['class' => $boxClass]) }}
>
    @if ($showsText)
        <div class="flex items-center justify-between gap-2 text-xs">
            <span class="font-medium text-primary-700 dark:text-primary-300" x-text="uploadLabel()"></span>
            <span class="shrink-0 font-mono text-primary-600 dark:text-primary-400" x-text="progress + '%'"></span>
        </div>
    @endif

    <div class="{{ $barClass }}">
        {{-- 起点给 4%：0% 时也能看见一条，避免"卡住了"的错觉 --}}
        <div class="{{ $fillClass }}" :style="'width:' + Math.max(progress, 4) + '%'"></div>
    </div>

    @if ($showsFilename)
        <p x-show="uploadFilename" x-cloak class="mt-1 truncate text-[11px] text-gray-400" x-text="uploadFilename"></p>
    @endif
</div>
