/**
 * 素材管理页 / 素材选择弹窗本地交互态（Alpine 资产组件，经 Filament 资产管线注册）：
 * 累积多选（+ 全选当页）、拖拽上传、上传进度（进度回调，不再是 DOM 事件）、
 * 上传前客户端大小预检（服务端三层最小值经 maxUploadKb 下发），
 * 以及选择弹窗的「本地选中态」（勾选不再往返后端）。
 * 业务操作（移动/删除/预览）走 Filament Action（mountAction）或 wire:click，本地只承载纯展示态。
 */
import { uploadProgress } from '../support/upload-progress.js';

export default function snMediaManager(props = {}) {
    return {
        // 上传进度/失败状态（与 FilePicker 同一份实现，见 support/upload-progress.js）
        // 上限与「超限」文案也在共享辅助里（素材页 / 弹窗 / 字段三处同源）
        ...uploadProgress({
            label: props.uploadingLabel,
            rejectedLabel: props.rejectedLabel,
            tooLargeLabel: props.tooLargeLabel,
            maxUploadKb: props.maxUploadKb,
        }),

        selected: [],
        dragOver: false,

        // ===== 选择弹窗的本地选中态（props 里带 statePath 才生效；管理页用上面的 selected） =====
        pickerMultiple: false,
        pickerStatePath: '',
        pickerFieldId: '',
        /** 有序的素材 id（数组序即保存顺序）。反查不到 id 的旧值用 `path:xxx` 虚拟 key 占位 */
        pickerSelection: [],
        /** id => { value, url }：翻页后仍能凭 id 拼出完整值对象 */
        pickerEntries: {},
        /** path => url：给「反查不到 id」的虚拟条目补预览地址 */
        pickerUrls: {},
        /** 本字段还能选几张（0 = 不限） */
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

        // ===== 上传前预检 + 手动提交（绕过 wire:model 的自动上传，超限文件在客户端拦截） =====

        /**
         * 交给 Livewire 上传：进度与失败只能靠回调拿（见 support/upload-progress.js）。
         * 服务端 `updatedUploads()` 负责落库、刷新列表与结果通知。
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

            // 重置 input，保证同名文件可重复选择
            event.target.value = '';

            this.submitFiles(this.filterOversized(picked));
        },

        // ===== 选择弹窗：勾选/取消/确认全部在本地完成，不发请求 =====

        /**
         * 打开弹层时用字段当前值重建选中态（字段侧 openPicker 派发 sn-media-picker-open）。
         *
         * 为什么每次打开都要重建：Picker 是常驻的 Livewire 组件（wire:key 固定、
         * modal 只切 display），mount() 只跑一次，pickerSelection 不会跟着字段的
         * 移除 / 替换更新 —— 不同步就重开，被删掉的那张仍是勾选态，直接确认会把它复活。
         *
         * 额度：重建后的数组长度就是「已选张数」，包含当前页看不见的那些（跨页已选），
         * 所以剩余张数不需要额外的计数器；用户看到的勾选数少于一屏时，额度依然是准的。
         */
        syncPickerSelection(detail) {
            if (!detail || detail.statePath !== this.pickerStatePath || detail.fieldId !== this.pickerFieldId) {
                return;
            }

            const values = Array.isArray(detail.values) ? detail.values : [];
            this.pickerUrls = detail.urls || {};

            // pickerEntries 的 key 是对象键（字符串），而 pickerSelection / togglePicker 用数字 id；
            // 不转 Number 的话 includes() 严格比较永远 false，勾选态会全部对不上。
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

                    // 反查不到（素材已删 / state 被外部改写）：造一个虚拟条目。
                    // 直接丢掉会让「确认一次」把这些值从字段里抹掉；虚拟 id 不匹配任何瓦片，
                    // 所以不会误显示成选中，但它仍然占一个名额。
                    const virtualId = `path:${value.path}`;
                    this.pickerEntries[virtualId] = {
                        value,
                        url: this.pickerUrls[value.path] || '',
                    };

                    return virtualId;
                });
        },

        /**
         * @param {number} id 素材 id
         * @param {object} value 值对象（path/name/mime/size/width/height）
         * @param {string} url 预览地址
         */
        togglePicker(id, value, url) {
            if (value) {
                this.pickerEntries[id] = { value, url };
            }

            const has = this.pickerSelection.includes(id);

            if (this.pickerMultiple) {
                // 到达上限后不再增加（已选的仍可取消，否则就没法腾位置）
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
         * 底部提示：还可选 N 张 / 已达上限 N 张（0 = 不限，不显示）
         */
        pickerRemainingText() {
            if (! this.pickerMax) {
                return '';
            }

            // 视图里先用 __COUNT__ / __MAX__ 当占位符再走 __('...', ['count' => ...])：
            // 直接传 ':count' 会被 Laravel 的翻译替换吃掉，拿不到可替换的标记。
            const remaining = this.pickerMax - this.pickerSelection.length;

            return remaining > 0
                ? this.pickerRemainingLabel.replace('__COUNT__', String(remaining))
                : this.pickerLimitTpl.replace('__MAX__', String(this.pickerMax));
        },

        isPickerSelected(id) {
            return this.pickerSelection.includes(id);
        },

        /**
         * 确认：按本地选中顺序拼出值对象数组 + 预览地址映射，直接发给字段
         * （字段侧监听 window 级 sn-media-selected，statePath + fieldId 双匹配才回填）。
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
         * 上传完成后服务端把新素材的 id/value/url 推下来，前端直接并入选中。
         *
         * 事件是 window 级的：页面上每个 picker（含未打开的弹层、repeater 里的小弹层）
         * 都会收到，不按 statePath + fieldId 双重过滤的话，在 A 弹层上传的图会
         * 悄悄进 B 弹层的选中态（保存时多出幽灵图片）。
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

        // ===== 多选（本地状态，不发请求；触发批量操作时一次性传给 Filament Action） =====
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

        // ===== 拖拽上传 =====
        onDrop(event) {
            this.dragOver = false;

            this.submitFiles(this.filterOversized(Array.from(event.dataTransfer?.files || [])));
        },
    };
}
