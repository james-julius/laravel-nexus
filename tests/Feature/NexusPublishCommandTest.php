<?php

it('can run nexus:publish command', function () {
    $this->artisan('nexus:publish')
        ->assertExitCode(0);
});

it('can run nexus:publish command with force flag', function () {
    $this->artisan('nexus:publish --force')
        ->assertExitCode(0);
});
