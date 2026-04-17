<?php

namespace Rdcstarr\DocsGenerator\Drivers;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Rdcstarr\DocsGenerator\Contracts\DocsDriver;
use Rdcstarr\DocsGenerator\Support\HtmlExtractor;
use Rdcstarr\DocsGenerator\Support\SidebarDiscovery;

class FluxDriver implements DocsDriver
{
    /**
     * FluxUI account email.
     *
     * @var string|null
     */
    protected ?string $email;

    /**
     * FluxUI account password.
     *
     * @var string|null
     */
    protected ?string $password;

    /**
     * HTTP request timeout in seconds.
     *
     * @var int
     */
    protected int $timeout;

    /**
     * Authenticated cookie jar (resolved lazily on first request).
     *
     * @var \GuzzleHttp\Cookie\CookieJar|null
     */
    protected ?CookieJar $cookieJar = null;

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
     * Create a new FluxDriver instance.
     *
     * @param  array{email?: ?string, password?: ?string, timeout?: int}  $config
     * @return void
     */
    public function __construct(array $config = [])
    {
        $this->email = $config['email'] ?? null;
        $this->password = $config['password'] ?? null;
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
        return 'flux';
    }

    /**
     * Return the heading used in CLAUDE.md for this driver's section.
     *
     * @return string
     */
    public function indexSection(): string
    {
        return '## Flux UI';
    }

    /**
     * Discover all documentation pages from the FluxUI docs sidebar.
     *
     * @return array<int, array{url: string, filename: string}>
     */
    public function discoverPages(): array
    {
        $cookieJar = $this->ensureAuthenticated();

        $response = Http::withOptions(['cookies' => $cookieJar])
            ->timeout($this->timeout)
            ->withHeaders([
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])
            ->get('https://fluxui.dev/components/button');

        abort_unless($response->successful(), 1, 'Could not fetch fluxui.dev discovery page.');

        return $this->discovery->discoverLinks(
            $response->body(),
            '#^https://fluxui\.dev/(components|docs|layouts)/[^/]+$#',
            'https://fluxui.dev'
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
        $cookieJar = $this->ensureAuthenticated();

        $response = Http::withOptions(['cookies' => $cookieJar])
            ->timeout($this->timeout)
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
        return <<<PROMPT
            Fetch the content of this Flux UI documentation page: {$url}

            The HTML content of the page is provided below. Generate a comprehensive .md documentation file optimized for AI code assistants (e.g. GitHub Copilot, Claude).

            CRITICAL: Output ONLY raw markdown. No preamble, no explanation, no code fences wrapping the entire output. Start directly with the H1 heading.

            Requirements:
            1. Write in English
            2. Start with a H1 title and a brief description of the component/topic
            3. Include ALL code examples found on the page, using proper fenced code blocks with language tags (blade, php, html, css, javascript)
            4. Include a complete Props/API reference table in markdown table format with columns: Prop | Type | Default | Description
            5. Include all variant examples and usage patterns
            6. Mark any Pro-only features with: ⚡ Requires Flux Pro
            7. Use clear H2/H3 section headers
            8. Keep content concise but complete — no navigation noise, no marketing copy
            9. Optimize structure so an AI model can quickly understand how to use this component in a Laravel/Livewire/Blade project

            PAGE HTML:
            {$html}
            PROMPT;
    }

    /**
     * Lazily authenticate against fluxui.dev and cache the resulting cookie jar.
     *
     * @return \GuzzleHttp\Cookie\CookieJar
     */
    protected function ensureAuthenticated(): CookieJar
    {
        if ($this->cookieJar instanceof CookieJar)
        {
            return $this->cookieJar;
        }

        abort_if(blank($this->email) || blank($this->password), 1, 'FLUXUI_EMAIL and FLUXUI_PASSWORD must be set in .env.');

        $jar = $this->authenticate($this->email, $this->password);

        abort_if(blank($jar), 1, 'Authentication failed. Check FLUXUI_EMAIL and FLUXUI_PASSWORD.');

        return $this->cookieJar = $jar;
    }

    /**
     * Authenticate on fluxui.dev via Livewire JSON and return an authenticated CookieJar.
     *
     * @param  string  $email
     * @param  string  $password
     * @return \GuzzleHttp\Cookie\CookieJar|null
     */
    protected function authenticate(string $email, string $password): ?CookieJar
    {
        $cookieJar = new CookieJar;

        $loginPage = Http::withOptions(['cookies' => $cookieJar])
            ->timeout($this->timeout)
            ->withHeaders([
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])
            ->get('https://fluxui.dev/login');

        abort_unless($loginPage->successful(), 1, 'Could not reach fluxui.dev/login');

        $csrfToken = $this->extractCsrfToken($loginPage->body());

        abort_if(blank($csrfToken), 1, 'Could not extract CSRF token from login page.');

        $snapshot = $this->extractWireSnapshot($loginPage->body());
        $updateUri = $this->extractLivewireUpdateUri($loginPage->body());

        abort_if(blank($snapshot), 1, 'Could not extract Livewire snapshot from login page.');
        abort_if(blank($updateUri), 1, 'Could not find Livewire update endpoint URL.');

        $response = Http::withOptions(['cookies' => $cookieJar])
            ->timeout($this->timeout)
            ->withHeaders([
                'Accept'     => 'application/json',
                'Referer'    => 'https://fluxui.dev/login',
                'X-Livewire' => 'true',
            ])
            ->post($updateUri, [
                '_token'     => $csrfToken,
                'components' => [
                    [
                        'snapshot' => $snapshot,
                        'updates'  => [
                            'email'    => $email,
                            'password' => $password,
                        ],
                        'calls' => [
                            [
                                'method'   => 'login',
                                'params'   => [],
                                'metadata' => (object) [],
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed())
        {
            return null;
        }

        return $cookieJar;
    }

    /**
     * Extract the raw CSRF token from the Livewire script tag's csrf="..." attribute.
     *
     * @param  string  $html
     * @return string|null
     */
    protected function extractCsrfToken(string $html): ?string
    {
        if (preg_match('/\bcsrf="([^"]{32,})"/', $html, $matches))
        {
            return $matches[1];
        }

        if (preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i', $html, $matches))
        {
            return $matches[1];
        }

        if (preg_match('/<input[^>]+name=["\']_token["\']\s+value=["\']([^"\']+)["\']/i', $html, $matches))
        {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract the wire:snapshot JSON string from a Livewire component HTML page.
     *
     * @param  string  $html
     * @return string|null
     */
    protected function extractWireSnapshot(string $html): ?string
    {
        if (preg_match('/wire:snapshot="([^"]+)"/s', $html, $matches))
        {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    /**
     * Extract the Livewire update endpoint URL from the script tag's data-update-uri attribute.
     *
     * @param  string  $html
     * @return string|null
     */
    protected function extractLivewireUpdateUri(string $html): ?string
    {
        if (preg_match('/data-update-uri="([^"]+)"/', $html, $matches))
        {
            return $matches[1];
        }

        if (preg_match('#/(livewire[^"\s\']*)/update#', $html, $matches))
        {
            return 'https://fluxui.dev' . $matches[0];
        }

        return null;
    }
}
