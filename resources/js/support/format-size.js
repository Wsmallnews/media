/**
 * Bytes -> human readable size (1.2 MB).
 *
 * Only used by the field components: their `values` are server-sent value objects holding
 * `size` in bytes. Server-rendered lists use `Media::size_for_humans` instead.
 *
 * Never pre-format on the PHP side and store the string in the value object: the value
 * object is the persisted value, so formatting rules would be frozen into old rows, and
 * the extra derived key has to be stripped from sorting/dedup comparisons.
 */
export function formatSize(bytes) {
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
