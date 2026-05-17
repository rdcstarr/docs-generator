# docs-generator

Generate AI-optimized Markdown documentation from Laravel-ecosystem docs sites (Laravel, Flux UI, Livewire) for [Claude Code](https://claude.com/claude-code), [Cursor](https://cursor.sh), and [GitHub Copilot](https://github.com/features/copilot).

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rdcstarr/docs-generator.svg?style=flat-square)](https://packagist.org/packages/rdcstarr/docs-generator)
[![License](https://img.shields.io/packagist/l/rdcstarr/docs-generator.svg?style=flat-square)](LICENSE)

## Why

When you work with AI coding assistants in Laravel projects, the model answers better when it has the official docs close at hand — sliced into small Markdown files you can load on demand. This package fetches the public docs, asks an LLM to rewrite each page as a clean, AI-friendly Markdown file, and writes it into the right folder for your IDE (`.claude/`, `.cursor/rules/`, or `.github/instructions/`).

## Features

- Three built-in sources: **Laravel**, **Flux UI** (with authentication for Flux Pro), and **Livewire**
- Three IDE targets: **Claude** (with auto-sync of `CLAUDE.md` index), **Cursor** (`.mdc` with frontmatter), **Copilot** (`.instructions.md`)
- Uses the **native Laravel AI SDK** (`laravel/ai`) — 10+ providers out of the box: DeepSeek, OpenAI, Anthropic, Gemini, Groq, xAI, Mistral, Ollama, Cohere, and more
- Configurable per-project: enable only the sources you use
- Smart retry with rate-limit backoff
- Skip-existing with `--force` to regenerate
- Filter with `--only=routing,eloquent` during development

## Installation

```bash
composer require rdcstarr/docs-generator
```

Publish the Laravel AI SDK config and run its migrations (used internally by `laravel/ai`):

```bash
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate
```

Then publish this package's config:

```bash
php artisan vendor:publish --tag=docs-generator-config
```

## Setup

Add the credentials for whichever AI provider and sources you use in `.env`. API keys are read by the Laravel AI SDK, so you can use any provider it supports:

```ini
# DeepSeek (default)
DEEPSEEK_API_KEY=sk-...

# OpenAI
OPENAI_API_KEY=sk-...

# Anthropic
ANTHROPIC_API_KEY=sk-ant-...

# Gemini
GEMINI_API_KEY=...

# Required only if you use the Flux UI source (for Flux Pro access)
FLUXUI_EMAIL=you@example.com
FLUXUI_PASSWORD=your-password
```

In `config/docs-generator.php`, keep only the sources you want to generate. For a plain Laravel project without Flux/Livewire:

```php
'sources' => [
    'laravel' => [
        'driver'  => \Rdcstarr\DocsGenerator\Drivers\LaravelDriver::class,
        'version' => '13.x',
    ],
],
```

## Usage

Generate docs for all enabled sources, for Claude (the default target):

```bash
php artisan docs:generate
```

Generate only one source:

```bash
php artisan docs:generate laravel
php artisan docs:generate flux
php artisan docs:generate livewire
```

Target Cursor or Copilot instead of Claude:

```bash
php artisan docs:generate --for=cursor
php artisan docs:generate laravel --for=copilot
```

Force regeneration of already-generated files:

```bash
php artisan docs:generate --force
```

Generate only specific pages (matches by slug, case-insensitive, substring):

```bash
php artisan docs:generate laravel --only=routing,eloquent
```

Use a different AI provider for this run (any key from `config/docs-generator.php`):

```bash
php artisan docs:generate --provider=openai
php artisan docs:generate --provider=anthropic
php artisan docs:generate --provider=gemini
```

Re-sync the `CLAUDE.md` index without generating any files:

```bash
php artisan docs:generate --sync-only
php artisan docs:generate laravel --sync-only
```

## Output

### Claude target (default)

```
.claude/
├── laravel/
│   ├── index.md         ← per-source index (filenames + H1 titles)
│   ├── routing.md
│   ├── eloquent.md
│   └── ...
├── flux/
│   ├── index.md
│   └── ...
└── livewire/
    ├── index.md
    └── ...
CLAUDE.md                ← single managed block pointing at the per-source indexes
```

Each source gets its own `index.md` listing every generated file with its H1 title. Your root `CLAUDE.md` is touched only between markers:

```markdown
<!-- docs-generator:start -->
## Generated documentation

Indexes maintained by docs-generator. Load on demand:

- **Flux UI** → `.claude/flux/index.md`
- **Laravel** → `.claude/laravel/index.md`
- **Livewire** → `.claude/livewire/index.md`
<!-- docs-generator:end -->
```

Anything outside `<!-- docs-generator:start -->` … `<!-- docs-generator:end -->` is preserved untouched, so it's safe to keep your own project instructions in the same file.

### Cursor target

```
.cursor/rules/
├── laravel.routing.mdc       (with Cursor frontmatter)
├── fluxui.button.mdc
└── livewire.actions.mdc
```

### Copilot target

```
.github/instructions/
├── laravel.routing.instructions.md
├── fluxui.button.instructions.md
└── livewire.actions.instructions.md
```

## Extending

### Add a new AI provider entry

Most providers you'd want are already available natively through the Laravel AI SDK (OpenAI, Anthropic, Gemini, Groq, xAI, Mistral, Ollama, Cohere, DeepSeek, …). To add another entry to `config/docs-generator.php`, point `class` at `LaravelAiProvider` and set the SDK `provider` key plus the model you want:

```php
'providers' => [
    'groq' => [
        'class'    => \Rdcstarr\DocsGenerator\Providers\LaravelAiProvider::class,
        'provider' => 'groq',
        'model'    => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'timeout'  => env('GROQ_TIMEOUT', 60),
    ],
],
```

Then add `GROQ_API_KEY=...` to `.env` and run `php artisan docs:generate --provider=groq`.

### Add a fully custom AI provider

If you need a service not supported by the Laravel AI SDK, implement `Rdcstarr\DocsGenerator\Contracts\AIProvider`:

```php
namespace App\DocsGenerator;

use Rdcstarr\DocsGenerator\Contracts\AIProvider;

class MyCustomProvider implements AIProvider
{
    public function __construct(array $config) { /* ... */ }

    public function generate(string $prompt): ?string
    {
        // call your API, return Markdown
    }
}
```

Register it under `providers` and use it via `--provider=mycustom`.

### Add a custom documentation source

Implement `Rdcstarr\DocsGenerator\Contracts\DocsDriver` — five methods:

```php
namespace App\DocsGenerator;

use Rdcstarr\DocsGenerator\Contracts\DocsDriver;

class FilamentDriver implements DocsDriver
{
    public function __construct(array $config) { /* ... */ }

    public function name(): string          { return 'filament'; }
    public function indexSection(): string  { return '## Filament'; }
    public function discoverPages(): array  { /* fetch + parse sidebar */ }
    public function fetchPage(string $url): ?string { /* fetch + clean HTML */ }
    public function buildPrompt(string $url, string $html): string { /* prompt */ }
}
```

Register it under `sources` in the config. Helpers `Rdcstarr\DocsGenerator\Support\HtmlExtractor` and `SidebarDiscovery` are available to keep your driver small.

### Add a custom IDE target

Implement `Rdcstarr\DocsGenerator\Contracts\Target` and register under `targets`.

## Requirements

- PHP **8.3+**
- Laravel **13+**
- `laravel/ai` (installed automatically as a dependency)

## License

MIT © [rdcstarr](https://github.com/rdcstarr)
