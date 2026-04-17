<?php

namespace Rdcstarr\DocsGenerator\Targets;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Rdcstarr\DocsGenerator\Contracts\Target;

class ClaudeTarget implements Target
{
    /**
     * Output directory template; the `{source}` placeholder is replaced per driver.
     *
     * @var string
     */
    protected string $outputDir;

    /**
     * Absolute path to the CLAUDE.md index file.
     *
     * @var string
     */
    protected string $indexFile;

    /**
     * Create a new ClaudeTarget instance.
     *
     * @param  array{output_dir: string, index_file: string}  $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->outputDir = (string) $config['output_dir'];
        $this->indexFile = (string) $config['index_file'];
    }

    /**
     * Return the absolute output directory for the given source key.
     *
     * @param  string  $source
     * @return string
     */
    public function outputDir(string $source): string
    {
        return Str::replace('{source}', $source, $this->outputDir);
    }

    /**
     * Return the absolute file path where the generated Markdown should be written.
     *
     * @param  string  $source
     * @param  string  $slug
     * @return string
     */
    public function outputPath(string $source, string $slug): string
    {
        return $this->outputDir($source) . '/' . $slug . '.md';
    }

    /**
     * Claude uses raw Markdown without frontmatter.
     *
     * @param  string  $content
     * @param  string  $source
     * @return string
     */
    public function wrapContent(string $content, string $source): string
    {
        return $content;
    }

    /**
     * Synchronise the CLAUDE.md index section for the given source.
     *
     * @param  string  $source
     * @param  string  $sectionHeading
     * @return void
     */
    public function syncIndex(string $source, string $sectionHeading): void
    {
        $outputDir = $this->outputDir($source);
        $docPrefix = $this->relativeFromBase($outputDir);

        $entries = collect(File::files($outputDir))
            ->map(fn (\SplFileInfo $file): string => $file->getFilename())
            ->sort()
            ->values()
            ->map(function (string $filename) use ($outputDir): string
            {
                $filePath = $outputDir . DIRECTORY_SEPARATOR . $filename;
                $firstLine = Str::of(File::get($filePath))
                    ->explode("\n")
                    ->first(fn (string $line): bool => Str::startsWith($line, '# '));
                $description = filled($firstLine)
                    ? Str::after($firstLine, '# ')
                    : Str::title(Str::replace('-', ' ', Str::before($filename, '.md')));

                return "- `{$filename}` — {$description}";
            });

        $intro = '> Read on demand with the Read tool. Load only the file relevant to the current task — do NOT load all files at once.';

        $block = $sectionHeading . "\n\n"
            . $intro . "\n\n"
            . "Available files in `{$docPrefix}/`:\n"
            . $entries->implode("\n");

        $content = File::exists($this->indexFile) ? File::get($this->indexFile) : '';
        $escaped = preg_quote($sectionHeading, '/');
        $pattern = '/' . $escaped . '[\s\S]*?(?=\n## |\z)/';

        if (preg_match($pattern, $content))
        {
            $content = preg_replace_callback($pattern, fn (): string => $block . "\n", $content);
        }
        else
        {
            $content = str($content)->rtrim()->toString() . "\n\n" . $block . "\n";
        }

        File::put($this->indexFile, $content);
    }

    /**
     * Return a path relative to the base path (for display in the index).
     *
     * @param  string  $path
     * @return string
     */
    protected function relativeFromBase(string $path): string
    {
        $base = base_path();

        if (Str::startsWith($path, $base))
        {
            return str(Str::after($path, $base))->ltrim('/\\')->toString();
        }

        return $path;
    }
}
