<?php

arch('commands')
    ->expect('JamesJulius\LaravelNexus\Commands')
    ->toExtend('Illuminate\Console\Command')
    ->toHaveSuffix('Command');

arch('services')
    ->expect('JamesJulius\LaravelNexus\Services')
    ->toHaveSuffix('Service');

arch('no debugging functions')
    ->expect(['dd', 'dump', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('ensure proper namespacing for app classes')
    ->expect('JamesJulius\LaravelNexus')
    ->toUseNothing('App\\');

arch('commands should not be final')
    ->expect('JamesJulius\LaravelNexus\Commands')
    ->not->toBeFinal();

arch('services can be final')
    ->expect('JamesJulius\LaravelNexus\Services')
    ->toBeFinal();
