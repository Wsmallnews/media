/**
 * 字节数 → 可读大小（1.2 MB）。
 *
 * 只在**字段组件**这一层用：picker-field / file-picker-field 的 values 是服务端下发的
 * 值对象，`size` 是字节数；服务端渲染的页面（素材页、弹窗列表）走 `Media::size_for_humans`。
 *
 * 为什么不把这个字符串由 PHP 拼好塞进值对象：值对象就是业务字段的保存值，放展示串
 * 等于把格式固化进数据（规则一改，新旧记录两种格式并存），而且它还要参与排序回写、
 * 去重比较，多一个派生键一直要剔除。一句话：值对象存字节数，格式化留在展示层。
 *
 * 两处字段组件共用这一份，避免同一套换算被复制两份。
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
