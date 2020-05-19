<?php


namespace Booni3\VaporQueueManager;


use Aws\Sqs\SqsClient;
use Carbon\Carbon;
use Illuminate\Cache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class JobPushCommand extends Command
{
    use ThrottlesVaporJob;

    /** @var DatabaseManager */
    protected $database;

    /** @var \Illuminate\Contracts\Queue\Queue|SqsQueue */
    protected $queue;

    /** @var string */
    protected $defaultQueue;

    /** @var SqsClient */
    protected $sqs;

    /** @var array*/
    protected $sqsQueues;

    /** @var array */
    protected $limits;

    /** @var Carbon */
    protected $killAt;

    /** @var Repository*/
    protected $cache;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:push';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(DatabaseManager $database, QueueManager $queue, CacheManager $cache)
    {
        parent::__construct();

        $this->database = $database;
        $this->queue = $queue;
        $this->cache = $cache->driver();
        $this->defaultQueue = config('vapor-queue-manager.default_queue');
        $this->limits = config('vapor-queue-manager.limits');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->sqs = $this->queue->getSqs();
        $this->sqsQueues = $this->sqs->listQueues()->get('QueueUrls');

        while ($this->shouldLoop()) {
            $this->dispatchEligibleJobs();
        }
    }

    protected function dispatchEligibleJobs()
    {
        $this
            ->database
            ->table('jobs')
            ->oldest('id')
            ->cursor()
            ->groupBy('queue')
            ->each(function (Collection $jobs, $queue) {
                foreach ($jobs as $job) {
                    if ($this->isThrottled($queue)) {
                        return true;
                    }

                    $this->dispatchJobToSqs($job);
                    $this->jobDispatched($queue, $job);
                }
            });
    }

    protected function dispatchJobToSqs($job)
    {
        unset($payload);
        if($this->usingVirtualQueue($job)){
            $payload = json_decode($job->payload);
            $payload->virtualQueue = $this->normalizedQueueName($job->queue);
        }

        $this->queue->pushRawDirect($payload ?? $job->payload, $this->toValidSqsQueue($job));
    }

    protected function toValidSqsQueue($job): string
    {
        if($this->usingVirtualQueue($job)){
            return $this->defaultQueue;
        }

        return $job->queue;
    }

    protected function usingVirtualQueue($job): bool
    {
        return ! in_array($job->queue, $this->sqsQueues);
    }

    protected function normalizedQueueName($queue): string
    {
        if (Str::startsWith($queue, 'http')) {
            return substr($queue, strrpos($queue, '/') + 1);
        }

        return $queue;
    }

    protected function jobDispatched($key, $job)
    {
        $this->incrementFunnel($key, $job->payload);
        $this->database->table('jobs')->delete($job->id);
    }

    protected function shouldLoop($maxRunTime = 60, $loopDelay = 1): bool
    {
        if (!$this->killAt) {
            $this->killAt = Carbon::now()->addSeconds($maxRunTime);
        }

        if ($bool = now()->lessThan($this->killAt)) {
            sleep($loopDelay);

            return true;
        }

        return false;
    }
}