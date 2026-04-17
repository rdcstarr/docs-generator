<?php

namespace Rdcstarr\DocsGenerator\Contracts;

interface DocsDriver
{
    /**
     * Return the driver's short key (e.g. "laravel", "flux", "livewire").
     *
     * @return string
     */
    public function name(): string;

    /**
     * Return the heading used in CLAUDE.md for this driver's section (e.g. "## Laravel").
     *
     * @return string
     */
    public function indexSection(): string;

    /**
     * Discover all documentation pages for this driver.
     *
     * Each entry must be an array with keys: "url" and "filename".
     *
     * @return array<int, array{url: string, filename: string}>
     */
    public function discoverPages(): array;

    /**
     * Fetch a single documentation page and return its cleaned HTML body.
     *
     * @param  string  $url
     * @return string|null
     */
    public function fetchPage(string $url): ?string;

    /**
     * Build the AI prompt used to convert the HTML of a page into Markdown.
     *
     * @param  string  $url
     * @param  string  $html
     * @return string
     */
    public function buildPrompt(string $url, string $html): string;
}
