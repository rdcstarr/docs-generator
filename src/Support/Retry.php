<?php

namespace Rdcstarr\DocsGenerator\Support;

use Illuminate\Support\Str;

class Retry
{
    /**
     * Execute a callable with automatic retry and smart backoff on rate limits.
     *
     * @param  callable  $fn
     * @param  int  $attempts
     * @param  int  $sleepMs
     * @param  callable|null  $onRetry
     * @return mixed
     */
    public function withRetry(callable $fn, int $attempts = 3, int $sleepMs = 2000, ?callable $onRetry = null): mixed
    {
        $attempt = 0;
        $lastException = null;

        return retry($attempts, function () use ($fn, $attempts, &$attempt, &$lastException, $onRetry): mixed
        {
            $attempt++;

            if ($attempt > 1 && $onRetry)
            {
                $onRetry("    <fg=yellow>↻ Retry {$attempt}/{$attempts}...</>");
            }

            try
            {
                return $fn();
            }
            catch (\Throwable $e)
            {
                $lastException = $e;

                throw $e;
            }
        }, function () use ($sleepMs, &$lastException, $onRetry): int
        {
            if ($lastException instanceof \Throwable && Str::contains(Str::lower($lastException->getMessage()), 'rate limit'))
            {
                if ($onRetry)
                {
                    $onRetry('    <fg=yellow>⏳ Rate limited — waiting 60s...</>');
                }

                return 60_000;
            }

            return $sleepMs;
        });
    }
}
