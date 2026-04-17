<?php

namespace Rdcstarr\DocsGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Rdcstarr\DocsGenerator\Contracts\AIProvider;
use Rdcstarr\DocsGenerator\Contracts\DocsDriver;
use Rdcstarr\DocsGenerator\Contracts\Target;
use Rdcstarr\DocsGenerator\Support\Retry;
use Rdcstarr\DocsGenerator\Support\SidebarDiscovery;

class Orchestrator
{
    /**
     * Optional console command used for progress output.
     *
     * @var \Illuminate\Console\Command|null
     */
    protected ?Command $command = null;

    /**
     * Retry service.
     *
     * @var \Rdcstarr\DocsGenerator\Support\Retry
     */
    protected Retry $retry;

    /**
     * Sidebar discovery service (used for filterByOnly).
     *
     * @var \Rdcstarr\DocsGenerator\Support\SidebarDiscovery
     */
    protected SidebarDiscovery $discovery;

    /**
     * Create a new Orchestrator instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->retry = new Retry;
        $this->discovery = new SidebarDiscovery;
    }

    /**
     * Attach a console command for progress output.
     *
     * @param  \Illuminate\Console\Command  $command
     * @return static
     */
    public function withCommand(Command $command): static
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Run the full documentation generation flow for a single driver.
     *
     * @param  \Rdcstarr\DocsGenerator\Contracts\DocsDriver  $driver
     * @param  \Rdcstarr\DocsGenerator\Contracts\AIProvider  $provider
     * @param  \Rdcstarr\DocsGenerator\Contracts\Target  $target
     * @param  array{force?: bool, only?: ?string, sync_only?: bool, retry_attempts?: int, retry_sleep_ms?: int, throttle_seconds?: int, target_key?: string}  $options
     * @return array{succeeded: int, failed: array<int, string>, total: int}
     */
    public function run(DocsDriver $driver, AIProvider $provider, Target $target, array $options = []): array
    {
        $force = (bool) ($options['force'] ?? false);
        $only = $options['only'] ?? null;
        $syncOnly = (bool) ($options['sync_only'] ?? false);
        $retryAttempts = (int) ($options['retry_attempts'] ?? 3);
        $retrySleepMs = (int) ($options['retry_sleep_ms'] ?? 2000);
        $throttleSeconds = (int) ($options['throttle_seconds'] ?? 1);
        $targetKey = (string) ($options['target_key'] ?? 'claude');

        $outputDir = $target->outputDir($driver->name());

        File::ensureDirectoryExists($outputDir);

        if ($syncOnly)
        {
            $this->info('Syncing index...');
            $target->syncIndex($driver->name(), $driver->indexSection());
            $this->info('Done!');

            return ['succeeded' => 0, 'failed' => [], 'total' => 0];
        }

        $this->info("Discovering pages for [{$driver->name()}]...");
        $pages = $driver->discoverPages();

        abort_if(blank($pages), 1, "Could not discover any documentation pages for [{$driver->name()}].");

        if (filled($only))
        {
            $pages = $this->discovery->filterByOnly($pages, $only);
        }

        $total = count($pages);
        $succeeded = 0;
        $failed = [];

        $this->info("Starting generation (target: {$targetKey}, pages: {$total})...");
        $this->newLine();

        foreach ($pages as $index => $page)
        {
            $current = $index + 1;
            $slug = Str::before($page['filename'], '.md');
            $outputFile = $target->outputPath($driver->name(), $slug);

            $this->line("[{$current}/{$total}] Processing: {$page['filename']}");

            if (File::exists($outputFile) && filled(File::get($outputFile)) && ! $force)
            {
                $this->line('  <fg=yellow>⏭  Already exists, skipping.</> (use --force to regenerate)');
                $succeeded++;

                continue;
            }

            try
            {
                $html = $this->retry->withRetry(
                    function () use ($driver, $page): string
                    {
                        $result = $driver->fetchPage($page['url']);
                        throw_if(blank($result), \RuntimeException::class, 'Failed to fetch page.');

                        return $result;
                    },
                    $retryAttempts,
                    $retrySleepMs,
                    fn (string $msg) => $this->line($msg),
                );
            }
            catch (\Throwable $e)
            {
                $this->line('  <fg=red>✗ Failed to fetch page: ' . $e->getMessage() . '</>');
                $failed[] = $page['filename'];

                Sleep::for($throttleSeconds)->second();

                continue;
            }

            try
            {
                $markdown = $this->retry->withRetry(
                    function () use ($driver, $provider, $page, $html): string
                    {
                        $prompt = $driver->buildPrompt($page['url'], $html);
                        $result = $provider->generate($prompt);
                        throw_if(blank($result), \RuntimeException::class, 'Empty AI response.');

                        return $result;
                    },
                    $retryAttempts,
                    $retrySleepMs,
                    fn (string $msg) => $this->line($msg),
                );
            }
            catch (\Throwable $e)
            {
                $this->line('  <fg=red>✗ AI failed: ' . $e->getMessage() . '</>');
                $failed[] = $page['filename'];

                Sleep::for($throttleSeconds)->second();

                continue;
            }

            $markdown = $target->wrapContent($markdown, $driver->name());

            File::put($outputFile, $markdown);

            $size = Str::of(File::size($outputFile))->toString();
            $this->line("  <fg=green>✓ Done ({$size} bytes)</>");

            $succeeded++;

            Sleep::for($throttleSeconds)->second();
        }

        $this->newLine();
        $this->line('============================================');
        $this->info("Completed: {$succeeded}/{$total} files generated");
        $this->info("Output: {$outputDir}");

        if (filled($failed))
        {
            $this->newLine();
            $this->error('Failed files (' . count($failed) . '):');

            foreach ($failed as $filename)
            {
                $this->line("  - {$filename}");
            }
        }

        $this->newLine();
        $this->info('Syncing index...');
        $target->syncIndex($driver->name(), $driver->indexSection());
        $this->info('Done!');

        return ['succeeded' => $succeeded, 'failed' => $failed, 'total' => $total];
    }

    /**
     * Print an info-level message to the attached command.
     *
     * @param  string  $message
     * @return void
     */
    protected function info(string $message): void
    {
        $this->command?->info($message);
    }

    /**
     * Print a raw line to the attached command.
     *
     * @param  string  $message
     * @return void
     */
    protected function line(string $message): void
    {
        $this->command?->line($message);
    }

    /**
     * Print an error-level message to the attached command.
     *
     * @param  string  $message
     * @return void
     */
    protected function error(string $message): void
    {
        $this->command?->error($message);
    }

    /**
     * Print a blank line to the attached command.
     *
     * @return void
     */
    protected function newLine(): void
    {
        $this->command?->newLine();
    }
}
