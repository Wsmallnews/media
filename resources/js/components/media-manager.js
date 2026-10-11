/**
 * 素材管理页本地交互态（Alpine 资产组件，经 Filament 资产管线注册）：
 * 累积多选（点选/再点取消，清空走「清除选择」）、拖拽上传、复制路径。
 * 服务端动作（删除/预览/上传入账）一律 wire:click / $wire.call，本地只承载纯展示态。
 */
export default function snMediaManager() {
    return {
        selected: [],
        dragOver: false,

        toggle(id) {
            this.selected = this.selected.includes(id)
                ? this.selected.filter((i) => i !== id)
                : [...this.selected, id];
        },

        isSelected(id) {
            return this.selected.includes(id);
        },

        batchDelete() {
            if (this.selected.length === 0) {
                return;
            }

            this.$wire.delete(this.selected);
            this.selected = [];
        },

        copyPath(event) {
            const path = event.target.closest('[data-path]')?.dataset.path;
            if (path && navigator.clipboard) {
                navigator.clipboard.writeText(path);
            }
        },

        onDrop(event) {
            this.dragOver = false;

            const files = event.dataTransfer?.files;
            if (files && files.length > 0) {
                this.$wire.uploadMultiple('uploads', files);
            }
        },
    };
}
