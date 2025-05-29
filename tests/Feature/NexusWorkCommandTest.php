<?php

it('can check worker status', function () {
    $this->artisan('nexus:work --status')
        ->assertExitCode(0);
});

it('can stop workers', function () {
    $this->artisan('nexus:work --stop')
        ->expectsOutputToContain('No worker processes found to stop')
        ->assertExitCode(0);
});

it('validates worker name when using worker flag', function () {
    $this->artisan('nexus:work --worker=nonexistent')
        ->expectsOutputToContain('Worker configuration not found')
        ->assertExitCode(1);
});

it('can show status with no workers running', function () {
    $this->artisan('nexus:work --status')
        ->expectsOutputToContain('Worker Status')
        ->assertExitCode(0);
});
