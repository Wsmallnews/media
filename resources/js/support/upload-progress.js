/**
 * 上传进度/失败状态（三个入口共用）：素材管理页、选择弹窗、FilePicker 字段。
 *
 * 为什么不再监听 `livewire-upload-*` DOM 事件：那四个事件**只由 wire:model 绑定的
 * file input 派发**（Livewire 的 handleFileUpload），而程序化上传 `$wire.upload()` /
 * `$wire.uploadMultiple()` 的回调默认全是 no-op、不派发任何事件。所以进度只能来自
 * 调用时传进去的 progress 回调（Livewire 内部就是 XMLHttpRequest + upload.onprogress，
 * 会给回调塞一个原生 ProgressEvent，并把百分比放在 detail.progress）。
 *
 * 用法：在 Alpine 对象里 spread 进来，然后
 *   $wire.uploadMultiple('uploads', files, finish, error, progress)
 * 三个回调分别接到 uploadFinished / failUpload / uploadProgressed。
 */
export function uploadProgress(defaults = {}) {
    return {
        uploading: false,
        progress: 0,
        uploadFilename: '',
        uploadingLabel: defaults.label || '',
        rejectedLabel: defaults.rejectedLabel || '',
        /** 单文件上限（KB，0 = 不限）：由 PHP 侧的 maxUploadKb() 下发，取 PHP/Livewire/配置最小值 */
        maxUploadKb: Number(defaults.maxUploadKb) || 0,
        /** 「__NAME__ 超过单文件上限 __MAX__ MB」文案 */
        tooLargeLabel: defaults.tooLargeLabel || '',

        uploadStarted(filename = '') {
            this.uploading = true;
            this.progress = 0;
            this.uploadFilename = filename || '';
        },

        /**
         * @param {ProgressEvent & { detail?: { progress?: number } }} event
         */
        uploadProgressed(event) {
            const percent = event?.detail?.progress
                ?? (event?.total ? Math.round((event.loaded * 100) / event.total) : 0);

            this.uploading = true;
            this.progress = Math.max(0, Math.min(100, Number(percent) || 0));
        },

        uploadFinished() {
            this.uploading = false;
            this.progress = 0;
            this.uploadFilename = '';
        },

        /**
         * 失败统一走服务端通知（文案只有一处定义），成功后只重置本地状态。
         *
         * 通知由 notifyUploadRejected 转发：页面/弹窗本身就是 Livewire 组件，直接调
         * $wire.uploadFailed；字段的 $wire 是宿主表单（没有这个方法），要改成调字段自己的方法。
         */
        failUpload(message = '') {
            this.uploadFinished();
            this.notifyUploadRejected(message || this.rejectedLabel);
        },

        notifyUploadRejected(message) {
            this.$wire.uploadFailed(message);
        },

        /**
         * 上传前按大小预筛，超限的逐个提示并剔除 —— 别等传到一半/传完才被服务端 422。
         *
         * 上限必须与「真正能传上去的那个最小限制」一致（PHP upload_max_filesize /
         * Livewire 规则 / 包配置的最小值），所以由 PHP 的 maxUploadKb() 下发，
         * 前端不要再自己读配置。
         *
         * @param {File[]} files
         * @returns {File[]}
         */
        filterOversized(files) {
            if (! this.maxUploadKb) {
                return files;
            }

            return files.filter((file) => {
                if (file.size / 1024 <= this.maxUploadKb) {
                    return true;
                }

                this.failUpload(
                    this.tooLargeLabel
                        .replace('__NAME__', file.name)
                        .replace('__MAX__', Math.round(this.maxUploadKb / 1024)),
                );

                return false;
            });
        },

        uploadLabel() {
            return this.uploadingLabel;
        },
    };
}
