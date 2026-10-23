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
     * Find a record by relative path (unique index).
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
     * Value object stored by business fields.
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
     * Public URL entry point; templates must never build URLs themselves.
     */
    public function url(?int $width = null): string
    {
        return MediaValue::make($this)->url($width);
    }

    /**
     * Media type derived from mime/ext.
     */
    protected function mediaType(): Attribute
    {
        return Attribute::get(fn () => MediaType::fromMime($this->mime, $this->ext));
    }

    /**
     * Human-readable size (1.2 MB); derived from the `size` bytes, never stored.
     */
    protected function sizeForHumans(): Attribute
    {
        return Attribute::get(fn () => Utils::formatBytes((int) $this->size));
    }

    /**
     * Filter by logical directory (files in descendants included).
     *
     * @param  int|array<int, int>  $directoryIds
     */
    public function scopeInDirectories(Builder $query, int | array $directoryIds): Builder
    {
        return $query->whereIn('directory_id', (array) $directoryIds);
    }

    /**
     * Filter by MIME pattern: `image/*` prefix or an exact value.
     *
     * Only the mime column is compared, so a family whose files sniff as
     * application/octet-stream would not be narrowed here; combine with ext if needed.
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
     * Uncategorized files (directory_id is NULL).
     */
    public function scopeUncategorized(Builder $query): Builder
    {
        return $query->whereNull('directory_id');
    }

    /**
     * Filter by type; File is the fallback type and does not filter.
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
     * Path prefix (virtual directory tree).
     */
    public function scopeDirectory(Builder $query, string $prefix): Builder
    {
        return $query->where('path', 'like', rtrim($prefix, '/') . '/%');
    }

    /**
     * Search by name or path.
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$keyword}%")->orWhere('path', 'like', "%{$keyword}%"));
    }

    /**
     * Current tenant (NULL without a tenant context).
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportUtils::getTenantModel());
    }

    /**
     * Uploader.
     */
    public function uploader(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Logical directory.
     */
    public function directory(): BelongsTo
    {
        return $this->belongsTo(MediaDirectory::class);
    }
}
