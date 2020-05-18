<?php


namespace Booni3\VaporQueueManager;


use App\Traits\ThrottlesVaporJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;

class JobPushCommand extends Command
{
    use ThrottlesVaporJob;

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

        $this->cache = $this->laravel['cache']->driver();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        while ($this->shouldLoop()) {
            $this->getThrottleParameters();
            $this->dispatchEligibleJobs();
        }
    }

    protected function getThrottleParameters()
    {
        $this->limits = [
            'oflow-app-staging' => ['allow' => 1, 'every' => 60, 'funnel' => 1],
        ];
    }

    protected function dispatchEligibleJobs()
    {
        DB::table('jobs')
            ->oldest('id')
            ->cursor()
            ->groupBy('queue')
            ->each(function (Collection $jobs, $queue) {
                foreach ($jobs as $job) {
                    if ($this->isThrottled($queue, $job->payload)) {
                        return true;
                    }

                    $this->dispatchJobToSqs($job);
                    $this->incrementFunnel($queue, $job->payload);
                }
            });
    }

    protected function dispatchJobToSqs($job)
    {
        Queue::pushRawDirect($job->payload, $job->queue);
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