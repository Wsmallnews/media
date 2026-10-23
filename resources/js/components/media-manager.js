/**
 * Media page and picker local interaction state (Alpine asset component).
 * Covers cumulative multi-select (+ select page), drag upload, progress via callbacks, a
 * client-side size pre-check (maxUploadKb is the min of the three server limits) and the picker's
 * local selection, which never round-trips. Business operations go through Filament Actions or
 * wire:click; this object only holds display state.
 */
import { uploadProgress } from '../support/upload-progress.js';

export default function snMediaManager(props = {}) {
    return {
        // Upload progress / failure state shared with FilePicker (see support/upload-progress.js);
        // the limits and the "too large" copy live there too, keeping all three entry points in sync.
        ...uploadProgress({
            label: props.uploadingLabel,
            rejectedLabel: props.rejectedLabel,
            tooLargeLabel: props.tooLargeLabel,
            maxUploadKb: props.maxUploadKb,
        }),

        selected: [],
        dragOver: false,

        // Picker local selection; only active when props carry statePath (the page uses `selected`).
        pickerMultiple: false,
        pickerStatePath: '',
        pickerFieldId: '',
        /** Ordered media ids; the array order is the save order. Values without a matching id use a `path:xxx` virtual key. */
        pickerSelection: [],
        /** id => { value, url }: keeps full value objects available after pagination. */
        pickerEntries: {},
        /** path => url: preview address for the virtual entries that carry no id. */
        pickerUrls: {},
        /** Remaining slots for this field, 0 = unlimited. */
        pickerMax: 0,
        pickerRemainingLabel: '',
        pickerLimitTpl: '',

        init() {
            this.pickerMultiple = !!props.multiple;
            this.pickerStatePath = props.statePath || '';
            this.pickerFieldId = props.fieldId || '';
            this.pickerSelection = Array.isArray(props.selection) ? props.selection.slice() : [];
            this.pickerEntries = props.entries || {};
            this.pickerMax = Number(props.maxCount) || 0;
            this.pickerRemainingLabel = props.remainingLabel || '';
            this.pickerLimitTpl = props.limitLabel || '';
        },

        // Size pre-check + manual submit: bypasses wire:model auto-upload so oversized files are
        // stopped on the client.

        /**
         * Hands files to Livewire. Progress and failure only arrive through the callbacks
         * (see support/upload-progress.js); the server's `updatedUploads()` persists and notifies.
         */
        submitFiles(files) {
            if (! files.length) {
                return;
            }

            this.uploadStarted(files.length === 1 ? files[0].name : '');

            this.$wire.uploadMultiple(
                'uploads',
                files,
                () => this.uploadFinished(),
                () => this.failUpload(),
                (event) => this.uploadProgressed(event),
            );
        },

        onFilePick(event) {
            const picked = Array.from(event.target.files || []);

            // Reset the input so picking the same file again still fires change.
            event.target.value = '';

            this.submitFiles(this.filterOversized(picked));
        },

        // Picker: select / deselect / confirm all happen locally, no requests.

        /**
         * Rebuilds the selection on every open. The picker is a persistent Livewire component
         * (mount() runs once), so otherwise a removed value stays checked and is revived on
         * confirm. The array length is the cross-page count, so slots need no extra counter.
         */
        syncPickerSelection(detail) {
            if (!detail || detail.statePath !== this.pickerStatePath || detail.fieldId !== this.pickerFieldId) {
                return;
            }

            const values = Array.isArray(detail.values) ? detail.values : [];
            this.pickerUrls = detail.urls || {};

            // pickerEntries keys are object keys (strings) while pickerSelection holds numeric
            // ids; without Number() the strict includes() comparison never matches and every
            // checkbox silently reads as unselected.
            const idByPath = {};
            Object.entries(this.pickerEntries).forEach(([id, entry]) => {
                const path = entry?.value?.path;
                const numericId = Number(id);

                if (path && Number.isFinite(numericId)) {
                    idByPath[path] = numericId;
                }
            });

            this.pickerSelection = values
                .filter((value) => value && value.path)
                .map((value) => {
                    const id = idByPath[value.path];

                    if (id !== undefined) {
                        return id;
                    }

                    // Unresolvable (media deleted / state edited elsewhere): keep a virtual entry.
                    // Dropping it would erase these values on the next confirm; it matches no tile,
                    // so it never renders as selected, yet it still occupies a slot.
                    const virtualId = `path:${value.path}`;
                    this.pickerEntries[virtualId] = {
                        value,
                        url: this.pickerUrls[value.path] || '',
                    };

                    return virtualId;
                });
        },

        /**
         * @param {number} id media id
         * @param {object} value value object (path / name / mime / size / width / height)
         * @param {string} url preview url
         */
        togglePicker(id, value, url) {
            if (value) {
                this.pickerEntries[id] = { value, url };
            }

            const has = this.pickerSelection.includes(id);

            if (this.pickerMultiple) {
                // Stop adding at the limit, but keep deselect allowed or no slot could be freed.
                if (! has && this.pickerMax > 0 && this.pickerSelection.length >= this.pickerMax) {
                    return;
                }

                this.pickerSelection = has
                    ? this.pickerSelection.filter((i) => i !== id)
                    : [...this.pickerSelection, id];
            } else {
                this.pickerSelection = has ? [] : [id];
            }
        },

        /**
         * Footer hint: N more selectable, or the limit of N is reached (0 = unlimited, no hint).
         */
        pickerRemainingText() {
            if (! this.pickerMax) {
                return '';
            }

            // Server-side translations carry __COUNT__ / __MAX__ placeholders (same convention as
            // upload_too_large); the substituted text may contain anything, digits included.
            const remaining = this.pickerMax - this.pickerSelection.length;

            return remaining > 0
                ? this.pickerRemainingLabel.replace('__COUNT__', String(remaining))
                : this.pickerLimitTpl.replace('__MAX__', String(this.pickerMax));
        },

        isPickerSelected(id) {
            return this.pickerSelection.includes(id);
        },

        /**
         * Confirm: emit the value objects in local selection order plus the url map. The field
         * listens on the window event and matches statePath + fieldId.
         */
        confirmPicker() {
            const urls = {};
            const values = this.pickerSelection
                .map((id) => this.pickerEntries[id])
                .filter((entry) => entry && entry.value && entry.value.path)
                .map((entry) => {
                    urls[entry.value.path] = entry.url;

                    return entry.value;
                });

            window.dispatchEvent(
                new CustomEvent('sn-media-selected', {
                    detail: {
                        statePath: this.pickerStatePath,
                        fieldId: this.pickerFieldId,
                        multiple: this.pickerMultiple,
                        values: this.pickerMultiple ? values : values.slice(0, 1),
                        urls,
                    },
                }),
            );
        },

        cancelPicker() {
            window.dispatchEvent(
                new CustomEvent('sn-media-picker-closed', {
                    detail: { statePath: this.pickerStatePath, fieldId: this.pickerFieldId },
                }),
            );
        },

        /**
         * Merges a freshly uploaded item into the selection. The event is window-level, so every
         * picker on the page receives it: without the statePath + fieldId filter an upload in
         * modal A silently lands in modal B and surfaces as a ghost image on save.
         */
        onMediaUploaded(detail) {
            if (!detail || !detail.id) {
                return;
            }

            if (detail.statePath !== this.pickerStatePath || detail.fieldId !== this.pickerFieldId) {
                return;
            }

            this.pickerEntries[detail.id] = { value: detail.value, url: detail.url };
            this.pickerSelection = this.pickerMultiple
                ? Array.from(new Set([...this.pickerSelection, detail.id]))
                : [detail.id];
        },

        // Multi-select: local state only, ids are handed to the Filament Action in one go.
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

        // Drag and drop upload.
        onDrop(event) {
            this.dragOver = false;

            this.submitFiles(this.filterOversized(Array.from(event.dataTransfer?.files || [])));
        },
    };
}
