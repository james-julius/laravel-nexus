<?php

it('displays help information successfully', function () {
    $this->artisan('nexus:help')
        ->expectsOutputToContain('Laravel Nexus - Queue Worker Management')
        ->expectsOutputToContain('WHAT IS LARAVEL NEXUS?')
        ->expectsOutputToContain('KEY FEATURES:')
        ->expectsOutputToContain('MAIN COMMANDS:')
        ->expectsOutputToContain('TYPICAL WORKFLOW:')
        ->expectsOutputToContain('TIPS & BEST PRACTICES:')
        ->assertExitCode(0);
});

it('shows all nexus commands in help', function () {
    $this->artisan('nexus:help')
        ->expectsOutputToContain('nexus:publish')
        ->expectsOutputToContain('nexus:configure')
        ->expectsOutputToContain('nexus:work')
        ->assertExitCode(0);
});

it('shows feature descriptions', function () {
    $this->artisan('nexus:help')
        ->expectsOutputToContain('Auto-Discovery')
        ->expectsOutputToContain('Interactive Setup')
        ->expectsOutputToContain('Live Log Streaming')
        ->expectsOutputToContain('Process Management')
        ->expectsOutputToContain('Hot Reload')
        ->assertExitCode(0);
});

it('shows configuration file path', function () {
    $this->artisan('nexus:help')
        ->expectsOutputToContain('config/nexus.php')
        ->assertExitCode(0);
});
