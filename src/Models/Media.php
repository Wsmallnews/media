<?php

namespace Wsmallnews\Media\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Wsmallnews\Media\Enums\MediaType;
use Wsmallnews\Media\Support\MediaValue;
use Wsmallnews\Media\Support\Utils;
use Wsmallnews\Support\Models\SupportModel;
use Wsmallnews\Support\Support\Utils as SupportUtils;

class Media extends SupportModel
{
    protected $table = 'sn_media';

    protected $casts = [];

    /**
     * 按相对路径查记录（path 唯一索引）
     */
    public static function findByPath(string $path, ?int $teamId = null): ?self
    {
        $query = static::query();

        if ($teamId === null) {
            $query->scopeTenant();
        } else {
            $query->where('team_id', $teamId);
        }

        return $query->where('path', $path)->first();
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
     * 人类可读的文件大小（1.2 MB）。
     *
     * 展示用的派生值，不是存储字段：`size` 本身仍是字节数（值对象 toValue() 也照旧给 int），
     * 只是把「字节 → 可读」这一步从页面组件挪到模型上，任何拿到模型的地方（其他包、
     * 导出、通知）都能直接用，不必依赖 media 的 Filament 组件，也不必自己再写一份换算。
     */
    protected function sizeForHumans(): Attribute
    {
        return Attribute::get(fn () => Utils::formatBytes((int) $this->size));
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
     * 按 mime 模式筛选（字段级约束：image/* 这类前缀，或 application/pdf 这类精确值）
     *
     * 只比对 mime 列：像 zip/docx 这类家族里存在 application/octet-stream 兜底，
     * 若把 octet-stream 写进约束，这一层等于不过滤——真要按家族收窄，调用处
     * 应该同时给 ext 条件（本 scope 不做，保持语义单一）。
     *
     * @param  array<int, string>  $patterns
     */
    public function scopeOfMimePatterns(Builder $query, array $patterns): Builder
    {
        return $query->where(function (Builder $q) use ($patterns) {
            foreach ($patterns as $pattern) {
                if (str_ends_with($pattern, '/*')) {
                    $q->orWhere('mime', 'like', substr($pattern, 0, -1) . '%');
                } else {
                    $q->orWhere('mime', $pattern);
                }
            }
        });
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
