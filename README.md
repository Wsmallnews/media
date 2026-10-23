# wsmallnews/media

Media library for Filament: a library page, a picker modal and two form fields (`MediaPicker`, `FilePicker`), backed by a single upload pipeline.

## Core concepts

- **Upload once, record always** — every upload goes through `MediaManager` (write file → read dimensions → insert `sn_media` → return a value object). No orphan files.
- **`path` is the identity** — the relative path is generated once (`{file_directory}/{Ymd}/{md5}.{ext}`) and never changes. Renaming only touches the display `name`; moving between directories only touches `directory_id`.
- **Deleting a record never deletes the file** — there is no reference counting and no prune job. Clean up files yourself if a record is gone.
- **Value object contract** — business columns store `{path, name, mime, size, width, height}` (an array of them for multi-value fields; array order is the order). Rendering needs no query. Plain path strings are still supported through the `media()` presenter.
- **URL transforms** — `media_url($value, 400)` appends a resize parameter for the current disk's provider (Aliyun OSS, Tencent COS, Qiniu, Huawei OBS, Amazon S3); local disks serve the original file. No pre-generation, no queue.
- **Any file type** — image / video / audio / PDF / Office / archive / unknown. Width and height are only computed for images.
- **Tenancy** — isolated by `team_id`; business dimensions (shop, store) are expressed as directory prefixes in the path.

## Installation

```bash
composer require wsmallnews/media
php artisan media:install
```

## Form fields

```php
use Wsmallnews\Media\Filament\Forms\FilePicker;
use Wsmallnews\Media\Filament\Forms\MediaPicker;

// Pick from the library (single value)
MediaPicker::make('image')->directory('sn/shop/product')

// Multiple values, drag to reorder
MediaPicker::make('gallery')->multiple()->directory('sn/shop/product')

// 3:1 trigger shape
MediaPicker::make('banner')->ratio(3)->acceptImages()

// PDF only
MediaPicker::make('certificate')->acceptedMimes(['application/pdf'])

// Upload directly, without touching the library
FilePicker::make('proof')->directory('sn/shop/contracts')->acceptedMimes(['application/pdf']);
```

Shared options: `multiple()`, `ratio()`, `acceptedMimes()`, `directory()`, `maxCount()`, `itemsLayout('grid'|'list')`, `triggerSize()`, `triggerIcon()`, `triggerLabel()`, `previewInfo()`, `imagePreview()`, `storePath()`.

`MediaPicker` adds modal options (`pickerSlideOver()`, `pickerWidth()`, …); `FilePicker` adds direct-upload options (`disk()`, `storageOnly()`, …).

## Rendering

```blade
{{-- Presenter: value object, path string or model --}}
<img src="{{ media($product->image)->url(400) }}" alt="{{ media($product->image)->alt }}">

{{-- Force download with the original filename --}}
<a href="{{ media($award->certificate)->downloadUrl() }}">Download</a>

{{-- Legacy plain-path columns work too --}}
<img src="{{ media_url($options['icon_src'], 64) }}">
```

## Storage layout

```
{file_directory}/{Ymd}/{md5(content)}.{ext}
```

Uploading identical content on the same day reuses the existing record (same path, unique index); the same content on another day becomes a new file. Moving a file between directories does **not** move it on disk.

## Library page and demo

`MediaPlugin` registers the library page in the panel (navigation group etc. under `panel_register` in `config/sn-media.php`).

The package also ships an official demo page (`MediaDemo`, slug `media-demo`) that puts every `MediaPicker` / `FilePicker` capability on one screen. It writes to no business table and is **not registered by default**: uncomment `MediaDemo::class` in `panel_register.pages` together with the matching `use` statement.

## Configuration

`config/sn-media.php`:

| Key | Description |
| --- | --- |
| `disk` | Active disk for all uploads (default `public`) |
| `allowed_disks` | Extra selectable disks for the field-level `disk()` option |
| `visibility` | Upload visibility (`public` / `private`) |
| `file_directory` | Root of the on-disk path; the date segment is appended automatically |
| `max_size` | Max upload size in KB; `null` = unlimited (still bounded by PHP / Livewire) |
| `mimes` | Lowercase extension whitelist; `null` = unlimited. Executable extensions are always rejected, SVG/HTML stay closed by default |
| `transforms.providers` | Resize templates per object storage; `null` = serve the original |
| `transforms.disks` | Disk name → provider key, overrides driver inference (needed for OBS) |
| `download_url_ttl` / `preview_url_ttl` | Temporary URL lifetime in minutes |
| `models.media` | Replaceable Media model |
| `panel_register` | Pages registered by `MediaPlugin` |

## Limits

- Deleting a media record leaves the physical file in place — by design.
- Width and height are only stored for images; other types report `null`.
- `formatSize()` renders `—` when a path cannot be resolved back to a record; that is expected, not a bug.

## License

MIT
