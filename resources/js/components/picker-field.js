/**
 * MediaPicker 字段本地状态（Alpine 资产组件）：
 * - 选中回填：监听 sn-media-selected（window 级），statePath 匹配才生效；
 * - 本地 values 为唯一渲染源（wire:ignore 区域），变更后按需回写表单状态；
 * - 多值排序 = 数组顺序（拖动换位，松手回写）。
 *
 * 触发外观、拖拽、预览、上限这套方法与 snFilePickerField 共用同一份展示辅助
 * （support/media-value.js），两个字段的模板也共用 views/components/filament/forms 下的四个组件。
 */
import { extLabel, isImageValue, itemMeta, valueMeta } from '../support/media-value.js';

export default function snMediaPickerField(props) {
    return {
        multiple: !!props.multiple,
        statePath: props.statePath || '',
        fieldId: props.fieldId || '',
        modalId: props.modalId || '',
        // 预览框/卡片宽高比（模板里 ::style="'aspect-ratio:' + ratio" 依赖此属性，漏了会抛 Alpine Expression Error）
        ratio: Number(props.ratio) || 1,
        // 归一：单值取 initialValues[0] 或 null（空数组是 truthy，不能直接 || 兜底）；多值恒为数组
        values: props.multiple ? (props.initialValues || []) : (props.initialValues?.[0] ?? null),
        urls: props.urls || {},
        // path-only 契约（如产品规格值图）：渲染仍用值对象，写回表单状态时只留 path
        storePath: !!props.storePath,
        // 多值上限（0 = 不限）：到达后「+」触发器禁用
        maxCount: Number(props.maxCount) || 0,
        // 拖动排序时被拖起的那一格
        dragIndex: null,
        // 放大预览中的值对象（null = 关闭）
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

        /**
         * 拖过的瞬间就把数组换位（视觉即时跟手），松手时再统一回写状态——
         * 只在 dragend 发一次请求，拖动过程中不产生往返。
         */
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

        onSelected(detail) {
            if (!detail || detail.statePath !== this.statePath || detail.fieldId !== this.fieldId) {
                return;
            }

            const values = detail.values || [];
            this.urls = Object.assign({}, this.urls, detail.urls || {});
            this.values = this.multiple ? values : (values[0] || null);
            this.closePicker();

            this.sync();
        },

        onClosed(detail) {
            if (!detail || detail.statePath !== this.statePath || detail.fieldId !== this.fieldId) {
                return;
            }
            this.closePicker();
        },

        openPicker() {
            // 打开前把「字段当前值」推给弹层：Picker 是常驻的 Livewire 组件
            // （wire:key 固定、modal 只切显隐），mount() 只跑一次，本地选中态不会
            // 跟着字段的移除 / 替换更新 —— 不同步的话，移除一张再打开，那张仍是
            // 勾选态，直接确认又会把它加回来。
            // 顺序上也正好覆盖「点取消」的场景：下次打开回到字段真实值，丢弃未确认勾选。
            const current = this.multiple
                ? (Array.isArray(this.values) ? this.values : [])
                : (this.values ? [this.values] : []);

            window.dispatchEvent(
                new CustomEvent('sn-media-picker-open', {
                    detail: {
                        statePath: this.statePath,
                        fieldId: this.fieldId,
                        // 完整值对象（不只是 path）：弹层反查不到 id 时靠它兜底，
                        // 否则确认一次就会把这些值弄丢。
                        values: current,
                        urls: this.urls || {},
                    },
                }),
            );

            window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: this.modalId } }));
        },

        closePicker() {
            window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: this.modalId } }));
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

        /**
         * 写回表单状态：默认写完整值对象；storePath 模式只写 path（单值 string / 多值 string[]）。
         */
        statePayload() {
            if (!this.storePath) {
                return this.values;
            }

            return this.multiple
                ? (this.values || []).map((v) => v?.path).filter(Boolean)
                : (this.values?.path ?? null);
        },

        removeAt(index) {
            this.values.splice(index, 1);
            this.sync();
        },

        /**
         * 写回表单状态：第三个参数 false = 不立即发请求，等下一次请求（保存/校验）一起提交。
         * 排序、移除、选完确认本来只改本地数组，没必要每点一次就往返一趟。
         */
        sync() {
            this.$wire.set(this.statePath, this.statePayload(), false);
        },
    };
}
