<?php

namespace Wsmallnews\Media\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kalnoy\Nestedset\NodeTrait;
use Wsmallnews\Media\Support\Utils;
use Wsmallnews\Support\Models\SupportModel;
use Wsmallnews\Support\Support\Utils as SupportUtils;

/**
 * 素材逻辑目录（嵌套集树，层级最多 3 级）。
 *
 * 目录是纯逻辑归类：文件物理路径不含目录（sn/{Ymd}/{hash}.ext），
 * 归属经 media.directory_id；移动文件 / 重命名目录均不改物理路径。
 */
class MediaDirectory extends SupportModel
{
    use NodeTrait;

    /** 目录最大层级（根 = 第 1 级） */
    public const MAX_DEPTH = 3;

    protected $table = 'sn_media_directories';

    /**
     * 多租户 scope（嵌套集 scoped 特性：同租户一棵树）
     */
    public function getScopeAttributes(): array
    {
        $scopes = [];
        if (SupportUtils::isTenancyEnabled()) {        // 多租户 时，自动增加 租户相关参数
            $scopes[] = 'team_id';
        }

        return $scopes;
    }


    /**
     * 含自身的子孙 id 列表（一次查询）
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
