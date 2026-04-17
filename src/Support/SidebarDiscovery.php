<?php

namespace Rdcstarr\DocsGenerator\Support;

use Illuminate\Support\Str;

class SidebarDiscovery
{
    /**
     * Discover documentation page links from HTML sidebar content.
     *
     * @param  string  $html
     * @param  string  $urlPattern
     * @param  string  $baseUrl
     * @param  string|null  $sidebarPattern
     * @return array<int, array{url: string, filename: string}>
     */
    public function discoverLinks(string $html, string $urlPattern, string $baseUrl, ?string $sidebarPattern = null): array
    {
        if (filled($sidebarPattern) && preg_match($sidebarPattern, $html, $sidebarMatch))
        {
            $html = $sidebarMatch[0];
        }

        preg_match_all('/<a[^>]+href=["\']([^"\'#][^"\']*)["\']/', $html, $matches);

        return collect($matches[1])
            ->map(function (string $href) use ($baseUrl): string
            {
                if (Str::startsWith($href, 'http'))
                {
                    return $href;
                }

                return str($baseUrl)->rtrim('/')->toString() . '/' . str($href)->ltrim('/')->toString();
            })
            ->filter(fn (string $url): bool => (bool) preg_match($urlPattern, $url))
            ->unique()
            ->map(function (string $url): array
            {
                $segment = Str::afterLast($url, '/');
                $filename = Str::slug($segment) . '.md';

                return ['url' => $url, 'filename' => $filename];
            })
            ->filter(fn (array $page): bool => filled($page['filename']) && $page['filename'] !== '.md')
            ->sortBy('filename')
            ->values()
            ->all();
    }

    /**
     * Filter a pages array to only those matching the --only option slugs.
     *
     * @param  array<int, array{url: string, filename: string}>  $pages
     * @param  string  $only
     * @return array<int, array{url: string, filename: string}>
     */
    public function filterByOnly(array $pages, string $only): array
    {
        $slugs = Str::of($only)->explode(',')
            ->map(fn (string $s) => str($s)->trim()->lower()->toString())
            ->all();

        return collect($pages)
            ->filter(function (array $page) use ($slugs): bool
            {
                $pageSlug = Str::lower(Str::beforeLast($page['filename'], '.md'));

                return collect($slugs)->contains(fn (string $slug) => Str::contains($pageSlug, $slug));
            })
            ->values()
            ->all();
    }
}
