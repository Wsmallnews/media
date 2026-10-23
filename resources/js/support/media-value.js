/**
 * 值对象的展示辅助：MediaPicker / FilePicker 两个字段组件的公共部分。
 *
 * 这些方法都只依赖入参，不碰组件状态（`this`），所以可以直接挂到 Alpine 对象上——
 * 模板里 `x-text="valueMeta(value)"` 求值时不需要接收者。
 * 唯一依赖 `this.urls` 的缩略图地址留在各组件里（`thumbSrc`）。
 */
import { formatSize } from './format-size.js';

export function isImageValue(value) {
    return !!value && String(value.mime || '').startsWith('image/');
}

/**
 * 非图片的角标文字：取扩展名前 5 个字（PDF / DOCX / ZIP…）。
 */
export function extLabel(value) {
    const ext = String(value?.path || '').split('.').pop() || 'FILE';

    return ext.toUpperCase().slice(0, 5);
}

/**
 * 单值信息行：尺寸 · 大小 · mime（非图片没有尺寸，用 — 占位）。
 */
export function valueMeta(value) {
    if (!value) {
        return '';
    }

    const dims = value.width ? `${value.width} × ${value.height}` : '—';
    return `${dims} · ${formatSize(value.size)} · ${value.mime}`;
}

/**
 * 列表布局的次要信息：有尺寸就带上尺寸，没有就只给大小（非图片文件）。
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
