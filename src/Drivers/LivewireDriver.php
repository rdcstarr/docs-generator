<?php

namespace Rdcstarr\DocsGenerator\Drivers;

use Illuminate\Support\Facades\Http;
use Rdcstarr\DocsGenerator\Contracts\DocsDriver;
use Rdcstarr\DocsGenerator\Support\HtmlExtractor;
use Rdcstarr\DocsGenerator\Support\SidebarDiscovery;

class LivewireDriver implements DocsDriver
{
    /**
     * Livewire docs version (e.g. "4.x").
     *
     * @var string
     */
    protected string $version;

    /**
     * HTTP request timeout in seconds.
     *
     * @var int
     */
    protected int $timeout;

    /**
     * HTML content extractor.
     *
     * @var \Rdcstarr\DocsGenerator\Support\HtmlExtractor
     */
    protected HtmlExtractor $html;

    /**
     * Sidebar link discovery service.
     *
     * @var \Rdcstarr\DocsGenerator\Support\SidebarDiscovery
     */
    protected SidebarDiscovery $discovery;

    /**
     * Create a new LivewireDriver instance.
     *
     * @param  array{version?: string, timeout?: int}  $config
     * @return void
     */
    public function __construct(array $config = [])
    {
        $this->version = (string) ($config['version'] ?? '4.x');
        $this->timeout = (int) ($config['timeout'] ?? 30);
        $this->html = new HtmlExtractor((int) ($config['max_html_bytes'] ?? 200_000));
        $this->discovery = new SidebarDiscovery;
    }

    /**
     * Return the driver's short key.
     *
     * @return string
     */
    public function name(): string
    {
        return 'livewire';
    }

    /**
     * Return the heading used in CLAUDE.md for this driver's section.
     *
     * @return string
     */
    public function indexSection(): string
    {
        return '## Livewire';
    }

    /**
     * Discover all documentation pages from the Livewire docs sidebar.
     *
     * @return array<int, array{url: string, filename: string}>
     */
    public function discoverPages(): array
    {
        $discoveryUrl = "https://livewire.laravel.com/docs/{$this->version}/quickstart";

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])
            ->get($discoveryUrl);

        abort_unless($response->successful(), 1, 'Could not fetch livewire.laravel.com discovery page.');

        $escapedVersion = preg_quote($this->version, '#');

        return $this->discovery->discoverLinks(
            $response->body(),
            "#^https://livewire\\.laravel\\.com/docs/{$escapedVersion}/[^/]+$#",
            'https://livewire.laravel.com'
        );
    }

    /**
     * Fetch a single documentation page and return its cleaned HTML body.
     *
     * @param  string  $url
     * @return string|null
     */
    public function fetchPage(string $url): ?string
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])
            ->get($url);

        if ($response->failed())
        {
            return null;
        }

        return $this->html->extractMainContent($response->body());
    }

    /**
     * Build the AI prompt used to convert the HTML of a page into Markdown.
     *
     * @param  string  $url
     * @param  string  $html
     * @return string
     */
    public function buildPrompt(string $url, string $html): string
    {
        $version = $this->version;

        return <<<PROMPT
            Fetch the content of this Livewire {$version} documentation page: {$url}

            The HTML content of the page is provided below. Generate a comprehensive .md documentation file optimized for AI code assistants (e.g. GitHub Copilot, Claude).

            CRITICAL: Output ONLY raw markdown. No preamble, no explanation, no code fences wrapping the entire output. Start directly with the H1 heading.

            Requirements:
            1. Write in English
            2. Start with a H1 title and a brief description of the feature/concept
            3. Include ALL code examples found on the page, using proper fenced code blocks with language tags (php, blade, html, javascript)
            4. Document all available options, parameters, and method signatures in markdown tables where applicable
            5. Include all variant examples and usage patterns
            6. Use clear H2/H3 section headers
            7. Keep content concise but complete — no navigation noise, no marketing copy
            8. Optimize structure so an AI model can quickly understand how to use this Livewire {$version} feature in a Laravel/Livewire/Blade project
            9. Highlight any differences or migration notes from previous Livewire versions when relevant

            PAGE HTML:
            {$html}
            PROMPT;
    }
}
