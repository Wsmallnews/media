<?php

namespace Wsmallnews\Media\Support;

use ArrayAccess;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * A gallery of attached images: the unified read-side view over both stored shapes.
 *
 * Two shapes live in the same JSON column and stay as-written (no rewrite on save):
 *  - flat (legacy):   [{path, ...}, {path, ...}]
 *  - grouped:         {type: "grouped", groups: [{name, images: [{path, ...}]}]}
 *
 * Flat data reads as one unnamed group (isGrouped() = false, so frontends skip the
 * group switcher). Backwards-compatible collection interface: iteration, count()
 * and [$offset] operate on the flattened list, so existing `foreach ($model->images)`
 * code keeps working without knowing about groups; toArray() (the stored shape, not
 * the flattened list) makes the object form-state safe — Filament hydrates Arrayable
 * state to arrays automatically, and Livewire never has to serialize the object.
 */
class MediaGallery implements Arrayable, ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param  array<int, MediaValueGroup>  $groups
     */
    public function __construct(
        protected readonly array $groups,
        protected readonly bool $grouped,
    ) {}

    /**
     * Build from a stored (or submitted) raw value, detecting the shape.
     */
    public static function make(mixed $value): self
    {
        $value = is_string($value) ? json_decode($value, true) : $value;

        if (is_array($value) && ($value['type'] ?? null) === 'grouped') {
            $groups = collect((array) ($value['groups'] ?? []))
                ->map(fn (mixed $group): MediaValueGroup => MediaValueGroup::make($group))
                ->filter(fn (MediaValueGroup $group): bool => ! $group->isEmpty())
                ->values()
                ->all();

            return new self($groups, true);
        }

        $images = collect((array) $value)
            ->map(fn (mixed $item): ?MediaValue => is_array($item) && filled($item['path'] ?? null) ? MediaValue::make($item) : null)
            ->filter()
            ->values()
            ->all();

        return new self($images === [] ? [] : [new MediaValueGroup(null, $images)], false);
    }

    /**
     * All groups (flat data = a single unnamed group).
     *
     * @return array<int, MediaValueGroup>
     */
    public function groups(): array
    {
        return $this->groups;
    }

    /**
     * Whether the stored shape is a real grouped gallery (frontends render the
     * group switcher only when this is true).
     */
    public function isGrouped(): bool
    {
        return $this->grouped;
    }

    /**
     * Every image flattened in group order.
     *
     * @return array<int, MediaValue>
     */
    public function all(): array
    {
        return array_merge(...array_map(fn (MediaValueGroup $group): array => $group->values(), $this->groups) ?: [[]]);
    }

    public function first(): ?MediaValue
    {
        foreach ($this->groups as $group) {
            if ($first = $group->first()) {
                return $first;
            }
        }

        return null;
    }

    /**
     * The stored shape: grouped data keeps its wrapper, flat data stays a list.
     * (Arrayable — form state gets this via Filament's hydrateState; use all()
     * for the flattened list.)
     */
    public function toArray(): array
    {
        return $this->grouped
            ? ['type' => 'grouped', 'groups' => array_map(fn (MediaValueGroup $group): array => $group->toArray(), $this->groups)]
            : array_map(fn (MediaValue $image): array => (array) $image->toArray(), $this->all());
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->all());
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->all()[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->all()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('MediaGallery is a read-only view.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('MediaGallery is a read-only view.');
    }
}
