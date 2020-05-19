<?php


namespace Booni3\VaporQueueManager;


use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Str;
use Mockery\Exception;

trait ThrottlesVaporJob
{
    /** @var Repository */
    protected $cache;

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

    protected function isThrottledByTime($key, $limit): bool
    {
        if (!($limit['allow'] && $limit['every'])) {
            return false;
        }

        if (!$this->cache->getStore() instanceof RedisStore) {
            throw new Exception('You must have redis installed to use the time based throttle');
        }

        // Uses Redis::throttle
        $this->cache
            ->throttle($this->timeKey($key))
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

        if ($jobsInFunnel = $this->cache->get($key, null)) {
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

            if (!$this->cache->has($key)) {
                $this->cache->set($key, 1, now()->addMinutes(10));
            } else {
                $this->cache->increment($key);
            }
        }

    }

    protected function decrementFunnel($queue, $payload)
    {
        $queue =  $this->virtualQueue($payload) ?? $queue;

        foreach ($this->throttleKeys($queue) as $key) {
            $key = $this->funnelKey($key);

            if ($this->cache->has($key) && $this->cache->decrement($key) <= 0) {
                $this->cache->delete($key);
            }
        }
    }

    protected function virtualQueue($payload): ?string
    {
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