<?php

namespace Rdcstarr\DocsGenerator\Support;

use Illuminate\Support\Str;

class HtmlExtractor
{
    /**
     * Maximum number of bytes kept after stripping noise (token budget control).
     *
     * @var int
     */
    protected int $maxBytes;

    /**
     * Create a new HtmlExtractor instance.
     *
     * @param  int  $maxBytes
     * @return void
     */
    public function __construct(int $maxBytes = 200_000)
    {
        $this->maxBytes = $maxBytes;
    }

    /**
     * Extract the main content area from a full HTML page and strip noise.
     *
     * @param  string  $html
     * @return string
     */
    public function extractMainContent(string $html): string
    {
        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $matches))
        {
            return $this->stripHtmlNoise($matches[1]);
        }

        if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $matches))
        {
            return $this->stripHtmlNoise($matches[1]);
        }

        return $this->stripHtmlNoise($html);
    }

    /**
     * Remove script, style, svg tags, comments, and noisy attributes to reduce token count.
     *
     * @param  string  $html
     * @return string
     */
    public function stripHtmlNoise(string $html): string
    {
        $previousLimit = (int) ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', 5_000_000);

        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<svg\b[^>]*>.*?<\/svg>/is', '', $html) ?? $html;
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;

        $html = preg_replace('/\s+class="[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/\s+style="[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/\s+(?:data|wire|x|aria)-[a-z][a-z0-9:._-]*="[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/\s+@[a-z][a-z0-9:._-]*="[^"]*"/i', '', $html) ?? $html;

        ini_set('pcre.backtrack_limit', $previousLimit);

        $html = strip_tags($html, '<h1><h2><h3><h4><h5><h6><p><pre><code><table><thead><tbody><tr><td><th><ul><ol><li><a><strong><em><blockquote>');

        $html = preg_replace('/\n{3,}/', "\n\n", $html) ?? $html;
        $html = preg_replace('/[ \t]+/', ' ', $html) ?? $html;

        return Str::substr($html, 0, $this->maxBytes);
    }
}
