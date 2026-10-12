<?php

namespace Wsmallnews\Media;

use BadMethodCallException;
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
    }

    public function packageBooted(): void
    {
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
        }

        // 注册 livewire 命名空间（自动发现 src/Livewire/ 下的组件）
        Livewire::addNamespace(
            namespace: 'sn-media',
            classNamespace: 'Wsmallnews\\Media\\Livewire'
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
        // 路由文件名（不含 .php，对应 routes/media.php）；web 中间件在路由文件内声明
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
        ];
    }

    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists(Utils::class, $method)) {
            return Utils::$method(...$arguments);
        }

        throw new BadMethodCallException("Method {$method} does not exist on MediaServiceProvider");
    }
}
