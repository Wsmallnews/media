<?php

namespace Wsmallnews\Media\Filament\Forms;

use Closure;
use Filament\Forms\Components\Field;
use Wsmallnews\Media\Support\MediaValue;

/**
 * 直接选文件的上传字段（不进素材库引用链，但照常入账走统一管道）：
 * 点击触发系统文件选择器 → 直传 /sn-media/upload → 值对象回填字段。
 * 适用：证书等敏感/业务文件——支持 ->disk('private') 私有盘（不可直链，预览经 file 路由）。
 *
 * 值契约与 MediaPicker 完全一致（单值对象 / 对象数组），media() presenter 统一渲染。
 */
class FilePicker extends Field
{
    protected string $view = 'sn-media::filament.forms.file-picker';

    protected bool | Closure $isMultiple = false;

    protected int | float | Closure $ratio = 1;

    /** @var array<string> | Closure mime 模式（仅用于 input accept 提示，强校验在服务端白名单） */
    protected array | Closure $acceptedMimes = [];

    protected string | Closure | null $disk = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (FilePicker $component, $state) {
            $multiple = $component->isMultiple();

            if ($multiple) {
                $state = is_array($state) ? array_values(array_filter($state, fn ($item) => is_array($item) && ($item['path'] ?? null))) : [];
            } else {
                $state = (is_array($state) && ($state['path'] ?? null)) ? $state : null;
            }

            $component->state($state);
        });
    }

    public function multiple(bool | Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function ratio(int | float | Closure $ratio): static
    {
        $this->ratio = $ratio;

        return $this;
    }

    /**
     * 目标盘（null = 活动盘；敏感文件传私有盘名，如 'local' 私有盘或自建 'private' 盘）
     */
    public function disk(string | Closure | null $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * @param  array<string> | Closure  $mimes
     */
    public function acceptedMimes(array | Closure $mimes): static
    {
        $this->acceptedMimes = $mimes;

        return $this;
    }

    public function acceptImages(): static
    {
        return $this->acceptedMimes(['image/*']);
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function getRatio(): float
    {
        return (float) $this->evaluate($this->ratio);
    }

    public function getDisk(): ?string
    {
        $disk = $this->evaluate($this->disk);

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    /**
     * @return array<int, string>
     */
    public function getAcceptedMimes(): array
    {
        $mimes = $this->evaluate($this->acceptedMimes);

        return array_values((array) $mimes);
    }

    /**
     * 传给字段 Alpine 的 props
     */
    public function getPickerProps(): array
    {
        $state = $this->getState();
        $multiple = $this->isMultiple();
        $values = $multiple ? (is_array($state) ? $state : []) : ($state ? [$state] : []);

        $urls = [];
        foreach ($values as $value) {
            if ($path = ($value['path'] ?? null)) {
                $urls[$path] = MediaValue::make($value)->url(200);
            }
        }

        return [
            'multiple' => $multiple,
            'acceptedMimes' => $this->getAcceptedMimes(),
            'ratio' => $this->getRatio(),
            'disk' => $this->getDisk(),
            'statePath' => $this->getStatePath(),
            'initialValues' => $values,
            'urls' => $urls,
            'uploadUrl' => route('sn-media.upload'),
            'csrfToken' => csrf_token(),
        ];
    }
}
