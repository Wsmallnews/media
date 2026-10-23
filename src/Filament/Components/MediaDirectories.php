<?php

namespace Wsmallnews\Media\Filament\Components;

use Filament\Forms;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Wsmallnews\FilamentNestedset\Filament\Components\SidebarNestedset;
use Wsmallnews\Media\Models\MediaDirectory;
use Wsmallnews\Media\Support\Utils;

class MediaDirectories extends SidebarNestedset
{
    protected static ?string $model = MediaDirectory::class;

    protected static ?int $level = MediaDirectory::MAX_DEPTH;

    protected static ?bool $hasFormParentSelect = false;

    /**
     * @var array<int, int>
     */
    protected array $nodeCounts = [];

    public function getNestedset(): Collection
    {
        $tree = parent::getNestedset();

        $this->preloadNodeCounts($tree);

        return $tree;
    }

    protected function getRecordLabel(Model $record): HtmlString | string
    {
        return new HtmlString(
            '<span class="min-w-0 truncate">' . e($record->name) . '</span>'
            . '<span class="sn-media-tree-count">' . ($this->nodeCounts[$record->getKey()] ?? 0) . '</span>'
        );
    }

    /**
     * The sidebar is 224px wide; 11rem keeps the menu inside it, otherwise the floating
     * middleware pushes it out. The string becomes a panel class (see index.css).
     */
    protected function getRowActionsDropdownWidth(): string | Width | null
    {
        return 'sn-media-row-actions-dropdown';
    }

    protected function schema(array $arguments): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label(__('sn-media::media.directories.name'))
                ->maxLength(20)
                ->required(),
        ];
    }

    /**
     * Pre-compute cumulative counts after the tree is built.
     */
    protected function preloadNodeCounts(Collection $tree): void
    {
        $directCounts = Utils::getMediaModel()::query()
            ->scopeTenant()
            ->whereNotNull('directory_id')
            ->toBase()
            ->selectRaw('directory_id, count(*) as c')
            ->groupBy('directory_id')
            ->pluck('c', 'directory_id');

        $this->accumulateCounts($tree, $directCounts);
    }

    /**
     * Recursively accumulate counts: a node's total is its own files plus all descendants'.
     *
     * @param  Collection<int, Model>  $nodes
     * @param  Collection<int, int>  $directCounts
     */
    protected function accumulateCounts(Collection $nodes, Collection $directCounts): int
    {
        $total = 0;

        foreach ($nodes as $node) {
            $self = (int) ($directCounts[$node->getKey()] ?? 0);
            $cumulative = $self + $this->accumulateCounts($node->children, $directCounts);

            $this->nodeCounts[$node->getKey()] = $cumulative;
            $total += $cumulative;
        }

        return $total;
    }
}
