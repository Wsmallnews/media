<?php

namespace Wsmallnews\Media\Filament\Forms;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Renderless;
use Throwable;
use Wsmallnews\Media\Filament\Forms\Concerns\InteractsWithMediaField;
use Wsmallnews\Media\Support\Concerns\UploadsMedia;
use Wsmallnews\Media\Support\Utils;

/**
 * 直接选文件的上传字段（字段自己存值，不经素材库选择）：
 * 点击触发系统文件选择器 → 上传 → 值对象回填字段。
 * 适用：证书等敏感/业务文件——支持 ->disk('private') 私有盘（不可直链，预览经 file 路由）。
 *
 * storageOnly()：只写盘、不写 sn_media（文件不进素材库、不参与素材库审计与去重记录），
 * 适合访客提交的表单附件这类「只属于这条业务记录」的文件；代价是引用计数没了，
 * 换图 / 删记录时业务侧要自己删文件。
 *
 * 传输与素材页 / 选择弹窗统一：同样走 **Livewire 上传**，只是接收槽不同——
 * 字段不在 Livewire 组件里，文件先落到宿主表单的 componentFileAttachments.{statePath}
 * （Filament v5 的 InteractsWithSchemas 自带 WithFileUploads + 这个槽），
 * 再由 storeUploadedMedia() 交给同一套 MediaManager 落库/落盘。
 * 好处：进度与失败都能拿到回调（$wire.upload 的 progress/error），不再依赖独立 HTTP 端点。
 *
 * 值契约、触发外观、多值排序/上限与 MediaPicker 完全一致（同用 InteractsWithMediaField），
 * 差别只有值的来源。
 */
class FilePicker extends Field
{
    use InteractsWithMediaField;
    use UploadsMedia;

    protected string $view = 'sn-media::filament.forms.file-picker';

    protected string | Closure | null $disk = null;

    /** 只写盘、不入 sn_media（文件不进素材库） */
    protected bool | Closure $storageOnly = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (FilePicker $component, $state) {
            $component->state($component->normalizeHydratedState($state));
        });
    }

    /**
     * 目标盘（null = 活动盘；敏感文件传私有盘名，如 'local' 私有盘或自建 'private' 盘）
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
     * 只写盘、不入账：文件不进素材库
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
     * 把附件槽里的临时文件落库/落盘，返回值对象与预览地址（JS 侧并入 values）。
     *
     * 由前端在 $wire.upload 完成后经 callSchemaComponentMethod 调用：
     * 临时文件此时已在 componentFileAttachments.{statePath}[.{key}]，同一轮请求里就能取到。
     * 失败原因走统一通知（uploadFailed），返回 null 让前端不做任何并入。
     *
     * 槽位名是 Filament v5 的约定（MarkdownEditor / RichText 的附件上传同款）。这里不用
     * HasFileAttachments::getUploadedFileAttachment()：它自带一套 size / mime 校验，
     * 还会把返回值收窄成 TemporaryUploadedFile，和 MediaManager 的校验与测试替身都会打架。
     * 校验只留一处（MediaManager），前端也拿不到任何可篡改的上传参数。
     *
     * $isLastOfBatch / $batchCount 由前端在「这一批的最后一个文件」上传完成时带上：
     * 字段是一个文件一次请求，服务端无法自己知道批次边界；不带就只在服务端静默成功。
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
            // 槽里没有文件：传输被拒（超 Livewire / PHP 限制）或已被消费
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
                    // 私有盘不可直链：给 null，字段侧退化成类型角标（预览走业务自己的下载路由）
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

            // 与素材页 / 选择弹窗一致的节奏：一批传完只报一次「已上传 N 个文件」
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
     * 传输层拒绝时的通知入口（前端 error 回调调它）。
     *
     * 前端不能直接 `$wire.uploadFailed()`：字段的 `$wire` 是宿主表单组件，
     * 它并不知道这个包（也不该为这个包加 trait），所以必须回到字段实例上再发通知。
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function notifyUploadRejected(string $message = ''): void
    {
        $this->uploadFailed($message);
    }

    /**
     * 传给字段 Alpine 的 props
     *
     * 上传参数（disk / storageOnly / mimes / 目录）一律不下发：它们由服务端在
     * storeUploadedMedia() 里从字段自身读取，前端连改的入口都没有。
     */
    public function getPickerProps(): array
    {
        return [
            ...$this->mediaFieldProps(),
            // callSchemaComponentMethod 要用字段 key 找到这个字段实例
            'fieldKey' => $this->getKey(),
            'uploadingLabel' => __('sn-media::media.notifications.uploading'),
            'rejectedLabel' => __('sn-media::media.notifications.upload_rejected_default'),
            // 上传前大小预检：与素材页 / 选择弹窗同一套上限（PHP ini / Livewire / 配置的最小值）
            'tooLargeLabel' => __('sn-media::media.notifications.upload_too_large', ['name' => '__NAME__', 'max' => '__MAX__']),
            'maxUploadKb' => $this->maxUploadKb(),
        ];
    }
}
