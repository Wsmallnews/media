/**
 * 素材管理页本地交互态（Alpine 资产组件，经 Filament 资产管线注册）：
 * 累积多选（+ 全选当页）、拖拽上传、进度条（livewire-upload-* 连字符事件）、
 * 上传前客户端大小预检（服务端三层最小值经 maxUploadKb 下发）。
 * 业务操作（移动/删除/预览）走 Filament Action（mountAction）或 wire:click，本地只承载纯展示态。
 */
export default function snMediaManager(props = {}) {
    return {
        selected: [],
        dragOver: false,
        uploading: false,
        progress: 0,
        uploadFilename: '',
        uploadingLabel: props.uploadingLabel || 'Uploading…',
        rejectedLabel: props.rejectedLabel || '',
        tooLargeLabel: props.tooLargeLabel || '',
        maxUploadKb: Number(props.maxUploadKb) || 0,

        init() {
            // Livewire 3 文件上传事件（连字符，dispatchEvent + bubbles 到 document）
            document.addEventListener('livewire-upload-start', () => {
                this.uploading = true;
                this.progress = 0;
                this.uploadFilename = '';
            });
            document.addEventListener('livewire-upload-progress', (event) => {
                this.uploading = true;
                this.progress = event.detail?.progress ?? 0;
                if (event.detail?.filename) {
                    this.uploadFilename = event.detail.filename;
                }
            });
            document.addEventListener('livewire-upload-finish', () => {
                this.uploading = false;
                this.progress = 0;
                this.uploadFilename = '';
            });
            document.addEventListener('livewire-upload-error', () => {
                this.uploading = false;
                this.progress = 0;
                this.uploadFilename = '';
                // 服务端拒绝（校验规则 / upload_max_filesize 等）：原因经 uploadFailed 通知
                this.$wire.uploadFailed(this.rejectedLabel);
            });
        },

        uploadLabel() {
            return this.progress > 0 ? `${this.uploadingLabel} ${this.progress}%` : this.uploadingLabel;
        },

        // ===== 上传前预检 + 手动提交（绕过 wire:model 的自动上传，超限文件在客户端拦截） =====

        /**
         * @returns {File[]} 通过大小预检的文件；超限的逐个通知并剔除
         */
        filterOversized(files) {
            if (!this.maxUploadKb) {
                return files;
            }

            return files.filter((file) => {
                if (file.size / 1024 <= this.maxUploadKb) {
                    return true;
                }

                this.$wire.uploadFailed(
                    this.tooLargeLabel.replace('__NAME__', file.name).replace('__MAX__', Math.round(this.maxUploadKb / 1024)),
                );

                return false;
            });
        },

        onFilePick(event) {
            const picked = Array.from(event.target.files || []);
            const files = this.filterOversized(picked);
            // 重置 input，保证同名文件可重复选择
            event.target.value = '';

            if (files.length > 0) {
                this.$wire.uploadMultiple('uploads', files);
            }
        },

        // ===== 多选（本地状态，不发请求；触发批量操作时一次性传给 Filament Action） =====
        toggle(id) {
            this.selected = this.selected.includes(id)
                ? this.selected.filter((i) => i !== id)
                : [...this.selected, id];
        },

        isSelected(id) {
            return this.selected.includes(id);
        },

        allPageSelected() {
            const raw = this.$root.querySelector('[data-page-ids]')?.dataset.pageIds;
            if (!raw) return false;
            try {
                const ids = JSON.parse(raw).map(Number).filter(Number.isInteger);
                return ids.length > 0 && ids.every((id) => this.selected.includes(id));
            } catch {
                return false;
            }
        },

        selectAllPage() {
            const raw = this.$root.querySelector('[data-page-ids]')?.dataset.pageIds;
            if (!raw) return;
            let ids = [];
            try {
                ids = JSON.parse(raw).map(Number).filter(Number.isInteger);
            } catch {
                ids = [];
            }
            const all = ids.every((id) => this.selected.includes(id));
            this.selected = all
                ? this.selected.filter((id) => !ids.includes(id))
                : Array.from(new Set([...this.selected, ...ids]));
        },

        // ===== 拖拽上传 =====
        onDrop(event) {
            this.dragOver = false;
            const dropped = Array.from(event.dataTransfer?.files || []);
            const files = this.filterOversized(dropped);

            if (files.length > 0) {
                this.$wire.uploadMultiple('uploads', files);
            }
        },
    };
}
