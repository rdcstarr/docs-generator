# docs-generator

Generate AI-optimized Markdown documentation from Laravel-ecosystem docs sites (Laravel, Flux UI, Livewire) for [Claude Code](https://claude.com/claude-code), [Cursor](https://cursor.sh), and [GitHub Copilot](https://github.com/features/copilot).

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rdcstarr/docs-generator.svg?style=flat-square)](https://packagist.org/packages/rdcstarr/docs-generator)
[![License](https://img.shields.io/packagist/l/rdcstarr/docs-generator.svg?style=flat-square)](LICENSE)

## Why

When you work with AI coding assistants in Laravel projects, the model answers better when it has the official docs close at hand — sliced into small Markdown files you can load on demand. This package fetches the public docs, asks an LLM to rewrite each page as a clean, AI-friendly Markdown file, and writes it into the right folder for your IDE (`.claude/`, `.cursor/rules/`, or `.github/instructions/`).

## Features

- Three built-in sources: **Laravel**, **Flux UI** (with authentication for Flux Pro), and **Livewire**
- Three IDE targets: **Claude** (with auto-sync of `CLAUDE.md` index), **Cursor** (`.mdc` with frontmatter), **Copilot** (`.instructions.md`)
- Pluggable AI providers: **DeepSeek** and **OpenAI** out of the box, add your own by implementing an interface
- Configurable per-project: enable only the sources you use
- Smart retry with rate-limit backoff
- Skip-existing with `--force` to regenerate
- Filter with `--only=routing,eloquent` during development

## Installation

```bash
composer require rdcstarr/docs-generator
```

Then publish the config file:

```bash
php artisan vendor:publish --tag=docs-generator-config
```

## Setup

Add the credentials for whichever AI provider and sources you use in `.env`:

```ini
# DeepSeek (default provider)
DEEPSEEK_API_KEY=sk-...

# OR OpenAI
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o

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

Use a different AI provider for this run:

```bash
php artisan docs:generate --provider=openai
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
│   ├── routing.md
│   ├── eloquent.md
│   └── ...
├── fluxui/
│   └── ...
└── livewire/
    └── ...
CLAUDE.md                ← auto-updated with per-source index sections
```

`CLAUDE.md` gets a `## Laravel` / `## Flux UI` / `## Livewire` section listing every generated file with its H1 title, so the model can pick the right file on demand.

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

### Add a custom AI provider

Implement `Rdcstarr\DocsGenerator\Contracts\AIProvider`:

```php
namespace App\DocsGenerator;

use Rdcstarr\DocsGenerator\Contracts\AIProvider;

class AnthropicProvider implements AIProvider
{
    public function __construct(array $config) { /* ... */ }

    public function generate(string $prompt): ?string
    {
        // call Anthropic API, return Markdown
    }
}
```

Register it in `config/docs-generator.php`:

```php
'providers' => [
    'anthropic' => [
        'class'   => \App\DocsGenerator\AnthropicProvider::class,
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model'   => 'claude-opus-4-7',
    ],
],
```

Use it: `php artisan docs:generate --provider=anthropic`.

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
- Laravel **11, 12, or 13**

## License

MIT © [rdcstarr](https://github.com/rdcstarr)
