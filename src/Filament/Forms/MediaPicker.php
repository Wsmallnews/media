<?php

namespace Wsmallnews\Media\Filament\Forms;

use Closure;
use Filament\Forms\Components\Field;
use Wsmallnews\Media\Support\MediaValue;
use Wsmallnews\Media\Support\Utils;

/**
 * 素材选择字段：统一上传管道 + 素材库复用。
 *
 * 字段状态（值对象契约）：
 * - 单值：{path, name, mime, size, width, height} | null
 * - 多值：上述对象的数组，数组序即排序（模型侧建议 cast 为 array）
 *
 * 外观：ratio 由调用处定义（1 正方形、3 横幅…），作用于字段预览框与弹窗网格卡片。
 */
class MediaPicker extends Field
{
    protected string $view = 'sn-media::filament.forms.media-picker';

    protected bool | Closure $isMultiple = false;

    protected int | float | Closure $ratio = 1;

    protected string | Closure | null $directory = null;

    /** @var array<string> | Closure mime 模式（image/* 前缀或精确 mime），空 = 不限 */
    protected array | Closure $acceptedMimes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (MediaPicker $component, $state) {
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

    public function directory(string | Closure | null $directory): static
    {
        $this->directory = $directory;

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

    /**
     * 仅图片（常用语法糖）
     */
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

    public function getDirectory(): string
    {
        $directory = $this->evaluate($this->directory);

        return trim((string) ($directory ?: Utils::getConfig('directory', 'sn/media')), '/');
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
     * 传给弹窗组件与字段 Alpine 的 props
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
            'directory' => $this->getDirectory(),
            'statePath' => $this->getStatePath(),
            'initialValues' => $values,
            'urls' => $urls,
        ];
    }
}
