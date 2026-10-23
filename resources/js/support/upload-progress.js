/**
 * Upload progress / failure state, shared by the library page, the picker modal and the
 * FilePicker field.
 *
 * Never listen for `livewire-upload-*` events: only wire:model inputs dispatch them.
 * Programmatic uploads get a progress callback from `$wire.uploadMultiple()` instead.
 */
export function uploadProgress(defaults = {}) {
    return {
        uploading: false,
        progress: 0,
        uploadFilename: '',
        uploadingLabel: defaults.label || '',
        rejectedLabel: defaults.rejectedLabel || '',
        /** Per-file limit in KB (0 = unlimited), sent down by PHP's maxUploadKb(). */
        maxUploadKb: Number(defaults.maxUploadKb) || 0,
        /** "__NAME__ exceeds the __MAX__ MB limit" copy. */
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
         * Failures are announced by the server (one source of copy); success only resets
         * local state. The field overrides notifyUploadRejected because its `$wire` is the
         * host form, which has no uploadFailed().
         */
        failUpload(message = '') {
            this.uploadFinished();
            this.notifyUploadRejected(message || this.rejectedLabel);
        },

        notifyUploadRejected(message) {
            this.$wire.uploadFailed(message);
        },

        /**
         * Reject oversized files before uploading (no doomed request, no half upload).
         *
         * The limit must be the smallest of PHP upload_max_filesize / Livewire rules /
         * package config — that is what maxUploadKb() returns. Never read config here.
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
