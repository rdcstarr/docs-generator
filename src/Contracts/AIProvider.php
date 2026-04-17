<?php

namespace Rdcstarr\DocsGenerator\Contracts;

interface AIProvider
{
    /**
     * Generate Markdown content for the given prompt.
     *
     * @param  string  $prompt
     * @return string|null
     */
    public function generate(string $prompt): ?string;
}
