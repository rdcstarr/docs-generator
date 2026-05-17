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
     * Marker delimiting the start of the docs-generator managed block in the root index file.
     *
     * @var string
     */
    protected const BLOCK_START = '<!-- docs-generator:start -->';

    /**
     * Marker delimiting the end of the docs-generator managed block in the root index file.
     *
     * @var string
     */
    protected const BLOCK_END = '<!-- docs-generator:end -->';

    /**
     * Write the per-source index file and upsert the pointer line in the root managed block.
     *
     * @param  string  $source
     * @param  string  $sectionHeading
     * @return void
     */
    public function syncIndex(string $source, string $sectionHeading): void
    {
        $label = str($sectionHeading)->after('## ')->trim()->toString();
        $outputDir = $this->outputDir($source);
        $indexPath = $outputDir . '/index.md';

        File::put($indexPath, $this->buildSourceIndex($outputDir, $label));

        $this->upsertRootPointer($label, $this->relativeFromBase($indexPath));
    }

    /**
     * Build the Markdown body for the per-source index.md file.
     *
     * @param  string  $outputDir
     * @param  string  $label
     * @return string
     */
    protected function buildSourceIndex(string $outputDir, string $label): string
    {
        $docPrefix = $this->relativeFromBase($outputDir);

        $entries = collect(File::files($outputDir))
            ->map(fn (\SplFileInfo $file): string => $file->getFilename())
            ->reject(fn (string $filename): bool => $filename === 'index.md')
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

        $intro = '> Read on demand. Load only the file relevant to the current task — do NOT load all files at once.';

        return "# {$label} documentation\n\n"
            . $intro . "\n\n"
            . "Available files in `{$docPrefix}/`:\n"
            . $entries->implode("\n") . "\n";
    }

    /**
     * Upsert the pointer line for the given source inside the root managed block.
     *
     * @param  string  $label
     * @param  string  $indexRelPath
     * @return void
     */
    protected function upsertRootPointer(string $label, string $indexRelPath): void
    {
        $pattern = '/' . preg_quote(self::BLOCK_START, '/') . '[\s\S]*?' . preg_quote(self::BLOCK_END, '/') . '/';

        $content = File::exists($this->indexFile) ? File::get($this->indexFile) : '';

        $pointers = [];

        if (preg_match($pattern, $content, $matches))
        {
            preg_match_all('/^- \*\*(.+?)\*\* → `(.+?)`$/m', $matches[0], $rows, PREG_SET_ORDER);

            foreach ($rows as $row)
            {
                $pointers[$row[1]] = $row[2];
            }
        }

        $pointers[$label] = $indexRelPath;
        ksort($pointers);

        $lines = collect($pointers)
            ->map(fn (string $path, string $lbl): string => "- **{$lbl}** → `{$path}`")
            ->implode("\n");

        $block = self::BLOCK_START . "\n"
            . "## Generated documentation\n\n"
            . "Indexes maintained by docs-generator. Load on demand:\n\n"
            . $lines . "\n"
            . self::BLOCK_END;

        if (preg_match($pattern, $content))
        {
            $content = preg_replace($pattern, $block, $content, 1);
        }
        else
        {
            $trimmed = str($content)->rtrim()->toString();
            $content = filled($trimmed) ? $trimmed . "\n\n" . $block . "\n" : $block . "\n";
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
