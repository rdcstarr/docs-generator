<?php

namespace Rdcstarr\DocsGenerator\Targets;

use Illuminate\Support\Str;
use Rdcstarr\DocsGenerator\Contracts\Target;

class CursorTarget implements Target
{
    /**
     * Absolute output directory.
     *
     * @var string
     */
    protected string $outputDir;

    /**
     * Create a new CursorTarget instance.
     *
     * @param  array{output_dir: string}  $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->outputDir = (string) $config['output_dir'];
    }

    /**
     * Return the absolute output directory for the given source key.
     *
     * @param  string  $source
     * @return string
     */
    public function outputDir(string $source): string
    {
        return $this->outputDir;
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
        return $this->outputDir($source) . '/' . $source . '.' . $slug . '.mdc';
    }

    /**
     * Prepend Cursor-compatible frontmatter to the Markdown content.
     *
     * @param  string  $content
     * @param  string  $source
     * @return string
     */
    public function wrapContent(string $content, string $source): string
    {
        $description = $this->extractDescription($content);
        $globs = '**/*.blade.php, **/*.php';

        return "---\ndescription: \"{$description}\"\nglobs: {$globs}\nalwaysApply: false\n---\n\n{$content}";
    }

    /**
     * Cursor does not use an index file.
     *
     * @param  string  $source
     * @param  string  $sectionHeading
     * @return void
     */
    public function syncIndex(string $source, string $sectionHeading): void
    {
        //
    }

    /**
     * Extract a short description from the first non-empty paragraph after the H1.
     *
     * @param  string  $content
     * @return string
     */
    protected function extractDescription(string $content): string
    {
        $lines = Str::of($content)->explode("\n");
        $description = $lines
            ->skipUntil(fn (string $l): bool => Str::startsWith($l, '# '))
            ->skip(1)
            ->first(fn (string $l): bool => filled(str($l)->trim()->toString()));

        return Str::limit(str($description ?? '')->trim()->toString(), 200);
    }
}
