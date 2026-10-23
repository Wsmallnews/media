<?php

namespace Wsmallnews\Media;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wsmallnews\Media\Commands\MediaInstallCommand;
use Wsmallnews\Media\Support\LibraryCounts;
use Wsmallnews\Media\Support\MediaManager;
use Wsmallnews\Media\Support\Utils;
use Wsmallnews\Support\Features\Modules\Module;
use Wsmallnews\Support\Features\Modules\ModuleRegistry;

class MediaServiceProvider extends PackageServiceProvider
{
    public static string $name = 'sn-media';

    public static string $viewNamespace = 'sn-media';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasConfigFile()
            ->hasMigrations($this->getMigrations())
            ->hasRoutes($this->getRoutes())
            ->hasTranslations()
            ->hasViews(static::$viewNamespace);
    }

    public function packageRegistered(): void
    {
        // 模块身份登记（ModuleRegistry 单一事实源）
        ModuleRegistry::register(new Module(
            id: static::$name,
            namespace: 'Wsmallnews\\Media',
            plugin: MediaPlugin::class,
        ));

        // 上传管道（容器单例；不设 Facade，避免与 spatie Media 概念歧义）
        $this->app->singleton(MediaManager::class);

        // 库级计数的请求内缓存：一个表单里的多个 picker 共享一次查询
        $this->app->scoped(LibraryCounts::class);
    }

    public function packageBooted(): void
    {
        // 对齐 Livewire 临时上传上限与 sn-media.max_size（Livewire 默认 max:12288/12MB，
        // 低于 sn-media 上限时大文件会在临时上传阶段被静默拦截；仅用户未自定义 rules 时生效）
        if (config('livewire.temporary_file_upload.rules') === null && ($maxSize = Utils::getConfig('max_size'))) {
            config(['livewire.temporary_file_upload.rules' => ['file', 'max:' . (int) $maxSize]]);
        }

        // 注册模型别名
        Relation::enforceMorphMap([
            'sn_media' => Utils::getMediaModel(),
        ]);

        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/media/{$file->getFilename()}"),
                ], 'media-stubs');
            }

            // 类型瓦片 SVG 发布到 public（Tailwind 动态类不靠谱，静态图最稳）
            foreach (app(Filesystem::class)->files(__DIR__ . '/../resources/images/tiles/') as $file) {
                $this->publishes([
                    $file->getRealPath() => public_path("vendor/wsmallnews/media/images/tiles/{$file->getFilename()}"),
                ], 'media-assets');
            }
        }

        // 注册 livewire 命名空间（自动发现 src/Livewire/ 下的组件）
        Livewire::addNamespace(
            namespace: 'sn-media',
            classNamespace: 'Wsmallnews\\Media\\Livewire'
        );

        Livewire::addNamespace(
            namespace: 'sn-media-filament',
            classNamespace: 'Wsmallnews\\Media\\Filament\\Components'
        );
    }

    protected function getAssetPackageName(): ?string
    {
        return 'wsmallnews/media';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            // 素材管理页本地交互态（累积多选 / 视图切换 / 拖拽上传 / 复制路径）
            AlpineComponent::make('sn-media-manager', __DIR__ . '/../resources/dist/components/media-manager.js'),
            // MediaPicker 字段本地状态（选中回填 / 排序）
            AlpineComponent::make('sn-media-picker-field', __DIR__ . '/../resources/dist/components/picker-field.js'),
            // FilePicker 字段（直接选文件上传，不打开素材弹窗）
            AlpineComponent::make('sn-media-file-picker-field', __DIR__ . '/../resources/dist/components/file-picker-field.js'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            MediaInstallCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return ['media'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_sn_media_table',
            'create_sn_media_directories_table',
        ];
    }
}
