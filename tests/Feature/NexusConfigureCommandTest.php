<?php

use JamesJulius\LaravelNexus\Services\QueueDiscoveryService;

it('can run discovery without errors', function () {
    $this->artisan('nexus:configure --discover')
        ->assertExitCode(0);
});

it('shows discovered queues when using discover flag', function () {
    // Mock the discovery service to return predictable results
    $this->instance(QueueDiscoveryService::class, new class {
        public function discoverQueues(): array
        {
            return [
                'default' => ['jobs' => 5, 'failed' => 1],
                'test-queue' => ['jobs' => 2, 'failed' => 0],
            ];
        }

        public function extractQueuesFromContent(string $content): array
        {
            return [];
        }
    });

    $this->artisan('nexus:configure --discover')
        ->expectsOutputToContain('Queue Discovery Results')
        ->assertExitCode(0);
});

it('can list jobs for all queues', function () {
    $this->artisan('nexus:configure --list-jobs')
        ->assertExitCode(0);
});

it('can list jobs for specific queue', function () {
    $this->artisan('nexus:configure --list-jobs --queue=default')
        ->assertExitCode(0);
});