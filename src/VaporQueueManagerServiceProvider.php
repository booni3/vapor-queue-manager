<?php

namespace Booni3\VaporQueueManager;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class VaporQueueManagerServiceProvider extends ServiceProvider
{
    use ThrottlesVaporJob;

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->listenForEvents();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('vapor-queue-manager.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_jobs_table.php.stub' => database_path('migrations/'
                    .date('Y_m_d_His', time()).'_create_jobs_table.php'),
            ], 'migrations');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/vapor-queue-manager'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/vapor-queue-manager'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/vapor-queue-manager'),
            ], 'lang');*/

            // Registering package commands.
             $this->commands([
                 JobPushCommand::class
             ]);
        }

        $this->app->booted(function () {
            if(config('vapor-queue-manager.enabled', false)){
                $schedule = app(Schedule::class);
                $schedule->command('job:push')->everyMinute();
            }
        });
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'vapor-queue-manager');

        $this->app->singleton('vapor-queue-manager', function () {
            return new VaporQueueManager;
        });
    }

    protected function listenForEvents()
    {
        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            //
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event) {
            $this->decrementFunnel($event->job->getQueue(), $event->job->payload());
        });

        Event::listen(JobFailed::class, function (JobFailed $event) {
            $this->decrementFunnel($event->job->getQueue(), $event->job->payload());
        });
    }
}
