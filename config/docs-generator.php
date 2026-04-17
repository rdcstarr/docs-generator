<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default AI provider used to generate Markdown from fetched HTML.
    | Must match a key in the "providers" array below. Can be overridden
    | per-command via the --provider option.
    |
    */

    'default_provider' => env('DOCS_PROVIDER', 'deepseek'),

    /*
    |--------------------------------------------------------------------------
    | Default Target IDE
    |--------------------------------------------------------------------------
    |
    | The default IDE target for generated docs. Must match a key in the
    | "targets" array below. Can be overridden per-command via --for.
    |
    */

    'default_target' => env('DOCS_TARGET', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Map of available AI providers. Each entry resolves to a class that
    | implements Rdcstarr\DocsGenerator\Contracts\AIProvider. Register
    | custom providers by adding new entries to this array.
    |
    */

    'providers' => [

        'deepseek' => [
            'class'    => \Rdcstarr\DocsGenerator\Providers\DeepSeekProvider::class,
            'api_key'  => env('DEEPSEEK_API_KEY'),
            'base_url' => env('DEEPSEEK_BASE_URL'),
            'model'    => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'timeout'  => env('DEEPSEEK_TIMEOUT', 60),
        ],

        'openai' => [
            'class'   => \Rdcstarr\DocsGenerator\Providers\OpenAIProvider::class,
            'api_key' => env('OPENAI_API_KEY'),
            'model'   => env('OPENAI_MODEL', 'gpt-4o'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Sources
    |--------------------------------------------------------------------------
    |
    | Map of sources to generate docs for. Each entry resolves to a driver
    | that implements Rdcstarr\DocsGenerator\Contracts\DocsDriver. Comment
    | out or remove entries you do not use in your project. Register custom
    | sources by implementing DocsDriver and adding a new entry.
    |
    */

    'sources' => [

        'laravel' => [
            'driver'  => \Rdcstarr\DocsGenerator\Drivers\LaravelDriver::class,
            'version' => '13.x',
        ],

        'flux' => [
            'driver'   => \Rdcstarr\DocsGenerator\Drivers\FluxDriver::class,
            'email'    => env('FLUXUI_EMAIL'),
            'password' => env('FLUXUI_PASSWORD'),
        ],

        'livewire' => [
            'driver'  => \Rdcstarr\DocsGenerator\Drivers\LivewireDriver::class,
            'version' => '4.x',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | IDE Targets
    |--------------------------------------------------------------------------
    |
    | Where and how generated docs are written for each supported IDE.
    | Register custom targets by implementing
    | Rdcstarr\DocsGenerator\Contracts\Target.
    |
    */

    'targets' => [

        'claude' => [
            'class'      => \Rdcstarr\DocsGenerator\Targets\ClaudeTarget::class,
            'output_dir' => base_path('.claude/{source}'),
            'index_file' => base_path('CLAUDE.md'),
        ],

        'cursor' => [
            'class'      => \Rdcstarr\DocsGenerator\Targets\CursorTarget::class,
            'output_dir' => base_path('.cursor/rules'),
        ],

        'copilot' => [
            'class'      => \Rdcstarr\DocsGenerator\Targets\CopilotTarget::class,
            'output_dir' => base_path('.github/instructions'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global Generation Options
    |--------------------------------------------------------------------------
    */

    'timeout'          => 30,
    'retry_attempts'   => 3,
    'retry_sleep_ms'   => 2000,
    'throttle_seconds' => 1,
    'max_tokens'       => 16384,
    'max_html_bytes'   => 200_000,

];
