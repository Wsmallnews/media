/**
 * FilePicker 字段本地状态（Alpine 资产组件）：
 * 点击直接拉起系统文件选择器 → POST /sn-media/upload → 值对象回填（不打开素材弹窗）。
 * 与 snMediaPickerField 共享值契约与本地状态模式（wire:ignore + $wire.set 回写）。
 */
export default function snFilePickerField(props) {
    return {
        multiple: !!props.multiple,
        statePath: props.statePath || '',
        values: props.multiple ? (props.initialValues || []) : (props.initialValues?.[0] ?? null),
        urls: props.urls || {},
        uploading: false,
        error: '',

        pick() {
            this.error = '';

            if (this.$refs.input) {
                this.$refs.input.click();
            }
        },

        async onFiles(event) {
            for (const file of event.target.files) {
                // eslint-disable-next-line no-await-in-loop
                await this.uploadOne(file);
            }

            event.target.value = '';
            this.sync();
        },

        async uploadOne(file) {
            this.uploading = true;

            const form = new FormData();
            form.append('file', file);
            form.append('directory', props.directory || '');
            if (props.disk) {
                form.append('disk', props.disk);
            }

            try {
                const response = await fetch(props.uploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': props.csrfToken, Accept: 'application/json' },
                    body: form,
                });

                const json = await response.json();

                if (!response.ok) {
                    throw new Error(json.message || `Upload failed (${response.status})`);
                }

                this.urls = Object.assign({}, this.urls, { [json.value.path]: json.url });
                this.values = this.multiple ? [...(this.values || []), json.value] : json.value;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.uploading = false;
            }
        },

        isImageValue(value) {
            return !!value && String(value.mime || '').startsWith('image/');
        },

        extLabel(value) {
            const ext = String(value?.path || '').split('.').pop() || 'FILE';

            return ext.toUpperCase().slice(0, 5);
        },

        thumbSrc(value) {
            return this.urls[value?.path] || '';
        },

        valueMeta(value) {
            if (!value) {
                return '';
            }

            const dims = value.width ? `${value.width} × ${value.height}` : '—';
            return `${dims} · ${formatSize(value.size)} · ${value.mime}`;
        },

        removeSingle() {
            this.values = null;
            this.sync();
        },

        removeAt(index) {
            this.values.splice(index, 1);
            this.sync();
        },

        move(index, direction) {
            const target = index + direction;
            if (target < 0 || target >= this.values.length) {
                return;
            }

            const moved = this.values.splice(index, 1)[0];
            this.values.splice(target, 0, moved);
            this.sync();
        },

        sync() {
            this.$wire.set(this.statePath, this.values);
        },
    };
}

function formatSize(bytes) {
    if (!bytes && bytes !== 0) {
        return '—';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1048576) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    if (bytes < 1073741824) {
        return `${(bytes / 1048576).toFixed(1)} MB`;
    }
    return `${(bytes / 1073741824).toFixed(2)} GB`;
}
