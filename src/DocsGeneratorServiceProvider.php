<?php

namespace Rdcstarr\DocsGenerator;

use Illuminate\Support\ServiceProvider;
use Rdcstarr\DocsGenerator\Console\GenerateDocsCommand;

class DocsGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/docs-generator.php', 'docs-generator');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole())
        {
            $this->publishes([
                __DIR__ . '/../config/docs-generator.php' => config_path('docs-generator.php'),
            ], 'docs-generator-config');

            $this->commands([
                GenerateDocsCommand::class,
            ]);
        }
    }
}
