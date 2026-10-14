<?php

namespace Wsmallnews\Media\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Wsmallnews\Media\Enums\MediaType;
use Wsmallnews\Media\Support\MediaValue;
use Wsmallnews\Support\Models\SupportModel;
use Wsmallnews\Support\Support\Utils as SupportUtils;

class Media extends SupportModel
{
    protected $table = 'sn_media';

    protected $guarded = [];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    /**
     * 按相对路径查记录（path 唯一索引）
     */
    public static function findByPath(string $path): ?self
    {
        return static::query()->where('path', $path)->first();
    }

    /**
     * 值对象：业务字段的保存值
     *
     * @return array{path: string, name: string, mime: string, size: int, width: int|null, height: int|null}
     */
    public function toValue(): array
    {
        return [
            'path' => $this->path,
            'name' => $this->name,
            'mime' => $this->mime,
            'size' => (int) $this->size,
            'width' => $this->width !== null ? (int) $this->width : null,
            'height' => $this->height !== null ? (int) $this->height : null,
        ];
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /**
     * 访问 URL（渲染层统一入口，禁止模板手拼）
     */
    public function url(?int $width = null): string
    {
        return MediaValue::make($this)->url($width);
    }

    /**
     * 媒体类型（由 mime/ext 派生）
     */
    protected function mediaType(): Attribute
    {
        return Attribute::get(fn () => MediaType::fromMime($this->mime, $this->ext));
    }

    /**
     * 按逻辑目录筛选（含子孙目录的文件）
     *
     * @param  int|array<int, int>  $directoryIds
     */
    public function scopeInDirectories(Builder $query, int | array $directoryIds): Builder
    {
        return $query->whereIn('directory_id', (array) $directoryIds);
    }

    /**
     * 未归类（directory_id 为 NULL）
     */
    public function scopeUncategorized(Builder $query): Builder
    {
        return $query->whereNull('directory_id');
    }

    /**
     * 按类型筛选（File 为兜底类型，不过滤）
     */
    public function scopeOfMediaType(Builder $query, MediaType $type): Builder
    {
        return match ($type) {
            MediaType::Image => $query->where('mime', 'like', 'image/%'),
            MediaType::Video => $query->where('mime', 'like', 'video/%'),
            MediaType::Audio => $query->where('mime', 'like', 'audio/%'),
            MediaType::Pdf => $query->where(fn (Builder $q) => $q->where('mime', 'application/pdf')->orWhereIn('ext', ['pdf'])),
            MediaType::Word => $query->where(fn (Builder $q) => $q->where('mime', 'like', '%word%')->orWhere('mime', 'like', '%wordprocessingml%')->orWhereIn('ext', ['doc', 'docx', 'rtf'])),
            MediaType::Excel => $query->where(fn (Builder $q) => $q->where('mime', 'like', '%excel%')->orWhere('mime', 'like', '%spreadsheetml%')->orWhereIn('ext', ['xls', 'xlsx', 'csv'])),
            MediaType::Ppt => $query->where(fn (Builder $q) => $q->where('mime', 'like', '%presentation%')->orWhere('mime', 'like', '%powerpoint%')->orWhereIn('ext', ['ppt', 'pptx'])),
            MediaType::Zip => $query->where(fn (Builder $q) => $q->where('mime', 'like', '%zip%')->orWhereIn('ext', ['zip', 'rar', '7z'])),
            MediaType::File => $query,
        };
    }

    /**
     * 目录前缀（虚拟目录树）
     */
    public function scopeDirectory(Builder $query, string $prefix): Builder
    {
        return $query->where('path', 'like', rtrim($prefix, '/') . '/%');
    }

    /**
     * 名称 / 路径搜索
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$keyword}%")->orWhere('path', 'like', "%{$keyword}%"));
    }

    /**
     * 当前租户（无租户上下文为 NULL）
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportUtils::getTenantModel());
    }

    /**
     * 上传者
     */
    public function uploader(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 逻辑目录
     */
    public function directory(): BelongsTo
    {
        return $this->belongsTo(MediaDirectory::class);
    }
}
