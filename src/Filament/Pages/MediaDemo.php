<?php

namespace Wsmallnews\Media\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\SlideOverPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;
use Wsmallnews\Media\Filament\Forms\FilePicker;
use Wsmallnews\Media\Filament\Forms\MediaPicker;
use Wsmallnews\Support\Filament\Pages\PageConfiguration;

/**
 * 官方演示页：把 MediaPicker / FilePicker 的**全部**能力摆在一起，供使用者照着抄。
 *
 * 覆盖范围（新增选项时必须同步补到这里，否则等于没文档）：
 * 单值 / 多值、网格 / 列表、上限、宽高比与固定尺寸、关闭文件信息、关闭放大预览、
 * 触发器图标与文案、仅 PDF / 全格式、弹窗形态（居中 / 左滑 / 右滑 / 宽度）、
 * 弹窗初始视图与排序、落盘子目录、path-only 契约、私有盘、storageOnly、
 * Repeater 嵌套（含嵌套内的 FilePicker 上传）。
 *
 * 注册方式：`config/sn-media.php` 的 `panel_register.pages`（MediaPlugin 读它）。
 * 正式发布时注释掉那一行即可从面板消失；本页不落任何业务表。
 */
class MediaDemo extends Page implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    /** 走 support 包的可配置页机制（panel_register.pages 里注册需要它） */
    protected static ?string $configurationClass = PageConfiguration::class;

    protected static ?string $slug = 'media-demo';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string | UnitEnum | null $navigationGroup = '素材库';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = '素材字段演示';

    protected string $view = 'sn-media::filament.pages.media-demo';

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getSubheading(): ?string
    {
        return '本页是 wsmallnews/media 的官方演示：所有字段选项都在这，照着抄即可。值契约见页面底部。';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ============ 图片 · 单值 ============
                Section::make('图片 · 单值')
                    ->description('触发器尺寸/比例由调用处定义；previewInfo(false) 只留缩略图，imagePreview(false) 关掉放大预览')
                    ->columns(4)
                    ->schema([
                        MediaPicker::make('image')
                            ->label('默认（商品主图）')
                            ->acceptImages(),
                        MediaPicker::make('banner')
                            ->label('3:1 长条（Banner）')
                            ->acceptImages()
                            ->ratio(3),
                        MediaPicker::make('avatar')
                            ->label('80×80 + 不显示文件信息')
                            ->acceptImages()
                            ->triggerSize(80, 80)
                            ->previewInfo(false),
                        MediaPicker::make('cover_plain')
                            ->label('关闭点击放大预览')
                            ->acceptImages()
                            ->imagePreview(false),
                    ]),

                // ============ 图片 · 多值 ============
                Section::make('图片 · 多值')
                    ->description('数组序即排序（拖拽换位）；itemsLayout 决定网格还是列表；maxCount 到达后触发器禁用、弹窗内也限选')
                    ->columns(2)
                    ->schema([
                        MediaPicker::make('gallery')
                            ->label('网格（可拖排序）')
                            ->multiple()
                            ->acceptImages(),
                        MediaPicker::make('gallery_list')
                            ->label('列表（可拖排序）')
                            ->multiple()
                            ->acceptImages()
                            ->itemsLayout('list'),
                        MediaPicker::make('gallery_limited')
                            ->label('网格 + 最多 3 张')
                            ->multiple()
                            ->acceptImages()
                            ->maxCount(3)
                            ->triggerLabel('添加（最多 3 张）'),
                        MediaPicker::make('gallery_list_limited')
                            ->label('列表 + 最多 2 张')
                            ->multiple()
                            ->acceptImages()
                            ->itemsLayout('list')
                            ->maxCount(2)
                            ->triggerLabel('添加（最多 2 张）'),
                    ]),

                // ============ 非图片文件 ============
                Section::make('非图片文件')
                    ->description('非图片没有宽高；列表布局比扩展名色块更好认；下载以原始文件名返回')
                    ->columns(2)
                    ->schema([
                        MediaPicker::make('certificate')
                            ->label('仅 PDF（单值）')
                            ->acceptedMimes(['application/pdf']),
                        MediaPicker::make('attachment')
                            ->label('全格式（单值）'),
                        MediaPicker::make('docs')
                            ->label('文档多选（列表 + 限定类型）')
                            ->multiple()
                            ->itemsLayout('list')
                            ->acceptedMimes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/zip',
                            ])
                            ->triggerLabel('添加文档')
                            ->columnSpanFull(),
                    ]),

                // ============ 弹层形态（MediaPicker 专有） ============
                Section::make('弹层形态与初始视图')
                    ->description('pickerDisplay / pickerSlideOver / pickerWidth / pickerViewMode / pickerSortBy —— 只影响弹出的素材库，不影响字段取值')
                    ->columns(3)
                    ->schema([
                        MediaPicker::make('slide_over_end')
                            ->label('右侧滑出（默认）')
                            ->acceptImages()
                            ->pickerSlideOver(),
                        MediaPicker::make('slide_over_start')
                            ->label('左侧滑出 + 3xl 宽')
                            ->acceptImages()
                            ->pickerSlideOver(SlideOverPosition::Start)
                            ->pickerWidth('3xl'),
                        MediaPicker::make('modal_wide_list')
                            ->label('居中 7xl + 初始列表视图 + 名称排序')
                            ->acceptImages()
                            ->pickerDisplay('modal')
                            ->pickerWidth('7xl')
                            ->pickerViewMode('list')
                            ->pickerSortBy('name_asc'),
                    ]),

                // ============ 触发器外观 ============
                Section::make('触发器外观（triggerIcon / triggerLabel）')
                    ->description('三处触发器（单值 / 网格瓦片 / 列表行）同一份实现：不传就只有加号；放不下自动换行，仍超宽则省略号')
                    ->columns(4)
                    ->schema([
                        MediaPicker::make('trigger_default')
                            ->label('默认：只有图标'),
                        MediaPicker::make('trigger_icon')
                            ->label('自定义图标 + 文案')
                            ->triggerIcon(Heroicon::OutlinedArrowUpTray)
                            ->triggerLabel('上传'),
                        MediaPicker::make('trigger_long')
                            ->label('长文案：换行 / 截断')
                            ->triggerLabel('这是一段很长的触发器提示文案'),
                        MediaPicker::make('trigger_multi_list')
                            ->label('多值列表 + 文案')
                            ->multiple()
                            ->itemsLayout('list')
                            ->triggerLabel('添加一行'),
                    ]),

                // ============ 存储契约 ============
                Section::make('存储契约与落盘位置')
                    ->description('默认存完整值对象；storePath 只存路径字符串；directory 只改落盘子目录，不影响逻辑目录（directory_id）')
                    ->columns(3)
                    ->schema([
                        MediaPicker::make('stored_path')
                            ->label('storePath（只存 path）')
                            ->acceptImages()
                            ->storePath(),
                        MediaPicker::make('stored_paths')
                            ->label('storePath + 多值（path 数组）')
                            ->multiple()
                            ->acceptImages()
                            ->storePath(),
                        MediaPicker::make('in_directory')
                            ->label('落盘到 products 子目录')
                            ->acceptImages()
                            ->directory('products'),
                    ]),

                // ============ FilePicker ============
                Section::make('直接上传（FilePicker：不打开素材库）')
                    ->description('点击直接拉起系统文件选择器；值契约与 MediaPicker 完全一致；支持私有盘与 storageOnly（只写盘、不入素材库、无引用计数）')
                    ->columns(3)
                    ->schema([
                        FilePicker::make('contract')
                            ->label('私有盘 + 仅 PDF')
                            ->acceptedMimes(['application/pdf'])
                            ->disk('local'),
                        FilePicker::make('photos')
                            ->label('多值（默认盘）')
                            ->multiple()
                            ->acceptImages(),
                        FilePicker::make('guest_files')
                            ->label('storageOnly（只写盘）')
                            ->storageOnly()
                            ->disk('local')
                            ->multiple()
                            ->itemsLayout('list')
                            ->triggerLabel('上传临时文件'),
                    ]),

                // ============ Repeater ============
                Section::make('Repeater 嵌套')
                    ->description('每行各自独立的字段实例：statePath 带行 key，选中态 / 上传槽 / 值回填都互不串台。这是 morph 压力最大的场景')
                    ->schema([
                        Repeater::make('rows')
                            ->label('明细行')
                            ->columns(4)
                            ->schema([
                                TextInput::make('name')->label('名称'),
                                MediaPicker::make('cover')
                                    ->label('图片（单值）')
                                    ->acceptImages(),
                                MediaPicker::make('attachments')
                                    ->label('图片（多值列表）')
                                    ->multiple()
                                    ->acceptImages()
                                    ->itemsLayout('list'),
                                FilePicker::make('proof')
                                    ->label('证明（FilePicker 直接上传）')
                                    ->acceptImages(),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('添加一行')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label('提交（演示页不落库）')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->action(fn () => $this->submit()),
        ];
    }

    public function submit(): void
    {
        Notification::make()->title('表单已提交：值见页面底部 JSON 面板')->success()->send();
    }

    /**
     * 当前 state 里需要用一句话说明「为什么长这样」的键（其余键见页面上的整体 JSON）
     *
     * @return array<string, string>
     */
    protected function stateNotes(): array
    {
        return [
            'image / gallery（值对象）' => 'path · name · mime · size · width · height；多值时数组序即排序',
            'stored_path / stored_paths（path-only）' => 'storePath() 后只存路径字符串，数组同样是路径数组',
            'guest_files（storageOnly）' => '值对象只有 path/name/mime/size，没有 id —— 文件没进素材库',
            'contract（私有盘）' => 'disk=local，不可直链；预览走 sn-media.file 路由',
            'in_directory（落盘子目录）' => 'path 形如 sn/media/{Ymd}/... 由 Utils::withDate 拼装，products 只加在 file_directory 上',
            'rows（Repeater）' => '每行是独立键（uuid），互不影响',
        ];
    }
}
