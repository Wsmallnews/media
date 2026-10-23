<?php

namespace Wsmallnews\Media\Support\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Wsmallnews\Media\Models\Media;
use Wsmallnews\Media\Models\MediaDirectory;
use Wsmallnews\Media\Support\MediaManager;

/**
 * 上传落库这一段：素材管理页、选择弹窗、FilePicker 字段三处共用。
 *
 * 三处的差别只在「传输」与「接收槽」：
 * - 管理页 / 弹窗：组件自己的 $uploads（WithFileUploads）→ updatedUploads()；
 * - 字段：宿主表单的 componentFileAttachments.{statePath} → storeUploadedMedia()。
 * 但「拿到一个 UploadedFile 之后要做什么」完全一致：同一套校验（扩展名白名单、MIME 嗅探、
 * 大小）、同一条内容寻址路径、同一个入账/只落盘分支、同一条失败通知文案。
 * 所以只把这一段抽出来，别在各处再抄一遍 try/catch 与通知标题。
 *
 * 使用方需要自己决定「用哪个目录 / 哪个盘 / 哪些 mime」——那些是业务语义，
 * 不属于本 trait（页面从浏览态推导，字段从自身选项读取）。
 */
trait UploadsMedia
{
    /**
     * 入账上传：写盘 + 建 sn_media 记录（素材库可见）。
     *
     * teamId 不传：由 MediaManager 按当前租户上下文解析，避免把租户判断散到调用方。
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
     * 只落盘、不入账（FilePicker::storageOnly()）：返回值对象数组。
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
     * 上传被拒（统一的危险通知）。
     *
     * 三个来源共用它：前端预检（JS 经 $wire 调）、Livewire 传输层拒绝（error 回调）、
     * 服务端校验抛出的 MediaException。文案只有一处定义，避免同一个错误三种说法。
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
     * 上传成功（批量上传只在全部处理完后报一次数量）。
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
     * 单文件上限（KB，0 = 不限）：取 PHP / Livewire / 包配置三者的**最小值**。
     *
     * 前端用它做上传前预检——只取包配置（20MB）是不够的：本机 PHP 往往限在 2M，
     * 前端放行后会被 PHP 直接掐断，用户看到的是"什么都没发生"。上限口径必须与真正
     * 能传上去的那个最小限制一致，三处入口（管理页 / 弹窗 / 字段）都用这一个。
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
     * php.ini 短写（2M / 512K / 1G）→ KB
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
     * Livewire 临时文件规则里的 `max:` 上限（KB）
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
