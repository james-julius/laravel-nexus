<?php

namespace JamesJulius\LaravelNexus;

use Illuminate\Support\ServiceProvider;
use JamesJulius\LaravelNexus\Commands\NexusConfigureCommand;
use JamesJulius\LaravelNexus\Commands\NexusHelpCommand;
use JamesJulius\LaravelNexus\Commands\NexusPublishCommand;
use JamesJulius\LaravelNexus\Commands\NexusWorkCommand;
use JamesJulius\LaravelNexus\Services\QueueDiscoveryService;

class NexusServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nexus.php', 'nexus');

        $this->app->singleton(QueueDiscoveryService::class);

        $this->commands([
            NexusConfigureCommand::class,
            NexusWorkCommand::class,
            NexusHelpCommand::class,
            NexusPublishCommand::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nexus.php' => config_path('nexus.php'),
            ], 'nexus-config');

            $this->publishes([
                __DIR__.'/../config/nexus.php' => config_path('nexus.php'),
            ], 'laravel-nexus');
        }
    }
}
