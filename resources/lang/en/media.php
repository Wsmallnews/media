<?php

return [
    'model_label' => 'Media',

    'global_default' => [
        'navigation_group' => 'Media',
    ],

    'page' => [
        'title' => 'Media Library',
        'navigation_label' => 'Media Library',
        'description' => 'Unified file pool: paths are immutable once generated; deleting removes the record only, files are kept',
    ],

    'toolbar' => [
        'upload' => 'Upload',
        'search_placeholder' => 'Search name / path',
        'sort' => [
            'created_desc' => 'Newest',
            'name_asc' => 'Name',
            'size_desc' => 'Size',
        ],
        'view_grid' => 'Large icons',
        'view_list' => 'Details',
    ],

    'tree' => [
        'types' => 'Types',
        'directories' => 'Directories',
        'all' => 'All files',
        'tree_hint' => 'Directories are virtual trees derived from path prefixes.',
    ],

    'list' => [
        'name' => 'Name',
        'created_at' => 'Uploaded at',
        'type' => 'Type',
        'size' => 'Size',
        'actions' => 'Actions',
        'empty' => 'No media here. Drop files to upload',
        'selected_count' => ':count selected',
        'selected_prefix' => 'Selected',
        'total_count' => ':count items',
        'delete_batch' => 'Delete records',
        'clear_selection' => 'Clear selection',
        'preview' => 'Preview',
        'copy_path' => 'Copy path',
        'download' => 'Download',
        'delete' => 'Delete record',
    ],

    'preview' => [
        'name' => 'Original name',
        'path' => 'Stored path',
        'size_label' => 'Dimensions',
        'size_empty' => '—',
        'file_size' => 'Size',
        'mime' => 'MIME',
        'close' => 'Close',
        'rename_label' => 'Display name',
        'alt_label' => 'Alt text',
        'save_meta' => 'Save',
    ],

    'notifications' => [
        'uploaded' => ':count file(s) uploaded',
        'upload_failed' => 'Upload failed: :reason',
        'deleted' => ':count record(s) deleted',
        'delete_hint' => 'Physical files are kept on disk by convention',
        'meta_saved' => 'Media info updated',
        'path_copied' => 'Path copied',
    ],

    'picker' => [
        'select' => 'Select media',
        'tab_library' => 'Library',
        'tab_upload' => 'Upload',
        'library_hint' => 'Only media matching the field type is listed; click to toggle selection',
        'library_empty' => 'No matching media in the library, switch to Upload',
        'drop_here' => 'Drop files here, or',
        'browse' => 'Browse files',
        'upload_hint' => 'Uploaded files enter the library and get selected automatically',
        'selected_count' => ':count selected',
        'selected_prefix' => 'Selected',
        'single_mode' => 'Single',
        'multiple_mode' => 'Multiple',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
        'wait_uploading' => 'Some files are still uploading',
        'trigger_select' => 'Select image',
        'trigger_add' => 'Add',
        'replace' => 'Replace',
        'remove' => 'Remove',
        'move_up' => 'Move up',
        'move_down' => 'Move down',
    ],

    'types' => [
        'image' => 'Image',
        'video' => 'Video',
        'audio' => 'Audio',
        'pdf' => 'PDF document',
        'word' => 'Word document',
        'excel' => 'Excel spreadsheet',
        'ppt' => 'Presentation',
        'zip' => 'Archive',
        'file' => 'File',
    ],
];
