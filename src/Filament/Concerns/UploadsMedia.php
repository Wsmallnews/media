<?php

namespace Wsmallnews\Media\Filament\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Wsmallnews\Media\Models\Media;
use Wsmallnews\Media\Models\MediaDirectory;
use Wsmallnews\Media\Support\MediaManager;

/**
 * Upload handling shared by the media page, the picker modal and FilePicker: one validation
 * path, one content-addressed path, one failure notification.
 */
trait UploadsMedia
{
    /**
     * Write to disk and create the sn_media record (visible in the library).
     *
     * The tenant is resolved by MediaManager, never by the caller.
     */
    protected function uploadMediaFile(
        UploadedFile $file,
        MediaDirectory | int | null $directory = null,
        ?string $disk = null,
        ?string $fileDirectory = null,
        ?array $acceptedMimes = null,
    ): Media {
        return app(MediaManager::class)->upload(
            $file,
            directory: $directory,
            disk: $disk,
            fileDirectory: $fileDirectory,
            acceptedMimes: $acceptedMimes,
        );
    }

    /**
     * Write to disk only, without a record (FilePicker::storageOnly()).
     *
     * @return array<string, mixed>
     */
    protected function storeMediaFile(
        UploadedFile $file,
        ?string $disk = null,
        ?string $fileDirectory = null,
        ?array $acceptedMimes = null,
    ): array {
        return app(MediaManager::class)->store(
            $file,
            disk: $disk,
            fileDirectory: $fileDirectory,
            acceptedMimes: $acceptedMimes,
        );
    }

    /**
     * Single rejection notification, shared by the JS pre-check, the transport error callback
     * and server-side validation.
     */
    public function uploadFailed(string $message = ''): void
    {
        Notification::make()
            ->title(__('sn-media::media.notifications.upload_rejected', [
                'reason' => $message !== '' ? $message : __('sn-media::media.notifications.upload_rejected_default'),
            ]))
            ->danger()
            ->send();
    }

    /**
     * Success notification; a batch reports once, after the last file.
     */
    protected function notifyMediaUploaded(int $count): void
    {
        if ($count <= 0) {
            return;
        }

        Notification::make()
            ->title(__('sn-media::media.notifications.uploaded', ['count' => $count]))
            ->success()
            ->send();
    }

    /**
     * Per-file limit in KB (0 = unlimited): the smallest of PHP, Livewire and the package config.
     *
     * The pre-check must match what uploads can actually pass, not just the 20MB package value.
     */
    protected function maxUploadKb(): int
    {
        $candidates = array_filter([
            $this->iniSizeToKb(ini_get('upload_max_filesize')),
            $this->iniSizeToKb(ini_get('post_max_size')),
            (int) config('sn-media.max_size', 0),
            $this->livewireMaxUploadKb(),
        ]);

        return $candidates === [] ? 0 : (int) min($candidates);
    }

    /**
     * php.ini shorthand (2M / 512K / 1G) to KB.
     */
    private function iniSizeToKb(string | false $value): int
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (float) $value;

        return max(0, (int) round(match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024,
            'm' => $number * 1024,
            'k' => $number,
            default => $number / 1024,
        }));
    }

    /**
     * `max:` limit from Livewire's temporary upload rules, in KB.
     */
    private function livewireMaxUploadKb(): int
    {
        foreach ((array) config('livewire.temporary_file_upload.rules', []) as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'max:')) {
                return (int) substr($rule, 4);
            }
        }

        return 0;
    }
}
