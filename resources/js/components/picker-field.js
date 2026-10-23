/**
 * MediaPicker field state (Alpine asset component).
 * Local `values` is the only render source for the wire:ignore block; changes are written back
 * to the form state lazily, and multi-value order is array order.
 * These method names are called directly from the Blade views — renaming one fails silently.
 * Window events are shared by every instance, so handlers must match statePath + fieldId.
 * Display helpers are shared with snFilePickerField via support/media-value.js.
 */
import { extLabel, isImageValue, itemMeta, valueMeta } from '../support/media-value.js';

export default function snMediaPickerField(props) {
    return {
        multiple: !!props.multiple,
        statePath: props.statePath || '',
        fieldId: props.fieldId || '',
        modalId: props.modalId || '',
        // Aspect ratio used by the template; omitting it raises an Alpine Expression Error.
        ratio: Number(props.ratio) || 1,
        // Normalise: single takes initialValues[0] or null (an empty array is truthy, so `||` is
        // wrong); multiple is always an array.
        values: props.multiple ? (props.initialValues || []) : (props.initialValues?.[0] ?? null),
        urls: props.urls || {},
        // path-only mode: render from value objects, but write only `path` to the form state.
        storePath: !!props.storePath,
        // Max values, 0 = unlimited; the "+" trigger is disabled once reached.
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

        /**
         * Reorders on dragenter so the move tracks the cursor; the state is synced once in
         * dragEnd, so dragging never round-trips.
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
            // Push the field's current value before opening: the picker is a persistent Livewire
            // component, so its local selection does not follow later removals. Without this a
            // removed item stays checked and comes back on confirm.
            const current = this.multiple
                ? (Array.isArray(this.values) ? this.values : [])
                : (this.values ? [this.values] : []);

            window.dispatchEvent(
                new CustomEvent('sn-media-picker-open', {
                    detail: {
                        statePath: this.statePath,
                        fieldId: this.fieldId,
                        // Full value objects, not just paths: the picker falls back to them when
                        // an id lookup misses, otherwise confirming drops these values.
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
         * Form payload: full value objects, or only paths in storePath mode (string / string[]).
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
         * Third argument false defers the request to the next one (save / validate) instead of
         * round-tripping on every click.
         */
        sync() {
            this.$wire.set(this.statePath, this.statePayload(), false);
        },
    };
}
