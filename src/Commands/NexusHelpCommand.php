<?php

namespace JamesJulius\LaravelNexus\Commands;

use Illuminate\Console\Command;

class NexusHelpCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nexus:help';

    /**
     * The console command description.
     */
    protected $description = 'Show comprehensive help and usage guide for Laravel Nexus';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->showHeader();
        $this->showOverview();
        $this->showCommands();
        $this->showWorkflow();
        $this->showTips();
        $this->showFooter();

        return 0;
    }

    /**
     * Show the header and logo.
     */
    protected function showHeader(): void
    {
        $this->newLine();
        $this->line('  <fg=blue>╭─────────────────────────────────────────────────────╮</>');
        $this->line('  <fg=blue>│</> <fg=cyan;options=bold>🚀 Laravel Nexus - Queue Worker Management</> <fg=blue>│</>');
        $this->line('  <fg=blue>╰─────────────────────────────────────────────────────╯</>');
        $this->newLine();
    }

    /**
     * Show overview section.
     */
    protected function showOverview(): void
    {
        $this->info('📋 WHAT IS LARAVEL NEXUS?');
        $this->line('  Laravel Nexus is your central hub for queue worker management.');
        $this->line('  It automatically discovers your queues, provides interactive');
        $this->line('  configuration, and manages multiple workers with ease.');
        $this->newLine();

        $this->info('✨ KEY FEATURES:');
        $this->line('  • 🔍 <options=bold>Auto-Discovery</> - Scans Jobs, Events, Mail, Notifications');
        $this->line('  • ⚙️  <options=bold>Interactive Setup</> - Beautiful prompts for configuration');
        $this->line('  • 📺 <options=bold>Live Log Streaming</> - Real-time, color-coded worker output');
        $this->line('  • 🎛️  <options=bold>Process Management</> - Start, stop, restart, monitor workers');
        $this->line('  • 🔥 <options=bold>Hot Reload</> - Auto-restart workers when files change');
        $this->line('  • 🔄 <options=bold>Auto-restart</> - Responds to Laravel\'s queue:restart signals');
        $this->line('  • 📊 <options=bold>Smart Defaults</> - Optimized settings based on queue type');
        $this->newLine();
    }

    /**
     * Show commands section.
     */
    protected function showCommands(): void
    {
        $this->info('🎯 MAIN COMMANDS:');
        $this->newLine();

        // Publish command
        $this->line('  <fg=green;options=bold>nexus:publish</> - Publish Configuration');
        $this->line('    <fg=yellow>php artisan nexus:publish</> - Publish config file');
        $this->line('    <fg=yellow>php artisan nexus:publish --force</> - Overwrite existing config');
        $this->newLine();

        // Configure command
        $this->line('  <fg=green;options=bold>nexus:configure</> - Queue Configuration & Discovery');
        $this->line('    <fg=yellow>php artisan nexus:configure</> - Interactive setup flow');
        $this->line('    <fg=yellow>php artisan nexus:configure --discover</> - Discovery only');
        $this->line('    <fg=yellow>php artisan nexus:configure --list-jobs</> - List all jobs');
        $this->line('    <fg=yellow>php artisan nexus:configure --list-jobs --queue=broadcasting</> - Specific queue');
        $this->newLine();

        // Work command
        $this->line('  <fg=green;options=bold>nexus:work</> - Worker Management & Monitoring');
        $this->line('    <fg=yellow>php artisan nexus:work</> - Start all workers');
        $this->line('    <fg=yellow>php artisan nexus:work --log</> - Start + live logs');
        $this->line('    <fg=yellow>php artisan nexus:work --detailed</> - Start + detailed job logs with IDs/dates');
        $this->line('    <fg=yellow>php artisan nexus:work --watch</> - Start + hot reload + live logs');
        $this->line('    <fg=yellow>php artisan nexus:work --status</> - Check status');
        $this->line('    <fg=yellow>php artisan nexus:work --stop</> - Stop all workers');
        $this->line('    <fg=yellow>php artisan nexus:work --restart</> - Restart workers');
        $this->line('    <fg=yellow>php artisan nexus:work --worker=name</> - Start specific worker');
        $this->newLine();
    }

    /**
     * Show typical workflow.
     */
    protected function showWorkflow(): void
    {
        $this->info('🚀 TYPICAL WORKFLOW:');
        $this->newLine();

        $this->line('  <fg=cyan;options=bold>0. Optional: Publish Config</> (for manual editing)');
        $this->line('    <fg=yellow>php artisan nexus:publish</>');
        $this->line('    → Publishes config/nexus.php for manual configuration');
        $this->newLine();

        $this->line('  <fg=cyan;options=bold>1. First Time Setup</> (run once)');
        $this->line('    <fg=yellow>php artisan nexus:configure</>');
        $this->line('    → Discovers queues from your app');
        $this->line('    → Easy queue selection (Select All or pick specific ones)');
        $this->line('    → Configures worker settings interactively');
        $this->line('    → Saves config to <comment>config/nexus.php</>');
        $this->line('    → Optionally starts workers immediately');
        $this->newLine();

        $this->line('  <fg=cyan;options=bold>2. Daily Operations</>');
        $this->line('    <fg=yellow>php artisan nexus:work</> - Start workers (daemon mode)');
        $this->line('    <fg=yellow>php artisan nexus:work --log</> - Development with live logs');
        $this->line('    <fg=yellow>php artisan nexus:work --detailed</> - Debugging with detailed job logs');
        $this->line('    <fg=yellow>php artisan nexus:work --watch</> - Development with hot reload');
        $this->line('    <fg=yellow>php artisan nexus:work --status</> - Check worker health');
        $this->newLine();

        $this->line('  <fg=cyan;options=bold>3. Deployments</>');
        $this->line('    <fg=yellow>php artisan nexus:work --restart</> - Restart all workers');
        $this->line('    <fg=yellow>php artisan queue:restart</> - Signal restart (auto-detected)');
        $this->newLine();

        $this->line('  <fg=cyan;options=bold>4. Troubleshooting</>');
        $this->line('    <fg=yellow>php artisan nexus:configure --discover</> - Re-scan for queues');
        $this->line('    <fg=yellow>php artisan nexus:configure --list-jobs --queue=name</> - Debug queue');
        $this->line('    <fg=yellow>php artisan nexus:work --stop</> - Emergency stop');
        $this->newLine();
    }

    /**
     * Show tips and best practices.
     */
    protected function showTips(): void
    {
        $this->info('💡 TIPS & BEST PRACTICES:');
        $this->newLine();

        $this->line('  <fg=green>✅ Development:</>');
        $this->line('    • Use <fg=yellow>--log</> mode for live log streaming');
        $this->line('    • Use <fg=yellow>--detailed</> mode for detailed job debugging (IDs, dates, colors)');
        $this->line('    • Use <fg=yellow>--watch</> mode for hot reload on file changes');
        $this->line('    • Check <comment>config/nexus.php</> for worker settings');
        $this->line('    • Failed job counts shown in <fg=yellow>--discover</> output');
        $this->newLine();

        $this->line('  <fg=green>✅ Production:</>');
        $this->line('    • Use process managers like Supervisor for persistence');
        $this->line('    • Monitor with <fg=yellow>nexus:work --status</> in cron jobs');
        $this->line('    • Set appropriate memory limits per worker type');
        $this->newLine();

        $this->line('  <fg=green>✅ Queue Types Detected:</>');
        $this->line('    • Jobs with <comment>$queue</> property or <comment>onQueue()</> calls');
        $this->line('    • Broadcasting Events with <comment>broadcastQueue()</> method');
        $this->line('    • Mail Classes using the <comment>Queueable</> trait');
        $this->line('    • Notifications implementing <comment>ShouldQueue</>');
        $this->line('    • Event Listeners that are queued');
        $this->newLine();
    }

    /**
     * Show footer with additional resources.
     */
    protected function showFooter(): void
    {
        $this->line('  <fg=blue>╭─────────────────────────────────────────────────────╮</>');
        $this->line('  <fg=blue>│</> <options=bold>Configuration File:</> <comment>config/nexus.php</> <fg=blue>│</>');
        $this->line('  <fg=blue>│</> <options=bold>Environment Variable:</> <comment>NEXUS_PREFIX</> <fg=blue>│</>');
        $this->line('  <fg=blue>│</> <options=bold>Process Files:</> <comment>storage/app/nexus.pids</> <fg=blue>│</>');
        $this->line('  <fg=blue>╰─────────────────────────────────────────────────────╯</>');
        $this->newLine();

        $this->line('  <fg=magenta>Need more help?</> Try:');
        $this->line('    <fg=yellow>php artisan help nexus:configure</>');
        $this->line('    <fg=yellow>php artisan help nexus:work</>');
        $this->newLine();

        $this->line('  <fg=cyan;options=bold>Laravel Nexus - Your central hub for queue management</> 🚀');
        $this->newLine();
    }
}
