<?php

namespace JamesJulius\LaravelNexus\Tests;

use JamesJulius\LaravelNexus\NexusServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup if needed
    }

    protected function getPackageProviders($app)
    {
        return [
            NexusServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('queue.default', 'database');

        // Set up test queue configuration
        config()->set('nexus.workers', [
            'default' => [
                'queue' => 'default',
                'connection' => 'database',
                'tries' => 3,
                'timeout' => 60,
                'sleep' => 3,
                'memory' => 128,
                'processes' => 1,
                'max_jobs' => 1000,
                'max_time' => 3600,
            ],
            'test' => [
                'queue' => 'test',
                'connection' => 'database',
                'tries' => 1,
                'timeout' => 30,
                'sleep' => 1,
                'memory' => 64,
                'processes' => 1,
                'max_jobs' => 100,
                'max_time' => 1800,
            ],
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadLaravelMigrations();
    }
}
