/**
 * FilePicker field state (Alpine asset component).
 * The trigger opens the native file dialog, uploads into the host form's attachment slot and the
 * server returns value objects — no media modal. Differs from snMediaPickerField only in where the
 * values come from; trigger look, sorting, limits and write-back timing are shared.
 * Method names are called directly from the Blade views — renaming one fails silently.
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
        /** Locates the field instance on the server (first argument of callSchemaComponentMethod) */
        fieldKey: props.fieldKey || '',
        // Aspect ratio used by the template; omitting it raises an Alpine Expression Error.
        ratio: Number(props.ratio) || 1,
        values: props.multiple ? (props.initialValues || []) : (props.initialValues?.[0] ?? null),
        urls: props.urls || {},
        // path-only mode: render from value objects, but write only `path` to the form state.
        storePath: !!props.storePath,
        // Max values, 0 = unlimited; the "+" trigger is disabled and extra files are not uploaded.
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

            // Size check first: oversized files are reported without a request (same limits as the
            // media page and the picker).
            const files = this.filterOversized(this.limitToRoom(picked));
            let uploaded = 0;

            for (const [index, file] of files.entries()) {
                const isLast = index === files.length - 1;
                // The running count goes down so the server can send a single "N files uploaded"
                // notification when the last file lands.
                // eslint-disable-next-line no-await-in-loop
                const stored = await this.uploadOne(file, isLast, uploaded + 1);

                if (stored) {
                    uploaded++;
                }
            }

            this.sync();
        },

        /**
         * Drops files that no longer fit (10 picked, 2 slots left → the extras are discarded
         * instead of silently overflowing the array).
         */
        limitToRoom(files) {
            if (!this.multiple || this.maxCount <= 0) {
                return files;
            }

            const room = this.maxCount - (this.values?.length || 0);

            return room > 0 ? files.slice(0, room) : [];
        },

        /**
         * The failure notice must come from the field itself: $wire here is the host form
         * component, which has no uploadFailed.
         */
        notifyUploadRejected(message) {
            this.$wire.callSchemaComponentMethod(this.fieldKey, 'notifyUploadRejected', [message || '']);
        },

        /**
         * One file: into the host form's attachment slot
         * (componentFileAttachments.{statePath}[.{key}]), then the server converts it to a value object.
         *
         * @param {File} file
         * @param {boolean} isLastOfBatch last of the batch: the server sends the success notice
         * @param {number} batchCount successful uploads so far in this batch, including this one
         */
        async uploadOne(file, isLastOfBatch = true, batchCount = 1) {
            // One slot per file in multi mode so successive uploads do not overwrite each other
            // (the key only has to be unique within this session).
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
                // Rejected at the transport layer (PHP / Livewire limits): the unified notifier
                // supplies the reason.
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
                // The server already sent a notice when its validation failed.
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
         * Form payload: full value objects, or only paths in storePath mode.
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
         * Third argument false defers the request: the upload already round-tripped, so the state
         * can ride along with the next request (save / validate).
         */
        sync() {
            this.$wire.set(this.statePath, this.statePayload(), false);
        },
    };
}
