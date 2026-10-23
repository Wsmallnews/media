/**
 * Display helpers shared by the MediaPicker and FilePicker fields.
 * They depend only on their arguments, never on `this`, so they can be attached to an Alpine
 * object and called from templates without a receiver. thumbSrc stays per component (needs this.urls).
 */
import { formatSize } from './format-size.js';

export function isImageValue(value) {
    return !!value && String(value.mime || '').startsWith('image/');
}

/**
 * Badge text for non-images: first 5 characters of the extension (PDF / DOCX / ZIP).
 */
export function extLabel(value) {
    const ext = String(value?.path || '').split('.').pop() || 'FILE';

    return ext.toUpperCase().slice(0, 5);
}

/**
 * Single-value info line: dimensions · size · mime ("—" when there are no dimensions).
 */
export function valueMeta(value) {
    if (!value) {
        return '';
    }

    const dims = value.width ? `${value.width} × ${value.height}` : '—';
    return `${dims} · ${formatSize(value.size)} · ${value.mime}`;
}

/**
 * Secondary line for the list layout: dimensions when known, then the size.
 */
export function itemMeta(value) {
    if (!value) {
        return '';
    }

    const parts = [];
    if (value.width && value.height) {
        parts.push(`${value.width} × ${value.height}`);
    }
    parts.push(formatSize(value.size));

    return parts.join(' · ');
}
