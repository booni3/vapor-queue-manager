<?php


namespace Booni3\VaporQueueManager;


use Aws\Sqs\SqsClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

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

    /** @var array*/
    protected $dispatchableQueues;

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
    public function __construct()
    {
        parent::__construct();

        $this->killAt = Carbon::now()->addSeconds(60);
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
        $this->sqs = Queue::getSqs();
        $this->sqsQueues = $this->sqs->listQueues()->get('QueueUrls');
        $this->dispatchableQueues = $this->distinctQueuesInJobsTable();

        while ($this->shouldLoop()) {
            $this->dispatchEligibleJobs();
        }
    }

    protected function distinctQueuesInJobsTable(): array
    {
        return DB::table('jobs')
            ->select('queue')
            ->distinct('queue')
            ->pluck('queue')
            ->toArray();
    }

    protected function dispatchEligibleJobs()
    {
        foreach($this->dispatchableQueues as $queue){
            DB::table('jobs')
                ->oldest('id')
                ->where('queue', $queue)
                ->cursor()
                ->each(function($job) use($queue){
                    if ($this->isThrottled($queue)) {
                        return false;
                    }

                    $this->dispatchJobToSqs($job);
                    $this->jobDispatched($queue, $job);
                });
        }
    }

    protected function dispatchJobToSqs($job)
    {
        unset($payload);

        if($this->usingVirtualQueue($job)){
            $payload = json_decode($job->payload);
            $payload->virtualQueue = $this->normalizedQueueName($job->queue);
            $payload = json_encode($payload);
        }

        Queue::pushRawDirect($payload ?? $job->payload, $this->toValidSqsQueue($job));
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

    protected function jobDispatched($key, $job)
    {
        $this->incrementFunnel($key, $job->payload);
        DB::table('jobs')->delete($job->id);
    }

    protected function shouldLoop($loopDelay = 1): bool
    {
        if ($bool = now()->lessThan($this->killAt)) {
            sleep($loopDelay);

            return true;
        }

        return false;
    }
}