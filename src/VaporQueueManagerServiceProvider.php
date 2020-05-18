<?php

namespace Booni3\VaporQueueManager;

use App\Traits\ThrottlesVaporJob;
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
            $schedule = app(Schedule::class);
            $schedule->command('job:push')->everyMinute();
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
        Event::listen(JobProcessing::class, function ($event) {
            //
        });

        Event::listen(JobProcessed::class, function ($event) {
            $this->decrementFunnel($event->job->getQueue(), $event->job->payload());
        });

        Event::listen(JobFailed::class, function ($event) {
            $this->decrementFunnel($event->job->getQueue(), $event->job->payload());
        });
    }
}
