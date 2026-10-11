<?php

namespace Wsmallnews\Media\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Wsmallnews\Support\Enums\Traits\EnumHelper;

enum MediaType: string implements HasColor, HasIcon, HasLabel
{
    use EnumHelper;

    case Image = 'image';

    case Video = 'video';

    case Audio = 'audio';

    case Pdf = 'pdf';

    case Word = 'word';

    case Excel = 'excel';

    case Ppt = 'ppt';

    case Zip = 'zip';

    case File = 'file';

    /**
     * 从 MIME（辅以扩展名兜底）判定媒体类型。
     */
    public static function fromMime(?string $mime, ?string $ext = null): self
    {
        $mime = $mime ?? '';
        $ext = strtolower($ext ?? '');

        return match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            str_starts_with($mime, 'video/') => self::Video,
            str_starts_with($mime, 'audio/') => self::Audio,
            $mime === 'application/pdf' => self::Pdf,
            str_contains($mime, 'word') || str_contains($mime, 'wordprocessingml') || in_array($ext, ['doc', 'docx', 'rtf']) => self::Word,
            str_contains($mime, 'excel') || str_contains($mime, 'spreadsheetml') || in_array($ext, ['xls', 'xlsx', 'csv']) => self::Excel,
            str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint') || in_array($ext, ['ppt', 'pptx']) => self::Ppt,
            str_contains($mime, 'zip') || in_array($ext, ['zip', 'rar', '7z']) => self::Zip,
            default => self::File,
        };
    }

    /**
     * 该类型作为列表筛选项时对应的状态（File 为兜底类型，不参与过滤）。
     */
    public function isFilterable(): bool
    {
        return $this !== self::File;
    }

    public function getLabel(): string | Htmlable | null
    {
        return match ($this) {
            self::Image => __('sn-media::media.types.image'),
            self::Video => __('sn-media::media.types.video'),
            self::Audio => __('sn-media::media.types.audio'),
            self::Pdf => __('sn-media::media.types.pdf'),
            self::Word => __('sn-media::media.types.word'),
            self::Excel => __('sn-media::media.types.excel'),
            self::Ppt => __('sn-media::media.types.ppt'),
            self::Zip => __('sn-media::media.types.zip'),
            self::File => __('sn-media::media.types.file'),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Image => 'primary',
            self::Video => 'purple',
            self::Audio => 'pink',
            self::Pdf => 'red',
            self::Word => 'blue',
            self::Excel => 'green',
            self::Ppt => 'orange',
            self::Zip => 'amber',
            self::File => 'gray',
        };
    }

    public function getIcon(): string | BackedEnum | Htmlable | null
    {
        return match ($this) {
            self::Image => Heroicon::OutlinedPhoto,
            self::Video => Heroicon::OutlinedVideoCamera,
            self::Audio => Heroicon::OutlinedSpeakerWave,
            self::Pdf => Heroicon::OutlinedDocumentText,
            self::Word => Heroicon::OutlinedDocument,
            self::Excel => Heroicon::OutlinedTableCells,
            self::Ppt => Heroicon::OutlinedPresentationChartLine,
            self::Zip => Heroicon::OutlinedArchiveBox,
            self::File => Heroicon::OutlinedDocumentDuplicate,
        };
    }
}
