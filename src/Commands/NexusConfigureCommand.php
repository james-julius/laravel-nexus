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

        // Optional consolidation step
        info('🔄 Queue Consolidation (Optional)');
        $this->line('   Laravel typically handles notifications, failed jobs, and mail on the default queue');
        $this->newLine();

        $shouldConsolidate = false;
        $queuesToConsolidate = ['notifications', 'failed', 'mail'];
        $selectedConsolidationQueues = array_intersect($selectedQueues, $queuesToConsolidate);

        if (! empty($selectedConsolidationQueues) && in_array('default', $selectedQueues)) {
            $this->line('📋 Detected the following queues that are commonly handled on the default queue:');
            foreach ($selectedConsolidationQueues as $queue) {
                $this->line("  • {$queue}");
            }
            $this->newLine();

            $shouldConsolidate = confirm(
                'Move notifications, failed, and mail to the default queue? (Recommended)',
                true
            );

            if ($shouldConsolidate) {
                // Remove the consolidated queues from selectedQueues
                $selectedQueues = array_diff($selectedQueues, $queuesToConsolidate);
                info('✅ Consolidated ' . implode(', ', $selectedConsolidationQueues) . ' into default queue');
                $this->newLine();
            }
        }

        // Step 1: Configure process counts for all queues first
        info('📊 Step 1: Configure Worker Processes');
        $this->line('   Set the number of worker processes for each queue');
        $this->newLine();

        $queueProcesses = [];
        foreach ($selectedQueues as $queueName) {
            $queue = collect($discoveredQueues)->firstWhere('name', $queueName);
            $suggested = $this->discoveryService->getDefaultConfigForQueue($queueName, $queue);

            // If this is the default queue and we consolidated other queues into it, suggest more processes
            if ($queueName === 'default' && $shouldConsolidate && ! empty($selectedConsolidationQueues)) {
                $extraProcesses = count($selectedConsolidationQueues);
                $suggested['processes'] = $suggested['processes'] + $extraProcesses;

                info("Queue: {$queueName} (+ consolidated: " . implode(', ', $selectedConsolidationQueues) . ')');
                $this->line('💡 Suggested process count increased to handle consolidated queues');
            } else {
                info("Queue: {$queueName}");
            }

            if (! empty($queue['jobs'])) {
                $this->line('Job classes using this queue:');
                foreach (array_slice($queue['jobs'], 0, 3) as $job) {
                    $this->line("  • {$job}");
                }
                if (count($queue['jobs']) > 3) {
                    $this->line('  • ... and ' . (count($queue['jobs']) - 3) . ' more');
                }
            }

            $processes = (int) text(
                label: 'Number of worker processes',
                default: (string) $suggested['processes'],
                hint: 'More processes = higher concurrency'
            );

            $queueProcesses[$queueName] = $processes;
            $this->newLine();
        }

        // Step 2: Ask if they want advanced options for each queue
        info('⚙️  Step 2: Advanced Configuration');
        $this->line('   You can now configure advanced options for each queue individually');
        $this->newLine();

        // Configure each selected queue with the new flow
        $configurations = [];
        foreach ($selectedQueues as $queueName) {
            $queue = collect($discoveredQueues)->firstWhere('name', $queueName);
            $processes = $queueProcesses[$queueName];

            $config = $this->configureQueueAdvanced($queueName, $queue, [], $processes);
            $configurations[$queueName] = $config;
        }

        // Show final configuration
        info('📝 Final Configuration:');
        foreach ($configurations as $name => $config) {
            $configLine = "<info>{$name}:</info> {$config['processes']} process(es), " .
                         "timeout: {$config['timeout']}s, memory: {$config['memory']}MB";

            // Add note about consolidated queues
            if ($name === 'default' && $shouldConsolidate && ! empty($selectedConsolidationQueues)) {
                $configLine .= ' (+ handling: ' . implode(', ', $selectedConsolidationQueues) . ')';
            }

            $this->line($configLine);
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
     * Configure a single queue with the new advanced flow.
     */
    protected function configureQueueAdvanced(string $queueName, array $queueInfo, array $globalDefaults = [], int $processes = 1): array
    {
        // Get suggested defaults
        $suggested = $this->discoveryService->getDefaultConfigForQueue($queueName, $queueInfo);

        // Merge with global defaults if provided
        if (! empty($globalDefaults)) {
            $suggested = array_merge($suggested, $globalDefaults);
        }

        info("Configure advanced options for: {$queueName}");

        // Ask if they want to configure advanced options for this specific queue
        $configureAdvanced = confirm(
            "Set advanced options for '{$queueName}'? (Otherwise use defaults)",
            false
        );

        if (! $configureAdvanced) {
            // Use defaults and continue to next queue
            info("✅ Using defaults for {$queueName}");
            $this->newLine();

            return [
                'queue' => $queueName,
                'connection' => $suggested['connection'],
                'tries' => $suggested['tries'],
                'timeout' => $suggested['timeout'],
                'sleep' => $suggested['sleep'],
                'memory' => $suggested['memory'],
                'processes' => $processes,
                'max_jobs' => $suggested['max_jobs'],
                'max_time' => $suggested['max_time'],
            ];
        }

        // Configure advanced options for this queue
        info("⚙️ Advanced configuration for: {$queueName}");

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

        $sleep = (int) text(
            label: 'Sleep time between jobs (seconds)',
            default: (string) $suggested['sleep'],
            hint: 'How long to wait when no jobs are available'
        );

        $maxJobs = (int) text(
            label: 'Max jobs per worker before restart',
            default: (string) $suggested['max_jobs'],
            hint: 'Worker restarts after processing this many jobs'
        );

        $maxTime = (int) text(
            label: 'Max worker runtime (seconds)',
            default: (string) $suggested['max_time'],
            hint: 'Worker restarts after running for this long'
        );

        info("✅ Advanced configuration completed for {$queueName}");
        $this->newLine();

        return [
            'queue' => $queueName,
            'connection' => $suggested['connection'],
            'tries' => $tries,
            'timeout' => $timeout,
            'sleep' => $sleep,
            'memory' => $memory,
            'processes' => $processes,
            'max_jobs' => $maxJobs,
            'max_time' => $maxTime,
        ];
    }

    /**
     * Configure a single queue interactively.
     */
    protected function configureQueue(string $queueName, array $queueInfo, array $globalDefaults = [], bool $useSimplified = false): array
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

        // Merge with global defaults if provided
        if (! empty($globalDefaults)) {
            $suggested = array_merge($suggested, $globalDefaults);
        }

        $processes = (int) text(
            label: 'Number of worker processes',
            default: (string) $suggested['processes'],
            hint: 'More processes = higher concurrency'
        );

        if ($useSimplified) {
            // In simplified mode, only ask for worker count and use defaults for everything else
            return [
                'queue' => $queueName,
                'connection' => $suggested['connection'],
                'tries' => $suggested['tries'],
                'timeout' => $suggested['timeout'],
                'sleep' => $suggested['sleep'],
                'memory' => $suggested['memory'],
                'processes' => $processes,
                'max_jobs' => $suggested['max_jobs'],
                'max_time' => $suggested['max_time'],
            ];
        }

        // Full configuration mode
        $allowOverride = ! empty($globalDefaults) ? confirm('Override global defaults for this queue?', false) : true;

        $timeout = $suggested['timeout'];
        $memory = $suggested['memory'];
        $tries = $suggested['tries'];

        if ($allowOverride || empty($globalDefaults)) {
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
        }

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
