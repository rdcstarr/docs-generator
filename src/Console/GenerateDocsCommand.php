<?php

namespace Rdcstarr\DocsGenerator\Console;

use Illuminate\Console\Command;
use Rdcstarr\DocsGenerator\Contracts\AIProvider;
use Rdcstarr\DocsGenerator\Contracts\DocsDriver;
use Rdcstarr\DocsGenerator\Contracts\Target;
use Rdcstarr\DocsGenerator\Orchestrator;

class GenerateDocsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:generate
                            {source? : Source key (e.g. laravel, flux, livewire); omit to run all enabled sources}
                            {--for= : Target IDE (claude, cursor, copilot)}
                            {--force : Overwrite existing files}
                            {--only= : Comma-separated list of page slugs to process}
                            {--provider= : AI provider to use (e.g. deepseek, openai)}
                            {--sync-only : Only update the index, skip doc generation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate AI-optimized Markdown documentation for Claude, Cursor, or Copilot.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $sources = $this->resolveSources();
        $targetKey = (string) ($this->option('for') ?: config('docs-generator.default_target'));
        $providerKey = (string) ($this->option('provider') ?: config('docs-generator.default_provider'));

        $target = $this->resolveTarget($targetKey);
        $provider = $this->resolveProvider($providerKey);

        $options = [
            'force'            => (bool) $this->option('force'),
            'only'             => $this->option('only') ?: null,
            'sync_only'        => (bool) $this->option('sync-only'),
            'retry_attempts'   => (int) config('docs-generator.retry_attempts', 3),
            'retry_sleep_ms'   => (int) config('docs-generator.retry_sleep_ms', 2000),
            'throttle_seconds' => (int) config('docs-generator.throttle_seconds', 1),
            'target_key'       => $targetKey,
        ];

        foreach ($sources as $sourceKey)
        {
            $driver = $this->resolveDriver($sourceKey);

            $this->newLine();
            $this->info("━━━ [{$sourceKey}] ━━━");

            (new Orchestrator)
                ->withCommand($this)
                ->run($driver, $provider, $target, $options);
        }
    }

    /**
     * Resolve the list of source keys to run.
     *
     * @return array<int, string>
     */
    protected function resolveSources(): array
    {
        $source = $this->argument('source');

        if (filled($source))
        {
            abort_unless(
                config()->has("docs-generator.sources.{$source}"),
                1,
                "Unknown source [{$source}]. Available: " . collect(config('docs-generator.sources'))->keys()->implode(', ')
            );

            return [(string) $source];
        }

        return collect(config('docs-generator.sources'))->keys()->all();
    }

    /**
     * Resolve a DocsDriver instance from config.
     *
     * @param  string  $key
     * @return \Rdcstarr\DocsGenerator\Contracts\DocsDriver
     */
    protected function resolveDriver(string $key): DocsDriver
    {
        $config = config("docs-generator.sources.{$key}");

        abort_if(blank($config), 1, "Source [{$key}] is not configured.");

        $class = $config['driver'] ?? null;

        abort_if(blank($class), 1, "Source [{$key}] has no driver class.");

        $config['timeout']        = config('docs-generator.timeout', 30);
        $config['max_html_bytes'] = config('docs-generator.max_html_bytes', 200_000);

        return new $class($config);
    }

    /**
     * Resolve an AIProvider instance from config.
     *
     * @param  string  $key
     * @return \Rdcstarr\DocsGenerator\Contracts\AIProvider
     */
    protected function resolveProvider(string $key): AIProvider
    {
        $config = config("docs-generator.providers.{$key}");

        abort_if(blank($config), 1, "Provider [{$key}] is not configured.");

        $class = $config['class'] ?? null;

        abort_if(blank($class), 1, "Provider [{$key}] has no class.");

        return new $class($config);
    }

    /**
     * Resolve a Target instance from config.
     *
     * @param  string  $key
     * @return \Rdcstarr\DocsGenerator\Contracts\Target
     */
    protected function resolveTarget(string $key): Target
    {
        $config = config("docs-generator.targets.{$key}");

        abort_if(blank($config), 1, "Target [{$key}] is not configured.");

        $class = $config['class'] ?? null;

        abort_if(blank($class), 1, "Target [{$key}] has no class.");

        return new $class($config);
    }
}
