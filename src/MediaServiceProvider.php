<?php

namespace Wsmallnews\Media;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wsmallnews\Media\Commands\MediaInstallCommand;
use Wsmallnews\Media\Support\LibraryCounts;
use Wsmallnews\Media\Support\MediaManager;
use Wsmallnews\Media\Support\MediaResolver;
use Wsmallnews\Media\Support\MediaValue;
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
        // Module identity (ModuleRegistry is the single source of truth).
        ModuleRegistry::register(new Module(
            id: static::$name,
            namespace: 'Wsmallnews\\Media',
            plugin: MediaPlugin::class,
        ));

        // Upload pipeline; no facade, to avoid confusion with spatie/laravel-medialibrary.
        $this->app->singleton(MediaManager::class);

        // Per-request library counts, shared by every picker in a form.
        $this->app->scoped(LibraryCounts::class);

        // Per-request path -> media record cache, backing MediaValue's lazy lookups.
        $this->app->scoped(MediaResolver::class);

        // List prewarming: $collection->resolveMedia(['image', 'images']) gathers every path
        // held in the given fields and fills the resolver cache in one whereIn, so rendering
        // URLs in a loop afterwards stays query-free.
        Collection::macro('resolveMedia', function (array $fields): Collection {
            $paths = [];

            foreach ($this as $model) {
                foreach ($fields as $field) {
                    $value = $model->{$field} ?? null;

                    if ($value instanceof MediaValue) {
                        $paths[] = $value->path;
                    } elseif (is_string($value) && $value !== '') {
                        $paths[] = $value;
                    } elseif (is_iterable($value)) {
                        foreach ($value as $item) {
                            $paths[] = $item instanceof MediaValue ? $item->path : (is_array($item) ? ($item['path'] ?? null) : $item);
                        }
                    }
                }
            }

            if ($paths !== []) {
                app(MediaResolver::class)->cacheByPath($paths);
            }

            return $this;
        });
    }

    public function packageBooted(): void
    {
        // Align Livewire's temporary upload limit with sn-media.max_size; the default 12MB
        // silently rejects larger files. Only applies when the app has not set its own rules.
        if (config('livewire.temporary_file_upload.rules') === null && ($maxSize = Utils::getConfig('max_size'))) {
            config(['livewire.temporary_file_upload.rules' => ['file', 'max:' . (int) $maxSize]]);
        }

        // Morph map.
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

            // Type tile SVGs (static files beat dynamic Tailwind classes).
            foreach (app(Filesystem::class)->files(__DIR__ . '/../resources/images/tiles/') as $file) {
                $this->publishes([
                    $file->getRealPath() => public_path("vendor/wsmallnews/media/images/tiles/{$file->getFilename()}"),
                ], 'media-assets');
            }
        }

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
            // Media page client state (multi-select, view mode, drag upload, copy path).
            AlpineComponent::make('sn-media-manager', __DIR__ . '/../resources/dist/components/media-manager.js'),
            // MediaPicker field state (selection, sorting).
            AlpineComponent::make('sn-media-picker-field', __DIR__ . '/../resources/dist/components/picker-field.js'),
            // FilePicker field (direct upload, no modal).
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
