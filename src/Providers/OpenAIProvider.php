<?php

namespace Rdcstarr\DocsGenerator\Providers;

use OpenAI;
use OpenAI\Contracts\ClientContract;
use Rdcstarr\DocsGenerator\Contracts\AIProvider;

class OpenAIProvider implements AIProvider
{
    /**
     * The underlying OpenAI client.
     *
     * @var \OpenAI\Contracts\ClientContract
     */
    protected ClientContract $client;

    /**
     * The model name used for generation.
     *
     * @var string
     */
    protected string $model;

    /**
     * Maximum completion tokens per response.
     *
     * @var int
     */
    protected int $maxTokens;

    /**
     * Create a new OpenAIProvider instance.
     *
     * @param  array{api_key: ?string, model: ?string}  $config
     * @param  int  $maxTokens
     * @return void
     */
    public function __construct(array $config, int $maxTokens = 16384)
    {
        abort_if(blank($config['api_key'] ?? null), 1, 'OpenAI provider requires OPENAI_API_KEY in .env.');

        $this->client = OpenAI::client((string) $config['api_key']);
        $this->model = (string) ($config['model'] ?? 'gpt-4o');
        $this->maxTokens = $maxTokens;
    }

    /**
     * Generate Markdown content for the given prompt.
     *
     * @param  string  $prompt
     * @return string|null
     */
    public function generate(string $prompt): ?string
    {
        $response = $this->client->chat()->create([
            'model'                 => $this->model,
            'messages'              => [['role' => 'user', 'content' => $prompt]],
            'max_completion_tokens' => $this->maxTokens,
        ]);

        return $response->choices[0]->message->content ?? null;
    }
}
