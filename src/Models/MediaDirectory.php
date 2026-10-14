<?php

namespace Wsmallnews\Media\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kalnoy\Nestedset\NodeTrait;
use Wsmallnews\Media\Exceptions\MediaException;
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

    protected $guarded = [];

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
     * 新建子目录（层级校验 ≤ 3）
     *
     * @throws MediaException
     */
    public static function createChild(string $name, ?self $parent = null, ?int $teamId = null): self
    {
        // 层级（0 基）：根 = 0；父级已达最深层级时拒绝再建子目录
        if ($parent && $parent->ancestors()->count() + 1 >= self::MAX_DEPTH) {
            throw new MediaException('Directory depth exceeds the limit of ' . self::MAX_DEPTH . ' levels.');
        }

        return static::create([
            'name' => $name,
            'team_id' => $teamId ?? $parent?->team_id,
            'parent_id' => $parent?->id,
        ]);
    }

    /**
     * 删除目录：子孙一并删除，目录内文件回落未归类（FK nullOnDelete 兜底）
     */
    public function deleteWithDescendants(): void
    {
        // 文件先回落（FK 也会兜底，显式处理保证语义清晰）
        Utils::getMediaModel()::query()
            ->whereIn('directory_id', $this->descendants()->pluck('id')->merge([$this->id]))
            ->update(['directory_id' => null]);

        $this->descendants()->delete();
        $this->delete();
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
