<?php

namespace Wsmallnews\Media\Support;

use Wsmallnews\Media\Models\Media;

/**
 * Path → Media 记录的请求级解析缓存（scoped 单例，随请求销毁）。
 *
 * MediaValue 的反查全部经这里走：
 * - resolve() 缓存命中零查询；miss 时单查一次并回填（含负缓存，查不到也记，防重复回表）；
 * - cacheByPath() 一次 whereIn 预热一批 path——列表场景用 media_resolve() / Collection::resolveMedia()
 *   显式调用（单条自动降级、整页显式预热的分工见 UploadsMedia 的 docblock）。
 *
 * 查询口径与 Media::findByPath() 一致（当前租户 scope）；单请求内租户不变，缓存无需按租户分桶。
 */
class MediaResolver
{
    /**
     * @var array<string, Media|null>
     */
    protected array $cache = [];

    /**
     * 批量预热：一次 whereIn 解析全部 path（未命中的记为负缓存）。
     *
     * @param  array<int, mixed>  $paths
     */
    public function cacheByPath(array $paths): void
    {
        $paths = array_values(array_unique(array_filter(
            $paths,
            fn (mixed $path): bool => is_string($path) && $path !== '',
        )));

        $missing = [];
        foreach ($paths as $path) {
            if (! array_key_exists($path, $this->cache)) {
                $missing[$path] = true;
            }
        }

        if ($missing === []) {
            return;
        }

        Utils::getMediaModel()::query()
            ->scopeTenant()
            ->whereIn('path', array_keys($missing))
            ->get()
            ->each(fn (Media $media) => $this->cache[$media->path] = $media);

        foreach (array_keys($missing) as $path) {
            $this->cache[$path] ??= null;
        }
    }

    /**
     * 单个 path 解析（缓存优先；miss 自动回表并回填）。
     */
    public function resolve(string $path): ?Media
    {
        if (! array_key_exists($path, $this->cache)) {
            $this->cacheByPath([$path]);
        }

        return $this->cache[$path];
    }
}
