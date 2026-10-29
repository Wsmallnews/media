<?php

namespace Wsmallnews\Media\Support;

/**
 * One named group of media values inside a gallery (or the single unnamed group
 * a flat list reads as; a null name means "no group switcher entry"). Named
 * after what it holds — MediaValue entries — so it stays type-agnostic (image,
 * file, whatever the field accepts); the storage key follows the same logic.
 */
class MediaValueGroup
{
    /**
     * @param  array<int, MediaValue>  $values
     */
    public function __construct(
        public readonly ?string $name,
        protected readonly array $values,
    ) {}

    /**
     * @param  array{name?: string|null, values?: array<int, mixed>}|mixed  $value
     */
    public static function make(mixed $value): self
    {
        $value = is_array($value) ? $value : [];

        $values = collect((array) ($value['values'] ?? []))
            ->map(fn (mixed $item): ?MediaValue => is_array($item) && filled($item['path'] ?? null) ? MediaValue::make($item) : null)
            ->filter()
            ->values()
            ->all();

        $name = $value['name'] ?? null;

        return new self(is_string($name) && $name !== '' ? $name : null, $values);
    }

    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * @return array<int, MediaValue>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function first(): ?MediaValue
    {
        return $this->values[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /**
     * @return array{name?: string|null, values: array<int, mixed>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'values' => array_map(fn (MediaValue $value): array => (array) $value->toArray(), $this->values),
        ];
    }
}
