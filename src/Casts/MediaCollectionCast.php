<?php

namespace Wsmallnews\Media\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Wsmallnews\Media\Support\MediaGallery;

/**
 * Collection media field cast: the JSON column ⇄ MediaGallery presenter.
 *
 * Two stored shapes coexist and keep their shape as-written (a flat list never
 * gets rewritten into a grouped wrapper, and vice versa):
 *  - flat (legacy):  [{path, name, mime, size, width, height}, ...]   array order = order
 *  - grouped:        {type: "grouped", groups: [{name, images: [...]}, ...]}
 *
 * Reading returns a MediaGallery (a single unnamed group for flat data) whose
 * collection interface (foreach / count / [$i]) flattens in group order, so
 * pre-grouping call sites keep working; url()/alt lookups stay lazy and go
 * through the request-scoped MediaResolver cache.
 *
 * Writing normalizes by element shape — {images: …} entries are groups, {path: …}
 * entries are images — so both a flat picker's state and a grouped repeater's
 * items store correctly without the form telling the cast which editor ran.
 * Dirty entries (no path) and empty groups are dropped; an all-empty value
 * stores null.
 */
class MediaCollectionCast implements CastsAttributes
{
    /**
     * @return MediaGallery
     */
    public function get($model, string $key, $value, array $attributes)
    {
        return MediaGallery::make($value);
    }

    /**
     * @param  iterable<array<string, mixed>>|MediaGallery|null  $value
     */
    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof MediaGallery) {
            return $value->jsonSerialize() === [] ? null : json_encode($value->jsonSerialize());
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;
        $items = is_array($decoded) ? $decoded : [];

        // Grouped wrapper as-is or a list of groups: normalize through the gallery.
        if (($items['type'] ?? null) === 'grouped') {
            $gallery = MediaGallery::make($items);
            $serialized = $gallery->jsonSerialize();

            return $gallery->groups() === [] ? null : json_encode($serialized);
        }

        // Element shape decides the shape: {values: …} rows are groups (a grouped
        // repeater's items), {path: …} rows are media entries (a flat picker's state).
        $rows = array_values($items);

        if ($rows !== [] && is_array($rows[0]) && array_key_exists('values', $rows[0])) {
            $groups = [];
            foreach ($rows as $row) {
                $entries = array_values(array_filter(
                    (array) ($row['values'] ?? []),
                    fn (mixed $item): bool => is_array($item) && filled($item['path'] ?? null),
                ));

                if ($entries !== []) {
                    $groups[] = ['name' => ($row['name'] ?? null) ?: null, 'values' => $entries];
                }
            }

            return $groups === [] ? null : json_encode(['type' => 'grouped', 'groups' => $groups]);
        }

        $flat = array_values(array_filter(
            array_map(fn (mixed $item): ?array => is_string($item) && $item !== ''
                ? ['path' => $item]
                : (is_array($item) && filled($item['path'] ?? null) ? $item : null), $rows),
            fn (?array $item): bool => $item !== null,
        ));

        return $flat === [] ? null : json_encode($flat);
    }
}
