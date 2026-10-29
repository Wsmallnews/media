<?php

namespace Wsmallnews\Media\Filament\Forms;

use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Wsmallnews\Media\Support\MediaGallery;
use Wsmallnews\Media\Support\MediaValue;
use Wsmallnews\Support\Filament\Forms\FormComponents as SupportFormComponents;

/**
 * Reusable form snippets for media fields, in the support-package factory style:
 * every method returns the field itself so callers keep chaining directly —
 * `FormComponents::flatMediaGallery('images')->required()`.
 */
class FormComponents
{
    /**
     * Flat gallery editor: the multi picker bound to the field itself.
     * Defaults: max 20 entries (call ->maxCount() to override).
     *
     * @param  string  $field  the model's collection media field (MediaCollectionCast)
     * @param  string|null  $label  field label, defaults to the gallery translation
     */
    public static function flatMediaGallery(string $field, ?string $label = null): MediaPicker
    {
        return MediaPicker::make($field)
            ->label($label ?? __('sn-media::media.gallery.label'))
            ->multiple()
            ->maxCount(20);
    }

    /**
     * Grouped gallery editor: a repeater of named value groups bound to the
     * field itself — no switcher, no mirror carrier, the state path is the column.
     * Returns the Repeater so callers keep chaining (->maxItems(), ->required()…).
     * Inner fields stay label-light by design: the group name keeps its label,
     * the per-group picker goes without one (the group name says what this is).
     *
     * State translation stays shape-based end to end: hydrate unpacks the stored
     * {type: grouped, groups} wrapper into repeater items ({name, values} rows);
     * on save the items dehydrate straight onto the field, and the cast stores
     * them back as the grouped wrapper (element shape: {values} rows = groups).
     * A flat legacy value is folded into one default group on hydrate, so old
     * records open cleanly in the grouped editor.
     *
     * @param  string  $field  the model's collection media field (MediaCollectionCast)
     * @param  string|null  $label  repeater label, defaults to the gallery translation
     * @param  Closure|null  $configureGroupPicker  fn (MediaPicker $picker) per-group inner picker
     */
    public static function groupedMediaGallery(string $field, ?string $label = null, ?Closure $configureGroupPicker = null): Repeater
    {
        return SupportFormComponents::cardsRepeater($field, 'name')
            ->label($label ?? __('sn-media::media.gallery.label'))
            ->default([])
            ->schema([
                TextInput::make('name')
                    ->label(__('sn-media::media.gallery.group_name'))
                    ->live(onBlur: true),
                static::groupPicker($configureGroupPicker),
            ])
            ->columns(1)
            ->collapsible()
            ->addActionLabel(__('sn-media::media.gallery.add_group'))
            ->reorderable()
            ->afterStateHydrated(function (Get $get, Set $set, Component $livewire, ?Model $record) use ($field): void {
                // Edit: unpack the stored wrapper into repeater items. The
                // hydrate hook cannot read sibling state, so the record's raw
                // JSON is the only authoritative source here.
                $raw = $record?->getAttributes()[$field] ?? null;
                if (blank($raw)) {
                    return;
                }

                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

                if (! is_array($decoded)) {
                    return;
                }

                $items = static::groupItemsOf(MediaGallery::make($decoded));

                if ($decoded === $items) {
                    return; // already row shape (Create form fill / resubmit)
                }

                $set($field, $items === [] ? null : $items);
            });
    }

    /**
     * The per-group inner picker (repeater item key "values" matches the stored
     * groups[].values contract); visually label-less inside the group card, with
     * the group-values translation as its accessible name.
     */
    protected static function groupPicker(?Closure $configure): MediaPicker
    {
        $picker = MediaPicker::make('values')
            ->label(__('sn-media::media.gallery.group_values'))
            ->hiddenLabel()
            ->multiple()
            ->maxCount(20);

        if ($configure) {
            $configure($picker);
        }

        return $picker;
    }

    /**
     * Repeater items mirroring a gallery's groups. A flat gallery folds into one
     * default group so legacy values open cleanly in the grouped editor.
     *
     * @return array<int, array{name: string|null, values: array<int, array<string, mixed>>}>
     */
    protected static function groupItemsOf(MediaGallery $gallery): array
    {
        $groups = $gallery->groups();

        if ($groups === [] || (! $gallery->isGrouped() && $gallery->first())) {
            $flat = array_map(fn (MediaValue $value): array => (array) $value->toArray(), $gallery->all());

            return $flat === [] ? [] : [['name' => __('sn-media::media.gallery.default_group'), 'values' => $flat]];
        }

        return array_map(fn ($group): array => [
            'name' => $group->name(),
            'values' => array_map(fn (MediaValue $value): array => (array) $value->toArray(), $group->values()),
        ], $groups);
    }
}
