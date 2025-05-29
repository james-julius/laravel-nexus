<?php

namespace JamesJulius\LaravelNexus\Commands;

use Illuminate\Console\Command;

class NexusPublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nexus:publish
                            {--force : Overwrite existing configuration file}';

    /**
     * The console command description.
     */
    protected $description = 'Publish Laravel Nexus configuration file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configPath = config_path('nexus.php');
        $configExists = file_exists($configPath);

        if ($configExists && ! $this->option('force')) {
            $this->info('📄 Configuration file already exists at config/nexus.php');
            $this->line('   Use <comment>--force</comment> to overwrite it');

            return 0;
        }

        // Publish the configuration file
        $this->call('vendor:publish', [
            '--provider' => 'JamesJulius\\LaravelNexus\\NexusServiceProvider',
            '--tag' => 'nexus-config',
            '--force' => $this->option('force'),
        ]);

        if ($configExists && $this->option('force')) {
            $this->info('✅ Configuration file updated at config/nexus.php');
        } else {
            $this->info('✅ Configuration file published to config/nexus.php');
        }

        $this->newLine();
        $this->line('📋 <comment>What\'s next?</comment>');
        $this->line('   • Run <comment>nexus:configure</comment> to discover and configure your queues');
        $this->line('   • Or edit <comment>config/nexus.php</comment> manually');
        $this->line('   • Then run <comment>nexus:work</comment> to start your workers');

        return 0;
    }
}
