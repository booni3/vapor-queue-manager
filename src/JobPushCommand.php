<?php


namespace Booni3\VaporQueueManager;


use App\Traits\ThrottlesVaporJob;
use Aws\Sqs\SqsClient;
use Carbon\Carbon;
use Illuminate\Cache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Collection;

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
            ->each(function (Collection $jobs, $key) {
                foreach ($jobs as $job) {
                    if ($this->isThrottled($key, $job->payload)) {
                        return true;
                    }

                    $this->dispatchJobToSqs($job);
                    $this->jobDispatched($key, $job);
                }
            });
    }

    protected function dispatchJobToSqs($job)
    {
        $this->queue->pushRawDirect($job->payload, $this->toValidSqsQueue($job));
    }

    protected function toValidSqsQueue($job): string
    {
        if(in_array($job->queue, $this->sqsQueues)){
            return $job->queue;
        }

        return $this->defaultQueue;
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