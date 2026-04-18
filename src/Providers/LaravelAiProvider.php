<?php

namespace Rdcstarr\DocsGenerator\Providers;

use Laravel\Ai\Enums\Lab;
use Rdcstarr\DocsGenerator\Contracts\AIProvider;

use function Laravel\Ai\agent;

class LaravelAiProvider implements AIProvider
{
    protected Lab $provider;
    protected ?string $model;
    protected int $timeout;

    /**
     * @param  array{provider: ?string, model: ?string, timeout: ?int}  $config
     */
    public function __construct(array $config)
    {
        $providerKey = (string) ($config['provider'] ?? 'openai');

        $this->provider = Lab::from($providerKey);
        $this->model    = $config['model'] ?? null;
        $this->timeout  = (int) ($config['timeout'] ?? 60);
    }

    public function generate(string $prompt): ?string
    {
        $response = agent(
            instructions: 'You are an expert technical writer. Output clean, concise Markdown only. No preface, no meta commentary.',
        )->prompt(
            $prompt,
            provider: $this->provider,
            model:    $this->model,
            timeout:  $this->timeout,
        );

        return $response->text ?? null;
    }
}
