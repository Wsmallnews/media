<?php

namespace Wsmallnews\Media;

use BadMethodCallException;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Wsmallnews\Media\Support\Utils;
use Wsmallnews\Support\Filament\Concerns\RegistersConfigurable;

/**
 * @method static mixed getPanelRegister(?string $type = null)
 */
class MediaPlugin implements Plugin
{
    use RegistersConfigurable;

    public function getId(): string
    {
        return 'sn-media';
    }

    public function register(Panel $panel): void
    {
        $this->registerConfigurablePages($panel);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists(Utils::class, $method)) {
            return Utils::$method(...$arguments);
        }

        throw new BadMethodCallException("Method {$method} does not exist on MediaPlugin");
    }
}
