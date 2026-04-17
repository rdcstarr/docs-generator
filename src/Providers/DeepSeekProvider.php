<?php

namespace Rdcstarr\DocsGenerator\Providers;

use DeepSeek\DeepSeekClient;
use Rdcstarr\DocsGenerator\Contracts\AIProvider;

class DeepSeekProvider implements AIProvider
{
    /**
     * The underlying DeepSeek client.
     *
     * @var \DeepSeek\DeepSeekClient
     */
    protected DeepSeekClient $client;

    /**
     * The model name used for generation.
     *
     * @var string
     */
    protected string $model;

    /**
     * Create a new DeepSeekProvider instance.
     *
     * @param  array{api_key: ?string, base_url: ?string, model: ?string, timeout: int|string|null}  $config
     * @return void
     */
    public function __construct(array $config)
    {
        abort_if(blank($config['api_key'] ?? null), 1, 'DeepSeek provider requires DEEPSEEK_API_KEY in .env.');

        $this->client = DeepSeekClient::build(
            apiKey: (string) $config['api_key'],
            baseUrl: (string) ($config['base_url'] ?? ''),
            timeout: (int) ($config['timeout'] ?? 60),
        );

        $this->model = (string) ($config['model'] ?? 'deepseek-chat');
    }

    /**
     * Generate Markdown content for the given prompt.
     *
     * @param  string  $prompt
     * @return string|null
     */
    public function generate(string $prompt): ?string
    {
        $response = $this->client->query($prompt, 'user')->withModel($this->model)->run();
        $decoded = json_decode($response, true);

        return $decoded['choices'][0]['message']['content'] ?? null;
    }
}
