@php
    use Filament\Support\Icons\Heroicon;
@endphp

<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" :icon="Heroicon::OutlinedPaperAirplane">
                提交（演示页不落库）
            </x-filament::button>

            <span class="text-xs text-gray-500 dark:text-gray-400">
                选图 / 上传先留在前端，点「提交」后这里是真正会落库的值
            </span>
        </div>
    </form>

    {{-- Persisted field value (JSON contract). --}}
    <div class="mt-8 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
            <x-filament::icon :icon="Heroicon::OutlinedClipboardDocument" class="size-4 text-gray-400" />
            当前 state（保存值）
        </h2>

        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-3 text-[11px] leading-5 text-emerald-300">{{ json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</pre>

        <div class="mt-3 space-y-1.5">
            @foreach ($this->stateNotes() as $key => $note)
                <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                    <span class="font-mono text-gray-700 dark:text-gray-300">{{ $key }}</span>
                    — {{ $note }}
                </p>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
