<?php


namespace App\Traits;


use Illuminate\Cache\Repository;
use Illuminate\Support\Str;

trait ThrottlesVaporJob
{
    /** @var Repository */
    protected $cache;

    public function isThrottled($queue, $payload)
    {
        foreach ($this->throttleKeys($queue, $payload) as $key) {
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

    protected function throttleKeys($queue, $payload): array
    {
        // Throttle by queue
        if (Str::startsWith($queue, 'http')) {
            $queue = substr($queue, strrpos($queue, '/') + 1);
        }

        return [$queue];
    }

    protected function isThrottledByTime($key, $limit): bool
    {
        if(! ($limit['allow'] && $limit['every'])){
            return false;
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
        if(! $limit['funnel']){
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
        foreach ($this->throttleKeys($queue, $payload) as $key) {
            $key = $this->funnelKey($key);

            if (! $this->cache->has($key)) {
                $this->cache->set($key, 1, now()->addMinutes(10));
            } else {
                $this->cache->increment($key);
            }
        }
    }

    protected function decrementFunnel($queue, $payload)
    {
        foreach ($this->throttleKeys($queue, $payload) as $key) {
            $key = $this->funnelKey($key);

            if ($this->cache->has($key) && $this->cache->decrement($key) <= 0) {
                $this->cache->delete($key);
            }
        }
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