<?php

namespace Rdcstarr\DocsGenerator\Contracts;

interface Target
{
    /**
     * Return the absolute output directory for the given source key.
     *
     * @param  string  $source
     * @return string
     */
    public function outputDir(string $source): string;

    /**
     * Return the absolute file path where the generated Markdown should be written.
     *
     * @param  string  $source
     * @param  string  $slug
     * @return string
     */
    public function outputPath(string $source, string $slug): string;

    /**
     * Wrap the Markdown content with any target-specific frontmatter or metadata.
     *
     * @param  string  $content
     * @param  string  $source
     * @return string
     */
    public function wrapContent(string $content, string $source): string;

    /**
     * Synchronise the index for the given source (no-op when the target has no index).
     *
     * @param  string  $source
     * @param  string  $sectionHeading
     * @return void
     */
    public function syncIndex(string $source, string $sectionHeading): void;
}
