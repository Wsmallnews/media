/**
 * FilePicker 字段本地状态（Alpine 资产组件）：
 * 点击拉起系统文件选择器 → 上传到宿主表单的附件槽 → 服务端换值对象回填（不打开素材弹窗）。
 *
 * 与 snMediaPickerField 的差别只有「值从哪来」：这里上传后由字段方法返回值对象；
 * 触发外观、拖拽排序、上限、放大预览、回写时机完全一致（同一份模板 + 同一份辅助方法）。
 * 上传进度/失败与素材页、选择弹窗共用 support/upload-progress.js。
 */
import { extLabel, isImageValue, itemMeta, valueMeta } from '../support/media-value.js';
import { uploadProgress } from '../support/upload-progress.js';

export default function snFilePickerField(props) {
    return {
        ...uploadProgress({
            label: props.uploadingLabel,
            rejectedLabel: props.rejectedLabel,
            tooLargeLabel: props.tooLargeLabel,
            maxUploadKb: props.maxUploadKb,
        }),

        multiple: !!props.multiple,
        statePath: props.statePath || '',
        /** 服务端定位字段实例用（callSchemaComponentMethod 的第一个参数） */
        fieldKey: props.fieldKey || '',
        // 预览框/卡片宽高比（模板 ::style 依赖，缺了会抛 Alpine Expression Error）
        ratio: Number(props.ratio) || 1,
        values: props.multiple ? (props.initialValues || []) : (props.initialValues?.[0] ?? null),
        urls: props.urls || {},
        // path-only 契约：渲染仍用值对象，写回表单状态时只留 path
        storePath: !!props.storePath,
        // 多值上限（0 = 不限）：到达后「+」触发器禁用，超出的文件不再上传
        maxCount: Number(props.maxCount) || 0,
        dragIndex: null,
        previewing: null,

        isImageValue,
        extLabel,
        valueMeta,
        itemMeta,

        openPreview(value) {
            this.previewing = value || null;
        },

        closePreview() {
            this.previewing = null;
        },

        isFull() {
            return this.multiple && this.maxCount > 0 && (this.values?.length || 0) >= this.maxCount;
        },

        dragStart(index) {
            this.dragIndex = index;
        },

        dragOver(index) {
            if (this.dragIndex === null || this.dragIndex === index) {
                return;
            }

            const moved = this.values.splice(this.dragIndex, 1)[0];
            this.values.splice(index, 0, moved);
            this.dragIndex = index;
        },

        dragEnd() {
            if (this.dragIndex === null) {
                return;
            }

            this.dragIndex = null;
            this.sync();
        },

        openPicker() {
            this.$refs.input?.click();
        },

        async onFiles(event) {
            const picked = this.multiple
                ? Array.from(event.target.files || [])
                : [event.target.files?.[0]].filter(Boolean);

            event.target.value = '';

            // 先按大小筛一遍：超限的直接提示，不发请求（与素材页 / 弹窗同一套上限）
            const files = this.filterOversized(this.limitToRoom(picked));
            let uploaded = 0;

            for (const [index, file] of files.entries()) {
                const isLast = index === files.length - 1;
                // 成功数带下去 = 最后一个文件落库时由服务端一次性发「已上传 N 个文件」
                // eslint-disable-next-line no-await-in-loop
                const stored = await this.uploadOne(file, isLast, uploaded + 1);

                if (stored) {
                    uploaded++;
                }
            }

            this.sync();
        },

        /**
         * 多值上限：一次拖进 10 个但只剩 2 个位置时，多传的文件直接丢弃（不静默变成超额数组）。
         */
        limitToRoom(files) {
            if (!this.multiple || this.maxCount <= 0) {
                return files;
            }

            const room = this.maxCount - (this.values?.length || 0);

            return room > 0 ? files.slice(0, room) : [];
        },

        /**
         * 失败通知必须让**字段自己**发：这里的 $wire 是宿主表单组件，它没有 uploadFailed。
         */
        notifyUploadRejected(message) {
            this.$wire.callSchemaComponentMethod(this.fieldKey, 'notifyUploadRejected', [message || '']);
        },

        /**
         * 单个文件：先落到宿主表单的附件槽（componentFileAttachments.{statePath}[.{key}]），
         * 再由服务端换成值对象返回 —— 与素材页 / 选择弹窗走同一个 MediaManager。
         */
        /**
         * @param {File} file
         * @param {boolean} isLastOfBatch 这一批的最后一个：落库后由服务端发一次成功通知
         * @param {number} batchCount 这一批到目前为止的成功数（含当前这个）
         */
        async uploadOne(file, isLastOfBatch = true, batchCount = 1) {
            // 多值时分槽：同一字段连续上传也不会互相覆盖（key 只需在本次会话内唯一）
            const attachment = this.multiple
                ? `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`
                : '';
            const slot = `componentFileAttachments.${this.statePath}${attachment ? '.' + attachment : ''}`;

            this.uploadStarted(file.name);

            const uploaded = await new Promise((resolve) => {
                this.$wire.upload(
                    slot,
                    file,
                    () => resolve(true),
                    () => resolve(false),
                    (event) => this.uploadProgressed(event),
                );
            });

            if (! uploaded) {
                // 传输层被拒（超 PHP / Livewire 上限等）：原因由统一通知给出
                this.failUpload();

                return;
            }

            const stored = await this.$wire.callSchemaComponentMethod(this.fieldKey, 'storeUploadedMedia', [
                attachment,
                isLastOfBatch,
                batchCount,
            ]);

            this.uploadFinished();

            if (! stored?.value?.path) {
                // 服务端校验失败已在 storeUploadedMedia() 里发过通知
                return false;
            }

            this.urls = Object.assign({}, this.urls, { [stored.value.path]: stored.url });
            this.values = this.multiple ? [...(this.values || []), stored.value] : stored.value;

            return true;
        },

        thumbSrc(value) {
            if (!value || !value.path) {
                return '';
            }

            return this.urls[value.path] || '';
        },

        removeSingle() {
            this.values = null;
            this.sync();
        },

        removeAt(index) {
            this.values.splice(index, 1);
            this.sync();
        },

        /**
         * 写回表单状态：默认写完整值对象；storePath 模式只写 path。
         */
        statePayload() {
            if (!this.storePath) {
                return this.values;
            }

            return this.multiple
                ? (this.values || []).map((v) => v?.path).filter(Boolean)
                : (this.values?.path ?? null);
        },

        /**
         * 第三个参数 false = 不立即发请求：上传接口已经往返一次，状态跟着下一次请求
         * （保存 / 校验）提上去就够了，没必要为了回写再发一趟。
         */
        sync() {
            this.$wire.set(this.statePath, this.statePayload(), false);
        },
    };
}
