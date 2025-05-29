<?php

use JamesJulius\LaravelNexus\Commands\NexusConfigureCommand;
use JamesJulius\LaravelNexus\Commands\NexusHelpCommand;
use JamesJulius\LaravelNexus\Commands\NexusPublishCommand;
use JamesJulius\LaravelNexus\Commands\NexusWorkCommand;
use JamesJulius\LaravelNexus\Services\QueueDiscoveryService;

it('registers the queue discovery service', function () {
    expect($this->app->bound(QueueDiscoveryService::class))->toBeTrue();
});

it('registers all nexus commands', function () {
    $commands = [
        NexusConfigureCommand::class,
        NexusWorkCommand::class,
        NexusHelpCommand::class,
        NexusPublishCommand::class,
    ];

    foreach ($commands as $command) {
        expect($this->app->bound($command))->toBeTrue();
    }
});

it('merges configuration from package', function () {
    expect(config('nexus'))->toBeArray();
    expect(config('nexus.workers'))->toBeArray();
    expect(config('nexus.prefix'))->toBe('nexus');
});

it('has correct configuration structure', function () {
    $config = config('nexus');

    expect($config)->toHaveKeys([
        'workers',
        'environment',
        'prefix',
        'auto_restart',
        'restart_signal_file',
    ]);

    expect($config['workers'])->toBeArray();
    expect($config['environment'])->toBe('testing');
    expect($config['prefix'])->toBe('nexus');
    expect($config['auto_restart'])->toBeTrue();
});
