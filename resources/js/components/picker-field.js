/**
 * MediaPicker 字段本地状态（Alpine 资产组件）：
 * - 选中回填：监听 sn-media-selected（window 级），statePath 匹配才生效；
 * - 本地 values 为唯一渲染源（wire:ignore 区域），所有变更后 $wire.set 回写表单状态；
 * - 多值排序 = 数组顺序（上移/下移/移除均为本地重排）。
 */
export default function snMediaPickerField(props) {
    return {
        open: false,
        multiple: !!props.multiple,
        statePath: props.statePath || '',
        values: props.initialValues || (props.multiple ? [] : null),
        urls: props.urls || {},

        onSelected(detail) {
            if (!detail || detail.statePath !== this.statePath) {
                return;
            }

            const values = detail.values || [];
            this.urls = Object.assign({}, this.urls, detail.urls || {});
            this.values = this.multiple ? values : (values[0] || null);
            this.open = false;

            this.$wire.set(this.statePath, this.values);
        },

        onClosed(detail) {
            if (!detail || detail.statePath !== this.statePath) {
                return;
            }
            this.open = false;
        },

        thumbSrc(value) {
            if (!value || !value.path) {
                return '';
            }

            return this.urls[value.path] || '';
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
            this.$wire.set(this.statePath, null);
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
