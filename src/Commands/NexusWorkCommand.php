<?php

namespace JamesJulius\LaravelNexus\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use JamesJulius\LaravelNexus\Services\QueueDiscoveryService;
use Symfony\Component\Process\Process as SymfonyProcess;

class NexusWorkCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nexus:work
                            {--log : Stream worker logs in real-time}
                            {--watch : Watch for file changes and auto-reload workers}
                            {--detailed : Enable detailed logging with job IDs and dates}
                            {--stop : Stop all running queue workers}
                            {--restart : Restart all queue workers}
                            {--status : Show status of running workers}
                            {--worker= : Start only specific worker}';

    /**
     * The console command description.
     */
    protected $description = 'Start and manage Laravel Nexus queue workers';

    /**
     * Array to store running processes.
     */
    protected array $processes = [];

    /**
     * Process ID file for tracking workers.
     */
    protected string $pidFile;

    /**
     * Configuration for workers.
     */
    protected array $config;

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

        $this->pidFile = storage_path('app/nexus.pids');
        $this->config = config('nexus');
        $this->discoveryService = $discoveryService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('watch')) {
            return $this->watchForChanges();
        }

        if ($this->option('log') || $this->option('detailed')) {
            return $this->streamLogs();
        }

        if ($this->option('stop')) {
            return $this->stopWorkers();
        }

        if ($this->option('restart')) {
            return $this->restartWorkers();
        }

        if ($this->option('status')) {
            return $this->showStatus();
        }

        return $this->startWorkers();
    }

    /**
     * Start queue workers based on configuration.
     */
    protected function startWorkers(): int
    {
        $this->info('🚀 Starting Laravel Nexus...');

        // Validate configuration exists
        if (empty($this->config)) {
            $this->error('❌ No Nexus configuration found!');
            $this->line('💡 Run <comment>php artisan nexus:configure</comment> to set up your workers');

            return 1;
        }

        if (empty($this->config['workers'])) {
            $this->error('❌ No workers configured!');
            $this->line('💡 Run <comment>php artisan nexus:configure</comment> to set up your workers');

            return 1;
        }

        // Show helpful hint about available options on first run
        if (! $this->option('log') && ! $this->option('watch')) {
            $this->line('💡 <comment>Tip:</comment> Use <comment>--log</comment> for live logs, <comment>--watch</comment> for file watching + auto-reload, or <comment>--detailed</comment> for detailed job logs');
            $this->newLine();
        }

        // Check if workers are already running
        if ($this->hasRunningWorkers()) {
            $this->warn('⚠️  Queue workers are already running. Use --restart to restart them.');

            return 1;
        }

        // Setup signal handlers for graceful shutdown
        $this->setupSignalHandlers();

        // Validate worker option if provided
        if ($this->option('worker')) {
            $workerName = $this->option('worker');
            if (! isset($this->config['workers'][$workerName])) {
                $this->error("Worker configuration not found for: {$workerName}");
                $availableWorkers = array_keys($this->config['workers']);
                $this->line('Available workers: ' . implode(', ', $availableWorkers));

                return 1;
            }
        }

        $workerConfig = $this->option('worker')
            ? [$this->option('worker') => $this->config['workers'][$this->option('worker')]]
            : $this->config['workers'];

        foreach ($workerConfig as $name => $config) {
            $this->startWorker($name, $config);
        }

        if (empty($this->processes)) {
            $this->error('❌ No workers started! Check your configuration.');

            return 1;
        }

        $this->info('✅ Started ' . count($this->processes) . ' queue workers');
        $this->displayWorkerTable();

        // Save process IDs
        $this->savePids();

        // Keep the manager running and monitor workers
        $this->monitorWorkers();

        return 0;
    }

    /**
     * Start a specific worker with multiple processes.
     */
    protected function startWorker(string $name, array $config): void
    {
        $processCount = $config['processes'] ?? 1;

        // Validate worker configuration
        $requiredFields = ['queue', 'connection', 'tries', 'timeout', 'sleep', 'memory', 'max_jobs', 'max_time'];
        foreach ($requiredFields as $field) {
            if (! isset($config[$field])) {
                $this->warn("⚠️  Missing configuration field '{$field}' for worker '{$name}'. Using defaults.");
            }
        }

        for ($i = 1; $i <= $processCount; $i++) {
            $processName = $processCount > 1 ? "{$name}-{$i}" : $name;

            try {
                $command = $this->buildWorkerCommand($config, $processName);

                $this->line('  🔧 Command: <comment>' . implode(' ', array_slice($command, 0, 5)) . '...</comment>');

                $process = new SymfonyProcess($command);
                $process->setTimeout(null);
                $process->start();

                if (! $process->isRunning()) {
                    $this->error("❌ Failed to start worker: {$processName}");
                    $this->line('Error output: ' . $process->getErrorOutput());

                    continue;
                }

                $this->processes[$processName] = [
                    'process' => $process,
                    'config' => $config,
                    'name' => $processName,
                    'started_at' => now(),
                ];

                $this->line("  → Started worker: <info>{$processName}</info> (PID: {$process->getPid()})");
            } catch (\Exception $e) {
                $this->error("❌ Exception starting worker {$processName}: " . $e->getMessage());
            }
        }
    }

    /**
     * Build the artisan queue:work command for a worker.
     */
    protected function buildWorkerCommand(array $config, string $processName): array
    {
        // Set defaults for missing configuration values
        $defaults = [
            'connection' => env('QUEUE_CONNECTION', 'database'),
            'queue' => 'default',
            'tries' => 3,
            'timeout' => 60,
            'sleep' => 3,
            'memory' => 128,
            'max_jobs' => 1000,
            'max_time' => 3600,
        ];

        $config = array_merge($defaults, $config);

        $command = [
            PHP_BINARY,
            'artisan',
            'queue:work',
            $config['connection'],
            '--queue=' . $config['queue'],
            '--tries=' . $config['tries'],
            '--timeout=' . $config['timeout'],
            '--sleep=' . $config['sleep'],
            '--memory=' . $config['memory'],
            '--max-jobs=' . $config['max_jobs'],
            '--max-time=' . $config['max_time'],
            '--name=' . ($this->config['prefix'] ?? 'nexus') . ':' . $processName,
        ];

        // Add environment-specific options
        if (app()->environment('local')) {
            $command[] = '--verbose';
        }

        // Add verbose output if in log, watch, or detailed mode
        if ($this->option('log') || $this->option('watch') || $this->option('detailed')) {
            $command[] = '--verbose';
        }

        return $command;
    }

    /**
     * Monitor running workers and handle restarts.
     */
    protected function monitorWorkers(): void
    {
        $this->info('👀 Nexus monitoring workers... (Press Ctrl+C to stop)');

        $lastRestartCheck = 0;

        while (true) {
            // Check for restart signal every 5 seconds
            if (time() - $lastRestartCheck > 5) {
                if ($this->shouldRestart()) {
                    $this->info('🔄 Restart signal detected, restarting workers...');
                    $this->restartAllProcesses();
                }
                $lastRestartCheck = time();
            }

            // Check if any processes have died
            foreach ($this->processes as $name => $worker) {
                if (! $worker['process']->isRunning()) {
                    $this->warn("⚠️  Worker {$name} has stopped. Restarting...");
                    $this->restartWorkerProcess($name, $worker);
                }
            }

            sleep(1);
        }
    }

    /**
     * Check if workers should restart based on the restart signal file.
     */
    protected function shouldRestart(): bool
    {
        if (! $this->config['auto_restart']) {
            return false;
        }

        $restartFile = $this->config['restart_signal_file'];

        if (! file_exists($restartFile)) {
            return false;
        }

        $restartTime = filemtime($restartFile);
        $managerStartTime = $this->processes[array_key_first($this->processes)]['started_at']->timestamp;

        return $restartTime > $managerStartTime;
    }

    /**
     * Restart a specific worker process.
     */
    protected function restartWorkerProcess(string $name, array $worker): void
    {
        // Kill the old process if still running
        if ($worker['process']->isRunning()) {
            $worker['process']->stop(10);
        }

        // Start new process
        $command = $this->buildWorkerCommand($worker['config'], $name);

        // Add verbose output if in log, watch, or detailed mode
        if ($this->option('log') || $this->option('watch') || $this->option('detailed')) {
            $command[] = '--verbose';
        }

        $process = new SymfonyProcess($command);
        $process->setTimeout(null);
        $process->start();

        $this->processes[$name] = [
            'process' => $process,
            'config' => $worker['config'],
            'name' => $name,
            'started_at' => now(),
            'color' => $worker['color'] ?? $this->getWorkerColor($name), // Preserve or assign color
        ];

        $this->line("  ✅ Restarted worker: <info>{$name}</info> (PID: {$process->getPid()})");
    }

    /**
     * Restart all worker processes.
     */
    protected function restartAllProcesses(): void
    {
        foreach ($this->processes as $name => $worker) {
            $this->restartWorkerProcess($name, $worker);
        }
        $this->savePids();
    }

    /**
     * Stop all running workers.
     */
    protected function stopWorkers(): int
    {
        $this->info('🛑 Stopping all queue workers...');

        $pids = $this->loadPids();

        if (empty($pids)) {
            $this->info('No worker processes found to stop.');

            return 0;
        }

        foreach ($pids as $name => $pid) {
            if ($this->isProcessRunning($pid)) {
                posix_kill($pid, SIGTERM);
                $this->line("  → Stopped worker: <info>{$name}</info> (PID: {$pid})");
            }
        }

        // Clean up PID file
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }

        $this->info('✅ All workers stopped');
        exit(0);
    }

    /**
     * Restart workers by stopping and starting them.
     */
    protected function restartWorkers(): int
    {
        $this->stopWorkers();
        sleep(2); // Give processes time to shut down

        return $this->startWorkers();
    }

    /**
     * Show status of running workers.
     */
    protected function showStatus(): int
    {
        $this->info('📊 Worker Status:');

        $pids = $this->loadPids();

        if (empty($pids)) {
            $this->line('No queue workers are currently running.');

            return 0;
        }

        $this->table(
            ['Worker', 'PID', 'Status', 'Memory (MB)', 'Uptime'],
            collect($pids)->map(function ($pid, $name) {
                $isRunning = $this->isProcessRunning($pid);

                return [
                    $name,
                    $pid,
                    $isRunning ? '<info>Running</info>' : '<error>Stopped</error>',
                    $isRunning ? $this->getProcessMemory($pid) : 'N/A',
                    $isRunning ? $this->getProcessUptime($pid) : 'N/A',
                ];
            })->values()->toArray()
        );

        return 0;
    }

    /**
     * Display worker configuration table.
     */
    protected function displayWorkerTable(): void
    {
        $workers = [];
        foreach ($this->processes as $name => $worker) {
            $config = $worker['config'];
            $workers[] = [
                $name,
                $config['queue'],
                $config['connection'],
                $worker['process']->getPid(),
                $config['timeout'] . 's',
                $config['tries'],
            ];
        }

        $this->table(
            ['Worker', 'Queue', 'Connection', 'PID', 'Timeout', 'Tries'],
            $workers
        );
    }

    /**
     * Setup signal handlers for graceful shutdown.
     */
    protected function setupSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        pcntl_signal(SIGINT, [$this, 'handleShutdown']);
    }

    /**
     * Handle shutdown signals.
     */
    public function handleShutdown(): void
    {
        $this->info("\n🛑 Shutting down gracefully...");

        foreach ($this->processes as $name => $worker) {
            if ($worker['process']->isRunning()) {
                $this->line("  → Stopping {$name}...");
                $worker['process']->stop(10);
            }
        }

        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }

        $this->info('✅ All workers stopped');
        exit(0);
    }

    /**
     * Check if workers are already running.
     */
    protected function hasRunningWorkers(): bool
    {
        $pids = $this->loadPids();

        foreach ($pids as $pid) {
            if ($this->isProcessRunning($pid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save process IDs to file.
     */
    protected function savePids(): void
    {
        $pids = [];
        foreach ($this->processes as $name => $worker) {
            $pids[$name] = $worker['process']->getPid();
        }

        file_put_contents($this->pidFile, json_encode($pids));
    }

    /**
     * Load process IDs from file.
     */
    protected function loadPids(): array
    {
        if (! file_exists($this->pidFile)) {
            return [];
        }

        $content = file_get_contents($this->pidFile);

        return json_decode($content, true) ?: [];
    }

    /**
     * Check if a process is running.
     */
    protected function isProcessRunning(int $pid): bool
    {
        return posix_getpgid($pid) !== false;
    }

    /**
     * Get process memory usage.
     */
    protected function getProcessMemory(int $pid): string
    {
        $status = @file_get_contents("/proc/{$pid}/status");
        if (! $status) {
            return 'N/A';
        }

        if (preg_match('/VmRSS:\s+(\d+)\s+kB/', $status, $matches)) {
            return round($matches[1] / 1024, 1);
        }

        return 'N/A';
    }

    /**
     * Get process uptime.
     */
    protected function getProcessUptime(int $pid): string
    {
        $stat = @file_get_contents("/proc/{$pid}/stat");
        if (! $stat) {
            return 'N/A';
        }

        $parts = explode(' ', $stat);
        $starttime = $parts[21] ?? 0;
        $uptime = file_get_contents('/proc/uptime');
        $uptimeSeconds = floatval(explode(' ', $uptime)[0]);
        $clockTicks = sysconf(30); // _SC_CLK_TCK

        $seconds = $uptimeSeconds - ($starttime / $clockTicks);

        if ($seconds < 60) {
            return round($seconds) . 's';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . 'm';
        } else {
            return round($seconds / 3600, 1) . 'h';
        }
    }

    /**
     * Watch for file changes and auto-reload workers.
     */
    protected function watchForChanges(): int
    {
        $this->info('👀 Starting Laravel Nexus in file watch mode...');

        // Check if workers are already running
        if ($this->hasRunningWorkers()) {
            $this->warn('⚠️  Queue workers are already running. Stopping them first...');
            $this->stopWorkers();
            sleep(2);
        }

        // Setup signal handlers for graceful shutdown
        $this->setupSignalHandlers();

        $workerConfig = $this->option('worker')
            ? [$this->option('worker') => $this->config['workers'][$this->option('worker')]]
            : $this->config['workers'];

        // Start workers with output capturing for logs
        foreach ($workerConfig as $name => $config) {
            $this->startWorkerWithLogging($name, $config);
        }

        $this->info('✅ Started ' . count($this->processes) . ' queue workers with file watching');
        $this->info('👁️  Watching for file changes... (Press Ctrl+C to stop)');
        $this->line(''); // Empty line for separation

        // Save process IDs
        $this->savePids();

        // Monitor files, handle restarts, and stream logs
        $this->monitorFilesAndLogs();

        return 0;
    }

    /**
     * Monitor files for changes and stream logs.
     */
    protected function monitorFilesAndLogs(): void
    {
        $lastRestartCheck = 0;
        $lastFileCheck = 0;
        $fileHashes = $this->getFileHashes();

        while (true) {
            // Check for restart signal every 5 seconds
            if (time() - $lastRestartCheck > 5) {
                if ($this->shouldRestart()) {
                    $this->info("\n🔄 Restart signal detected, restarting workers...");
                    $this->restartAllProcesses();
                }
                $lastRestartCheck = time();
            }

            // Check for file changes every 2 seconds
            if (time() - $lastFileCheck > 2) {
                $currentHashes = $this->getFileHashes();
                $changedFiles = $this->getChangedFiles($fileHashes, $currentHashes);

                if (! empty($changedFiles)) {
                    $this->info("\n🔄 File changes detected:");
                    foreach (array_slice($changedFiles, 0, 3) as $file) {
                        $this->line("   → {$file}");
                    }
                    if (count($changedFiles) > 3) {
                        $this->line('   → ... and ' . (count($changedFiles) - 3) . ' more files');
                    }
                    $this->info('🚀 Reloading workers...');
                    $this->restartAllProcesses();
                    $fileHashes = $currentHashes;
                }
                $lastFileCheck = time();
            }

            // Read output from all processes (log streaming)
            foreach ($this->processes as $name => $worker) {
                $process = $worker['process'];

                if (! $process->isRunning()) {
                    $this->warn("\n⚠️  Worker {$name} has stopped. Restarting...");
                    $this->restartWorkerProcess($name, $worker);

                    continue;
                }

                // Read incremental output
                $incrementalOutput = $process->getIncrementalOutput();
                $incrementalErrorOutput = $process->getIncrementalErrorOutput();

                if (! empty($incrementalOutput)) {
                    $this->formatAndDisplayLog($name, $incrementalOutput, $worker['color'], 'info');
                }

                if (! empty($incrementalErrorOutput)) {
                    $this->formatAndDisplayLog($name, $incrementalErrorOutput, $worker['color'], 'error');
                }
            }

            // Check signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            usleep(200000); // Sleep 200ms to prevent excessive CPU usage
        }
    }

    /**
     * Get hashes of files to watch.
     */
    protected function getFileHashes(): array
    {
        $watchPaths = [
            'app',
            'config',
            'routes',
            'database/migrations',
            'resources/views',
        ];

        $hashes = [];

        foreach ($watchPaths as $path) {
            $fullPath = base_path($path);
            if (! is_dir($fullPath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'env'])) {
                    $relativePath = str_replace(base_path() . '/', '', $file->getPathname());
                    $hashes[$relativePath] = filemtime($file->getPathname());
                }
            }
        }

        return $hashes;
    }

    /**
     * Get files that have changed.
     */
    protected function getChangedFiles(array $oldHashes, array $newHashes): array
    {
        $changed = [];

        // Check for new or modified files
        foreach ($newHashes as $file => $hash) {
            if (! isset($oldHashes[$file]) || $oldHashes[$file] !== $hash) {
                $changed[] = $file;
            }
        }

        // Check for deleted files
        foreach ($oldHashes as $file => $hash) {
            if (! isset($newHashes[$file])) {
                $changed[] = $file . ' (deleted)';
            }
        }

        return $changed;
    }

    /**
     * Start a worker with logging capabilities.
     */
    protected function startWorkerWithLogging(string $name, array $config): void
    {
        $processCount = $config['processes'] ?? 1;

        for ($i = 1; $i <= $processCount; $i++) {
            $processName = $processCount > 1 ? "{$name}-{$i}" : $name;
            $command = $this->buildWorkerCommand($config, $processName);

            // Add verbose output for better logging
            $command[] = '--verbose';

            $process = new SymfonyProcess($command);
            $process->setTimeout(null);
            $process->start();

            $this->processes[$processName] = [
                'process' => $process,
                'config' => $config,
                'name' => $processName,
                'started_at' => now(),
                'color' => $this->getWorkerColor($processName),
            ];

            $this->line("  → Started worker: <info>{$processName}</info> (PID: {$process->getPid()})");
        }
    }

    /**
     * Stream logs from all worker processes.
     */
    protected function streamWorkerLogs(): void
    {
        $lastRestartCheck = 0;

        while (true) {
            // Check for restart signal every 5 seconds
            if (time() - $lastRestartCheck > 5) {
                if ($this->shouldRestart()) {
                    $this->info("\n🔄 Restart signal detected, restarting workers...");
                    $this->restartAllProcesses();
                }
                $lastRestartCheck = time();
            }

            // Read output from all processes
            foreach ($this->processes as $name => $worker) {
                $process = $worker['process'];

                if (! $process->isRunning()) {
                    $this->warn("\n⚠️  Worker {$name} has stopped. Restarting...");
                    $this->restartWorkerProcess($name, $worker);

                    continue;
                }

                // Read incremental output
                $incrementalOutput = $process->getIncrementalOutput();
                $incrementalErrorOutput = $process->getIncrementalErrorOutput();

                if (! empty($incrementalOutput)) {
                    $this->formatAndDisplayLog($name, $incrementalOutput, $worker['color'], 'info');
                }

                if (! empty($incrementalErrorOutput)) {
                    $this->formatAndDisplayLog($name, $incrementalErrorOutput, $worker['color'], 'error');
                }
            }

            // Check signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            usleep(100000); // Sleep 100ms to prevent excessive CPU usage
        }
    }

    /**
     * Format and display log output from workers with enhanced formatting.
     */
    protected function formatAndDisplayLog(string $workerName, string $output, string $color, string $type = 'info'): void
    {
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Enhanced timestamp format based on detailed mode
            if ($this->option('detailed')) {
                $timestamp = now()->format('Y-m-d H:i:s');
            } else {
                $timestamp = now()->format('H:i:s');
            }

            $prefix = "[{$timestamp}] [{$workerName}]";
            $coloredPrefix = "<fg={$color}>{$prefix}</>";

            // Apply color coding to job statuses
            $enhancedLine = $this->colorizeJobStatuses($line);

            // Extract job ID if in detailed mode and job processing is detected
            if ($this->option('detailed')) {
                $enhancedLine = $this->addJobIdToLog($enhancedLine);
            }

            if ($type === 'error') {
                $this->line("{$coloredPrefix} <fg=red>{$enhancedLine}</>");
            } else {
                $this->line("{$coloredPrefix} {$enhancedLine}");
            }
        }
    }

    /**
     * Colorize job status indicators in log output.
     */
    protected function colorizeJobStatuses(string $line): string
    {
        // Color patterns for different job statuses
        $patterns = [
            // RUNNING/PROCESSING status - Orange
            '/\b(Processing|RUNNING)\b/' => '<fg=yellow;options=bold>$1</>',

            // SUCCESS/DONE status - Green
            '/\b(Processed|DONE|SUCCESS|successful|completed|finished)\b/' => '<fg=green;options=bold>$1</>',

            // FAILED/ERROR status - Red
            '/\b(Failed|FAILED|ERROR|exception|error)\b/' => '<fg=red;options=bold>$1</>',

            // Job class names - Cyan
            '/\\\\([A-Z][a-zA-Z0-9_]*Job)\b/' => '\\<fg=cyan>$1</>',

            // Queue names - Magenta
            '/\[([a-z-_]+)\]/' => '[<fg=magenta>$1</>]',

            // Memory usage - Blue
            '/(\d+(\.\d+)?)\s*(MB|KB|GB)\b/' => '<fg=blue>$1$3</>',

            // Duration/Time - Yellow
            '/(\d+(\.\d+)?)\s*(ms|seconds?|s)\b/' => '<fg=yellow>$1$3</>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $line = preg_replace($pattern, $replacement, $line);
        }

        return $line;
    }

    /**
     * Add job ID information to log line in detailed mode.
     */
    protected function addJobIdToLog(string $line): string
    {
        // Extract job ID from Laravel queue worker output
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+.*?\.INFO:.*?(\{.*?\})/', $line, $matches)) {
            // Parse JSON payload to extract job info
            if (isset($matches[2])) {
                $payload = json_decode($matches[2], true);
                if (isset($payload['id'])) {
                    $jobId = substr($payload['id'], 0, 8); // Show first 8 chars of job ID
                    $line = preg_replace('/(\[.*?\]\s+)/', '$1<fg=blue>[ID:' . $jobId . ']</> ', $line);
                }
            }
        }

        // Also handle simpler job processing messages
        if (preg_match('/Processing:\s+(.+?)(\s+\[|$)/', $line, $matches)) {
            $jobClass = basename(str_replace('\\', '/', $matches[1]));
            $line = str_replace($matches[1], "<fg=cyan>{$jobClass}</>", $line);
        }

        return $line;
    }

    /**
     * Get a color for the worker name for better visual distinction.
     */
    protected function getWorkerColor(string $workerName): string
    {
        $colors = ['cyan', 'green', 'yellow', 'blue', 'magenta', 'white'];
        $hash = crc32($workerName);

        return $colors[abs($hash) % count($colors)];
    }

    /**
     * Stream worker logs in real-time.
     */
    protected function streamLogs(): int
    {
        $this->info('📺 Starting Laravel Nexus with live log streaming...');

        // Check if workers are already running
        if ($this->hasRunningWorkers()) {
            $this->warn('⚠️  Queue workers are already running. Stopping them first...');
            $this->stopWorkers();
            sleep(2);
        }

        // Setup signal handlers for graceful shutdown
        $this->setupSignalHandlers();

        $workerConfig = $this->option('worker')
            ? [$this->option('worker') => $this->config['workers'][$this->option('worker')]]
            : $this->config['workers'];

        // Start workers with output capturing
        foreach ($workerConfig as $name => $config) {
            $this->startWorkerWithLogging($name, $config);
        }

        $this->info('✅ Started ' . count($this->processes) . ' queue workers with log streaming');
        $this->info('📺 Streaming logs... (Press Ctrl+C to stop)');
        $this->line(''); // Empty line for separation

        // Save process IDs
        $this->savePids();

        // Monitor and stream logs
        $this->streamWorkerLogs();

        return 0;
    }
}
