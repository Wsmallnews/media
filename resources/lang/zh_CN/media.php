<?php

return [
    'model_label' => '素材',

    'global_default' => [
        'navigation_group' => '素材库',
    ],

    'page' => [
        'title' => '素材管理',
        'navigation_label' => '素材管理',
        'description' => '统一上传入库的文件池：path 生成后不可变，删除仅移除记录，物理文件保留',
    ],

    'toolbar' => [
        'upload' => '上传',
        'search_placeholder' => '搜索文件名 / 路径',
        'sort' => [
            'created_desc' => '最新上传',
            'name_asc' => '文件名',
            'size_desc' => '大小',
        ],
        'view_grid' => '大图标',
        'view_list' => '详细信息',
    ],

    'tree' => [
        'types' => '类型',
        'directories' => '目录',
        'all' => '全部文件',
        'tree_hint' => '目录 = path 前缀的虚拟树。换云盘只改 config，路径不变。',
    ],

    'list' => [
        'name' => '名称',
        'created_at' => '上传时间',
        'type' => '类型',
        'size' => '大小',
        'actions' => '操作',
        'empty' => '此目录暂无素材，拖拽文件到此处上传',
        'selected_count' => '已选 :count 项',
        'selected_prefix' => '已选',
        'total_count' => '共 :count 个项目',
        'delete_batch' => '批量删除记录',
        'clear_selection' => '清除选择',
        'preview' => '预览',
        'copy_path' => '复制路径',
        'download' => '下载',
        'delete' => '删除记录',
    ],

    'preview' => [
        'name' => '原始文件名',
        'path' => '存储路径',
        'size_label' => '尺寸',
        'size_empty' => '—',
        'file_size' => '大小',
        'mime' => 'MIME',
        'close' => '关闭',
        'rename_label' => '显示名',
        'alt_label' => '替代文本（alt）',
        'save_meta' => '保存信息',
    ],

    'notifications' => [
        'uploaded' => '已上传 :count 个文件',
        'upload_failed' => '上传失败：:reason',
        'deleted' => '已删除 :count 条素材记录',
        'delete_hint' => '物理文件按约定保留在磁盘',
        'meta_saved' => '素材信息已更新',
        'path_copied' => '路径已复制',
    ],

    'picker' => [
        'select' => '选择素材',
        'tab_library' => '素材库',
        'tab_upload' => '本地上传',
        'library_hint' => '仅显示符合字段类型的素材；点击选中，再次点击取消',
        'library_empty' => '素材库中暂无符合类型的文件，请切换到「本地上传」',
        'drop_here' => '拖拽文件到此处，或',
        'browse' => '浏览文件',
        'upload_hint' => '上传完成自动入素材库并选中',
        'selected_count' => '已选 :count 个',
        'selected_prefix' => '已选',
        'single_mode' => '单选模式',
        'multiple_mode' => '多选模式',
        'cancel' => '取消',
        'confirm' => '确定',
        'wait_uploading' => '仍有文件在上传中，请稍候',
        'trigger_select' => '选择图片',
        'trigger_add' => '添加',
        'replace' => '替换',
        'remove' => '移除',
        'move_up' => '上移',
        'move_down' => '下移',
    ],

    'types' => [
        'image' => '图片',
        'video' => '视频',
        'audio' => '音频',
        'pdf' => 'PDF 文档',
        'word' => 'Word 文档',
        'excel' => 'Excel 表格',
        'ppt' => 'PPT 演示',
        'zip' => '压缩包',
        'file' => '文件',
    ],
];
