<?php

namespace Wsmallnews\Media\Filament\Forms;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Renderless;
use Throwable;
use Wsmallnews\Media\Filament\Concerns\UploadsMedia;
use Wsmallnews\Media\Filament\Forms\Concerns\InteractsWithMediaField;
use Wsmallnews\Media\Support\Utils;

/**
 * Upload field that picks files directly: click, system file dialog, upload, value object.
 * The file arrives in the host form's componentFileAttachments slot, then goes to MediaManager.
 */
class FilePicker extends Field
{
    use InteractsWithMediaField;
    use UploadsMedia;

    protected string $view = 'sn-media::filament.forms.file-picker';

    protected string | Closure | null $disk = null;

    /** Write to disk only, no sn_media record */
    protected bool | Closure $storageOnly = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (FilePicker $component, $state) {
            $component->state($component->normalizeHydratedState($state));
        });
    }

    /**
     * Target disk (null = active disk); pass a private disk name for sensitive files.
     */
    public function disk(string | Closure | null $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function getDisk(): ?string
    {
        $disk = $this->evaluate($this->disk);

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    /**
     * Disk only: the file never enters the media library.
     */
    public function storageOnly(bool | Closure $condition = true): static
    {
        $this->storageOnly = $condition;

        return $this;
    }

    public function isStorageOnly(): bool
    {
        return (bool) $this->evaluate($this->storageOnly);
    }

    /**
     * Move the attachment into storage and return its value plus preview URL; JS calls this
     * after $wire.upload. Validation stays in MediaManager (HasFileAttachments would add its
     * own). $isLastOfBatch comes from JS: one request per file, so the server cannot know.
     *
     * @return array{value: array<string, mixed>, url: ?string}|null
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function storeUploadedMedia(string $attachment = '', bool $isLastOfBatch = false, int $batchCount = 0): ?array
    {
        $slot = 'componentFileAttachments.' . $this->getStatePath() . ($attachment !== '' ? '.' . $attachment : '');

        $file = data_get($this->getLivewire(), $slot);

        if (! $file instanceof UploadedFile) {
            // Empty slot: the transfer was rejected (size limits) or already consumed.
            $this->uploadFailed();

            return null;
        }

        try {
            if ($this->isStorageOnly()) {
                $value = $this->storeMediaFile(
                    $file,
                    disk: $this->getDisk(),
                    fileDirectory: $this->getDirectory(),
                    acceptedMimes: $this->getAcceptedMimes() ?: null,
                );

                $disk = $this->getDisk() ?: Utils::getDisk();

                return [
                    'value' => $value,
                    // Private disks have no direct URL: return null and let the field show a type badge.
                    'url' => Utils::getDiskVisibility($disk) === 'public'
                        ? Storage::disk($disk)->url($value['path'])
                        : null,
                ];
            }

            $media = $this->uploadMediaFile(
                $file,
                disk: $this->getDisk(),
                fileDirectory: $this->getDirectory(),
                acceptedMimes: $this->getAcceptedMimes() ?: null,
            );

            // Same rhythm as the media page: report once per batch.
            if ($isLastOfBatch) {
                $this->notifyMediaUploaded($batchCount);
            }

            return ['value' => $media->toValue(), 'url' => $media->url(200)];
        } catch (Throwable $e) {
            $this->uploadFailed($e->getMessage());

            return null;
        }
    }

    /**
     * Notification entry point for the JS error callback: the field's $wire is the host form,
     * which knows nothing about this package, so the call must come back to the field.
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function notifyUploadRejected(string $message = ''): void
    {
        $this->uploadFailed($message);
    }

    /**
     * Props passed to the field's Alpine component. Upload options stay server-side: the field
     * reads them in storeUploadedMedia(), so the client has nothing to tamper with.
     */
    public function getPickerProps(): array
    {
        return [
            ...$this->mediaFieldProps(),
            // callSchemaComponentMethod looks the field instance up by key
            'fieldKey' => $this->getKey(),
            'uploadingLabel' => __('sn-media::media.notifications.uploading'),
            'rejectedLabel' => __('sn-media::media.notifications.upload_rejected_default'),
            // Pre-check with the same limit as the media page (smallest of PHP / Livewire / config)
            'tooLargeLabel' => __('sn-media::media.notifications.upload_too_large', ['name' => '__NAME__', 'max' => '__MAX__']),
            'maxUploadKb' => $this->maxUploadKb(),
        ];
    }
}
