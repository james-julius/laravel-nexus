<?php

namespace JamesJulius\LaravelNexus\Commands;

use Illuminate\Console\Command;
use JamesJulius\LaravelNexus\Services\QueueDiscoveryService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class NexusConfigureCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nexus:configure
                            {--discover : Just discover and show queues without configuring}
                            {--list-jobs : Show detailed list of jobs/classes for each queue}
                            {--queue= : Show jobs for specific queue only}';

    /**
     * The console command description.
     */
    protected $description = 'Configure queue workers and discover jobs in your Laravel application';

    /**
     * Queue discovery service.
     */
    protected QueueDiscoveryService $discoveryService;

    /**
     * Create a new command instance.
     */
    public function __construct(QueueDiscoveryService $discoveryService)
    {
        parent::__construct();
        $this->discoveryService = $discoveryService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('list-jobs')) {
            return $this->listQueueJobs();
        }

        if ($this->option('discover')) {
            return $this->discoverQueues(false);
        }

        return $this->configureQueues();
    }

    /**
     * Discover queues from job classes.
     */
    protected function discoverQueues(bool $askToConfigure = true): int
    {
        info('🔍 Discovering queues from your application...');

        $discoveredQueues = $this->discoveryService->discoverQueues();
        $queueStats = $this->discoveryService->getQueueStats();

        if (empty($discoveredQueues)) {
            warning('No queues found in your application.');

            return 0;
        }

        info('📋 Queue Discovery Results:');

        // Prepare table data
        $tableData = [];
        foreach ($discoveredQueues as $queue) {
            $stats = $queueStats[$queue['name']] ?? ['pending' => 0, 'failed' => 0];
            $tableData[] = [
                'Queue' => $queue['name'],
                'Status' => $queue['detected'] ? '✅ Detected' : '⚪ Common',
                'Jobs' => count($queue['jobs']),
                'Pending' => $stats['pending'],
                'Failed' => $stats['failed'],
            ];
        }

        table(
            headers: ['Queue', 'Status', 'Jobs', 'Pending', 'Failed'],
            rows: $tableData
        );

        if ($askToConfigure && confirm('Would you like to configure workers for these queues?', true)) {
            return $this->configureQueues($discoveredQueues);
        }

        return 0;
    }

    /**
     * Interactively configure queue workers.
     */
    protected function configureQueues(?array $discoveredQueues = null): int
    {
        info('⚙️  Laravel Nexus Configuration');

        if ($discoveredQueues === null) {
            info('🔍 Discovering queues...');
            $discoveredQueues = $this->discoveryService->discoverQueues();
        }

        // Let user select which queues to configure
        $queueOptions = collect($discoveredQueues)->mapWithKeys(function ($queue) {
            $status = $queue['detected'] ? '✅' : '⚪';
            $jobCount = count($queue['jobs']);

            return [$queue['name'] => "{$status} {$queue['name']} ({$jobCount} job types)"];
        })->toArray();

        // Add "Select All" option at the top for convenience
        $allQueuesOption = ['__ALL__' => '🎯 Select All Queues (' . count($queueOptions) . ' total)'];
        $selectOptions = $allQueuesOption + $queueOptions;

        info('📋 Queue Selection:');
        $this->line('   Use <comment>SPACE</comment> to select/deselect, <comment>ENTER</comment> to confirm');
        $this->line('   💡 <comment>Pro tip:</comment> Choose "Select All" for the fastest setup!');
        $this->line('   ✅ = Auto-detected queues, ⚪ = Common Laravel queues');
        $this->newLine();

        $selected = multiselect(
            label: 'Which queues would you like to manage?',
            options: $selectOptions,
            default: ['__ALL__'], // Default to "Select All" option
            hint: '🚀 "Select All" is pre-selected for quick setup! Uncheck it to choose specific queues.'
        );

        // Handle "Select All" option
        if (in_array('__ALL__', $selected)) {
            $selectedQueues = array_keys($queueOptions);
            info('✅ Selected all ' . count($selectedQueues) . ' queues');
        } else {
            $selectedQueues = array_filter($selected, fn ($item) => $item !== '__ALL__');
            if (! empty($selectedQueues)) {
                info('✅ Selected ' . count($selectedQueues) . ' queue(s): ' . implode(', ', $selectedQueues));
            }
        }

        if (empty($selectedQueues)) {
            warning('No queues selected.');

            return 0;
        }

        $this->newLine();

        // Configure each selected queue
        $configurations = [];
        foreach ($selectedQueues as $queueName) {
            $queue = collect($discoveredQueues)->firstWhere('name', $queueName);
            $config = $this->configureQueue($queueName, $queue);
            $configurations[$queueName] = $config;
        }

        // Show final configuration
        info('📝 Final Configuration:');
        foreach ($configurations as $name => $config) {
            $this->line("<info>{$name}:</info> {$config['processes']} process(es), " .
                       "timeout: {$config['timeout']}s, memory: {$config['memory']}MB");
        }

        if (confirm('Save this configuration?', true)) {
            $this->saveConfiguration($configurations);
            info('✅ Configuration saved to config/nexus.php');
        }

        if (confirm('Start workers now?', true)) {
            info('🚀 Starting workers...');

            return $this->call('nexus:work');
        }

        info('💡 Run "php artisan nexus:work" to start your workers');

        return 0;
    }

    /**
     * Configure a single queue interactively.
     */
    protected function configureQueue(string $queueName, array $queueInfo): array
    {
        info("Configuring queue: {$queueName}");

        if (! empty($queueInfo['jobs'])) {
            $this->line('Job classes using this queue:');
            foreach (array_slice($queueInfo['jobs'], 0, 5) as $job) {
                $this->line("  • {$job}");
            }
            if (count($queueInfo['jobs']) > 5) {
                $this->line('  • ... and ' . (count($queueInfo['jobs']) - 5) . ' more');
            }
        }

        // Get suggested defaults
        $suggested = $this->discoveryService->getDefaultConfigForQueue($queueName, $queueInfo);

        $processes = (int) text(
            label: 'Number of worker processes',
            default: (string) $suggested['processes'],
            hint: 'More processes = higher concurrency'
        );

        $timeout = (int) text(
            label: 'Timeout per job (seconds)',
            default: (string) $suggested['timeout'],
            hint: 'How long a job can run before timing out'
        );

        $memory = (int) text(
            label: 'Memory limit per worker (MB)',
            default: (string) $suggested['memory'],
            hint: 'Worker will restart after hitting this limit'
        );

        $tries = (int) text(
            label: 'Max retry attempts',
            default: (string) $suggested['tries'],
            hint: 'Number of times to retry failed jobs'
        );

        return [
            'queue' => $queueName,
            'connection' => $suggested['connection'],
            'tries' => $tries,
            'timeout' => $timeout,
            'sleep' => $suggested['sleep'],
            'memory' => $memory,
            'processes' => $processes,
            'max_jobs' => $suggested['max_jobs'],
            'max_time' => $suggested['max_time'],
        ];
    }

    /**
     * Save configuration to file.
     */
    protected function saveConfiguration(array $configurations): void
    {
        $configContent = $this->generateConfigFile($configurations);
        file_put_contents(config_path('nexus.php'), $configContent);
    }

    /**
     * Generate configuration file content.
     */
    protected function generateConfigFile(array $configurations): string
    {
        $workersConfig = '';
        foreach ($configurations as $name => $config) {
            $workersConfig .= "
        '{$name}' => [
            'queue' => '{$config['queue']}',
            'connection' => env('QUEUE_CONNECTION', 'database'),
            'tries' => {$config['tries']},
            'timeout' => {$config['timeout']},
            'sleep' => {$config['sleep']},
            'memory' => {$config['memory']},
            'processes' => {$config['processes']},
            'max_jobs' => {$config['max_jobs']},
            'max_time' => {$config['max_time']},
        ],
";
        }

        return "<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Nexus - Queue Workers
    |--------------------------------------------------------------------------
    |
    | This configuration defines the queue workers that should be spawned
    | when running the nexus:work command. Each worker can have its own
    | configuration for queues, timeouts, retries, and process count.
    |
    | Generated automatically by: php artisan nexus:configure
    |
    */

    'workers' => [
{$workersConfig}
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the environment in which the queue manager will
    | run. This affects the process names and can be used for monitoring.
    |
    */

    'environment' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Process Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used for naming the worker processes. This helps
    | with process identification and monitoring in production environments.
    |
    */

    'prefix' => env('NEXUS_PREFIX', 'nexus'),

    /*
    |--------------------------------------------------------------------------
    | Auto Restart
    |--------------------------------------------------------------------------
    |
    | When this is enabled, workers will automatically restart when they
    | receive a restart signal, helping with deployments and updates.
    |
    */

    'auto_restart' => true,

    /*
    |--------------------------------------------------------------------------
    | Restart Signal File
    |--------------------------------------------------------------------------
    |
    | This file path is checked for modifications to trigger worker restarts.
    | Laravel's queue:restart command updates this file's timestamp.
    |
    */

    'restart_signal_file' => storage_path('framework/cache/laravel-queue-restart'),

];
";
    }

    /**
     * List jobs for queues.
     */
    protected function listQueueJobs(): int
    {
        $specificQueue = $this->option('queue');

        if ($specificQueue) {
            return $this->showJobsForSpecificQueue($specificQueue);
        }

        return $this->showAllQueueJobs();
    }

    /**
     * Show jobs for a specific queue.
     */
    protected function showJobsForSpecificQueue(string $queueName): int
    {
        info("📋 Jobs using queue: {$queueName}");

        $jobs = $this->discoveryService->getJobsForQueue($queueName);

        if ($jobs->isEmpty()) {
            warning("No jobs found for queue: {$queueName}");

            return 0;
        }

        $tableData = $jobs->map(function ($job) {
            return [
                'Name' => $job['name'],
                'Type' => $job['type'],
                'Class' => $job['class'],
                'File' => $job['file'],
            ];
        })->toArray();

        table(
            headers: ['Name', 'Type', 'Class', 'File'],
            rows: $tableData
        );

        info("✅ Found {$jobs->count()} jobs/classes using the '{$queueName}' queue");

        return 0;
    }

    /**
     * Show all jobs grouped by queue.
     */
    protected function showAllQueueJobs(): int
    {
        info('🔍 Discovering all queues and their jobs...');

        $discoveredQueues = $this->discoveryService->discoverQueues();
        $detectedQueues = collect($discoveredQueues)->filter(fn ($queue) => $queue['detected']);

        if ($detectedQueues->isEmpty()) {
            warning('No queues with jobs detected.');

            return 0;
        }

        foreach ($detectedQueues as $queue) {
            if (empty($queue['job_details'])) {
                continue;
            }

            $this->line(''); // Empty line for spacing
            info("📋 Queue: {$queue['name']} ({$queue['job_count']} jobs)");

            $tableData = collect($queue['job_details'])->map(function ($job) {
                return [
                    'Name' => $job['job_name'],
                    'Type' => $job['type'],
                    'Class' => $job['job_class'],
                ];
            })->toArray();

            table(
                headers: ['Name', 'Type', 'Class'],
                rows: $tableData
            );
        }

        $totalJobs = $detectedQueues->sum('job_count');
        info("\n✅ Found {$totalJobs} jobs/classes across {$detectedQueues->count()} queues");

        $this->line('');
        info('💡 Use --queue=<name> to see detailed information for a specific queue');

        return 0;
    }
}
