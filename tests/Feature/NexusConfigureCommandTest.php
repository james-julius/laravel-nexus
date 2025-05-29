<?php

it('can run discovery without errors', function () {
    $this->artisan('nexus:configure --discover')
        ->assertExitCode(0);
});

it('shows discovered queues when using discover flag', function () {
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
