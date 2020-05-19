<?php


namespace Booni3\VaporQueueManager;


use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery\Exception;

trait ThrottlesVaporJob
{

    public function isThrottled($queue)
    {
        foreach ($this->throttleKeys($queue) as $key) {
            if (! $limit = $this->limits[$key] ?? false) {
                throw new \Exception('Throttle key (' . $key . ') limits not defined');
            }

            if ($this->isThrottledByFunnel($key, $limit)) {
                return true;
            }

            if ($this->isThrottledByTime($key, $limit)) {
                return true;
            }
        }

        return false;
    }

    protected function throttleKeys($queue): array
    {
        return [
            $this->normalizedQueueName($queue)
        ];
    }

    protected function normalizedQueueName($queue): string
    {
        if (Str::startsWith($queue, 'http')) {
            return substr($queue, strrpos($queue, '/') + 1);
        }

        return $queue;
    }

    protected function isThrottledByTime($key, $limit): bool
    {
        if (!($limit['allow'] && $limit['every'])) {
            return false;
        }

        if (! Cache::getStore() instanceof RedisStore) {
            throw new Exception('You must have redis installed to use the time based throttle');
        }

        Redis::throttle($this->timeKey($key))
            ->block(0)
            ->allow($limit['allow']) // jobs
            ->every($limit['every']) // seconds
            ->then(function () use (&$throttled) {
                $throttled = false;
            }, function () use (&$throttled) {
                $throttled = true;
            });

        if ($throttled === true) {
            return true;
        }

        return false;
    }

    protected function isThrottledByFunnel($key, $limit): bool
    {
        if (! $limit['funnel']) {
            return false;
        }

        $key = $this->funnelKey($key);

        if ($jobsInFunnel = Cache::get($key, null)) {
            if ($jobsInFunnel >= $limit['funnel']) {
                return true;
            }
        }
        return false;
    }

    protected function incrementFunnel($queue, $payload)
    {
        $queue =  $this->virtualQueue($payload) ?? $queue;

        foreach ($this->throttleKeys($queue) as $key) {
            $key = $this->funnelKey($key);

            if (! Cache::has($key)) {
                Cache::set($key, 1, now()->addMinutes(10));
            } else {
                Cache::increment($key);
            }
        }

    }

    protected function decrementFunnel($queue, $payload)
    {
        $queue =  $this->virtualQueueFromPayload($payload) ?? $queue;

        \Sentry::captureMessage('queue: '.$queue);

        foreach ($this->throttleKeys($queue) as $key) {
            $key = $this->funnelKey($key);

            \Sentry::captureMessage('key: '.$key);

            if (Cache::has($key) && Cache::decrement($key) <= 0) {
                Cache::delete($key);
            }
        }
    }

    protected function virtualQueueFromPayload($payload): ?string
    {
        Log::info('virtual queue', $payload);

        if(is_string($payload)){
            return json_decode($payload)->virtualQueue ?? null;
        }

        if(is_array($payload)){
            return $payload->virtualQueue ?? null;
        }

        return null;
    }

    protected function funnelKey($key): string
    {
        return 'throttle:' . $key . ':funnel';
    }

    protected function timeKey($key): string
    {
        return 'throttle:' . $key . ':time';
    }
}