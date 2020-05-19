<?php


namespace Booni3\VaporQueueManager;


use App\Traits\ThrottlesVaporJob;
use Aws\Sqs\SqsClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

class JobPushCommand extends Command
{
    use ThrottlesVaporJob;

    /** @var string */
    protected $defaultQueue;

    /** @var array */
    protected $limits;

    /** @var Carbon */
    protected $killAt;

    /** @var Repository*/
    protected $cache;

    /** @var array*/
    protected $sqsQueues;

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
    public function __construct()
    {
        parent::__construct();

        $this->cache = app('cache')->driver();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->sqsQueues = $this->getSqsQueues();
        $this->defaultQueue = config('vapor-queue-manager.config.default_queue');
        $this->limits = config('vapor-queue-manager.config.limits');

        while ($this->shouldLoop()) {
            $this->dispatchEligibleJobs();
        }
    }

    protected function getSqs(): SqsClient
    {
        return app('queue')->getSqs();
    }

    protected function getSqsQueues(): array
    {
        return $this->getSqs()->listQueues()->get('QueueUrls');
    }

    protected function dispatchEligibleJobs()
    {
        DB::table('jobs')
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
        Queue::pushRawDirect($job->payload, $this->toValidSqsQueue($job));
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
        DB::table('jobs')->delete($job->id);
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