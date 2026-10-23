<?php

namespace Wsmallnews\Media\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kalnoy\Nestedset\NodeTrait;
use Wsmallnews\Media\Support\Utils;
use Wsmallnews\Support\Models\SupportModel;
use Wsmallnews\Support\Support\Utils as SupportUtils;

/**
 * Logical media directory (nested set, max 3 levels). Physical paths never contain it:
 * ownership lives in media.directory_id, so moving files or renaming never touches the path.
 */
class MediaDirectory extends SupportModel
{
    use NodeTrait;

    /** Max depth (root = level 1) */
    public const MAX_DEPTH = 3;

    protected $table = 'sn_media_directories';

    /**
     * Multi-tenant scope: one tree per tenant.
     */
    public function getScopeAttributes(): array
    {
        $scopes = [];
        if (SupportUtils::isTenancyEnabled()) {
            $scopes[] = 'team_id';
        }

        return $scopes;
    }

    /**
     * Ids of this node and all descendants, in one query.
     *
     * @return array<int, int>
     */
    public function selfAndDescendantIds(): array
    {
        return $this->descendants()->pluck('id')
            ->merge([$this->id])
            ->all();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportUtils::getTenantModel());
    }

    public function medias(): HasMany
    {
        return $this->hasMany(Utils::getMediaModel());
    }
}
