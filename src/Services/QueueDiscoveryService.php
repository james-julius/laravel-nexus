<?php

namespace JamesJulius\LaravelNexus\Services;

use Illuminate\Support\Facades\File;
use ReflectionClass;

final class QueueDiscoveryService
{
    /**
     * Discover all queues used by job classes in the application.
     */
    public function discoverQueues(): array
    {
        $queues = collect();
        $jobFiles = $this->getJobFiles();

        foreach ($jobFiles as $file) {
            $className = $this->getClassNameFromFile($file);
            if ($className && class_exists($className)) {
                $queueName = $this->getQueueFromClass($className);
                if ($queueName) {
                    $queues->push([
                        'queue' => $queueName,
                        'job_class' => $className,
                        'job_name' => class_basename($className),
                        'file' => $file,
                        'type' => $this->getClassType($className),
                    ]);
                }
            }
        }

        // Group by queue name and include default queue info
        $groupedQueues = $queues->groupBy('queue')->map(function ($jobs, $queueName) {
            return [
                'name' => $queueName,
                'jobs' => $jobs->pluck('job_class')->unique()->values()->toArray(),
                'job_details' => $jobs->unique('job_class')->values()->toArray(),
                'job_count' => $jobs->unique('job_class')->count(),
                'detected' => true,
            ];
        });

        // Add some common Laravel queues that might not be detected
        $commonQueues = [
            'default' => [
                'name' => 'default',
                'jobs' => ['General application jobs'],
                'job_details' => [],
                'job_count' => 0,
                'detected' => false,
            ],
            'failed' => [
                'name' => 'failed',
                'jobs' => ['Failed job retries'],
                'job_details' => [],
                'job_count' => 0,
                'detected' => false,
            ],
            'notifications' => [
                'name' => 'notifications',
                'jobs' => ['Email and push notifications'],
                'job_details' => [],
                'job_count' => 0,
                'detected' => false,
            ],
            'mail' => [
                'name' => 'mail',
                'jobs' => ['Email sending jobs'],
                'job_details' => [],
                'job_count' => 0,
                'detected' => false,
            ],
            'broadcasting' => [
                'name' => 'broadcasting',
                'jobs' => ['WebSocket/real-time updates'],
                'job_details' => [],
                'job_count' => 0,
                'detected' => false,
            ],
        ];

        // Merge discovered queues with common ones (discovered takes precedence)
        foreach ($commonQueues as $name => $info) {
            if (! $groupedQueues->has($name)) {
                $groupedQueues->put($name, $info);
            }
        }

        return $groupedQueues->sortKeys()->values()->toArray();
    }

    /**
     * Get all job files in the application.
     */
    protected function getJobFiles(): array
    {
        $jobDirectories = [
            app_path('Jobs'),
            app_path('Mail'), // Mail classes often have queue properties
            app_path('Events'), // Broadcasting events with broadcastQueue()
            app_path('Notifications'), // Notification classes can be queued
            app_path('Listeners'), // Event listeners can be queued
        ];

        $files = [];
        foreach ($jobDirectories as $directory) {
            if (File::exists($directory)) {
                $files = array_merge($files, File::allFiles($directory));
            }
        }

        return collect($files)
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->map(fn ($file) => $file->getPathname())
            ->toArray();
    }

    /**
     * Extract the class name from a PHP file.
     */
    protected function getClassNameFromFile(string $filePath): ?string
    {
        $content = File::get($filePath);

        // Extract namespace
        preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
        $namespace = $namespaceMatches[1] ?? '';

        // Extract class name
        preg_match('/class\s+(\w+)/', $content, $classMatches);
        $className = $classMatches[1] ?? '';

        if ($className) {
            return $namespace ? $namespace . '\\' . $className : $className;
        }

        return null;
    }

    /**
     * Get the queue name from a job class.
     */
    protected function getQueueFromClass(string $className): ?string
    {
        try {
            $reflection = new ReflectionClass($className);
            $content = File::get($reflection->getFileName());

            // 1. Check for explicit $queue property
            if ($reflection->hasProperty('queue')) {
                $property = $reflection->getProperty('queue');
                $property->setAccessible(true);

                // If it's a static property, get the default value
                if ($property->isStatic()) {
                    $value = $property->getValue();
                    if ($value) {
                        return $value;
                    }
                }

                // For instance properties, try to get default value
                try {
                    $instance = $reflection->newInstanceWithoutConstructor();
                    $defaultValue = $property->getValue($instance);
                    if ($defaultValue) {
                        return $defaultValue;
                    }
                } catch (\Exception $e) {
                    // Can't instantiate, check source code
                }
            }

            // 2. Look for public $queue property declaration in source
            if (preg_match('/public\s+\$queue\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                return $matches[1];
            }

            // 3. Look for $this->queue assignment in constructor or methods
            if (preg_match('/\$this->queue\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                return $matches[1];
            }

            // 4. Look for onQueue() method calls (Jobs)
            if (preg_match('/->onQueue\([\'"]([^\'"]+)[\'"]\)/', $content, $matches)) {
                return $matches[1];
            }

            // 5. Check for broadcastQueue() method (Broadcasting Events)
            if ($reflection->hasMethod('broadcastQueue')) {
                // Look for return statement in broadcastQueue method
                if (preg_match('/public\s+function\s+broadcastQueue\(\)[\s\S]*?return\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                    return $matches[1];
                }

                // Also check if method exists and try to call it statically if possible
                try {
                    $method = $reflection->getMethod('broadcastQueue');
                    if ($method->isPublic() && ! $method->isAbstract()) {
                        // Default for broadcast events if we can't determine the specific queue
                        return 'broadcasting';
                    }
                } catch (\Exception $e) {
                    // Method exists but we can't call it
                }
            }

            // 6. Check if implements ShouldBroadcast (Broadcasting Events)
            if ($reflection->implementsInterface(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class)) {
                return 'broadcasting';
            }

            // 7. Check if implements ShouldQueue (Queued classes)
            if ($reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class)) {
                // Try to determine queue from class type
                if (str_contains($className, '\\Mail\\')) {
                    return 'mail';
                }
                if (str_contains($className, '\\Notifications\\')) {
                    return 'notifications';
                }
                if (str_contains($className, '\\Listeners\\')) {
                    return 'listeners';
                }
                if (str_contains($className, '\\Jobs\\')) {
                    return 'default'; // Jobs without explicit queue go to default
                }
            }

            // 8. Special cases for Mail classes (they use Queueable trait)
            if ($reflection->isSubclassOf(\Illuminate\Mail\Mailable::class)) {
                // Check if mail is queueable
                $traits = $reflection->getTraitNames();
                if (in_array('Illuminate\\Bus\\Queueable', $traits)) {
                    return 'mail';
                }
            }

            // 9. Special cases for Notification classes
            if ($reflection->isSubclassOf(\Illuminate\Notifications\Notification::class)) {
                // Check if notification is queueable
                $traits = $reflection->getTraitNames();
                if (in_array('Illuminate\\Bus\\Queueable', $traits) ||
                    $reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class)) {
                    return 'notifications';
                }
            }

            // 10. Look for via() method in Notifications that might specify queued channels
            if ($reflection->hasMethod('via')) {
                if (preg_match('/[\'"]mail[\'"]/', $content) && preg_match('/implements.*ShouldQueue/', $content)) {
                    return 'notifications';
                }
            }

            return null;
        } catch (\Exception $e) {
            // If we can't reflect on the class, skip it
            return null;
        }
    }

    /**
     * Generate suggested configuration for discovered queues.
     */
    public function generateQueueConfigs(array $discoveredQueues): array
    {
        $configs = [];

        foreach ($discoveredQueues as $queue) {
            $name = $queue['name'];

            // Generate sensible defaults based on queue type
            $config = $this->getDefaultConfigForQueue($name, $queue);
            $configs[$name] = $config;
        }

        return $configs;
    }

    /**
     * Get default configuration for a specific queue type.
     */
    public function getDefaultConfigForQueue(string $queueName, array $queueInfo): array
    {
        $baseConfig = [
            'queue' => $queueName,
            'connection' => env('QUEUE_CONNECTION', 'database'),
            'tries' => 3,
            'timeout' => 60,
            'sleep' => 3,
            'memory' => 128,
            'processes' => 1,
            'max_jobs' => 1000,
            'max_time' => 3600,
        ];

        // Customize based on queue type
        switch ($queueName) {
            case 'broadcasting':
                return array_merge($baseConfig, [
                    'timeout' => 30,
                    'sleep' => 1,
                    'processes' => 1,
                    'max_jobs' => 500,
                ]);

            case 'edi-processing':
                return array_merge($baseConfig, [
                    'timeout' => 300,
                    'memory' => 256,
                    'processes' => 2,
                    'max_jobs' => 100,
                ]);

            case 'mail':
                return array_merge($baseConfig, [
                    'timeout' => 120,
                    'processes' => 1,
                    'max_jobs' => 500,
                ]);

            case 'default':
                return array_merge($baseConfig, [
                    'processes' => 2,
                ]);

            default:
                return $baseConfig;
        }
    }

    /**
     * Get queue statistics from the database.
     */
    public function getQueueStats(): array
    {
        try {
            // This assumes database queue driver
            $stats = \DB::table('jobs')
                ->select('queue', \DB::raw('count(*) as pending'))
                ->groupBy('queue')
                ->get()
                ->keyBy('queue')
                ->toArray();

            $failedStats = \DB::table('failed_jobs')
                ->select('queue', \DB::raw('count(*) as failed'))
                ->groupBy('queue')
                ->get()
                ->keyBy('queue')
                ->toArray();

            // Merge the stats
            foreach ($failedStats as $queue => $failed) {
                if (isset($stats[$queue])) {
                    $stats[$queue]->failed = $failed->failed;
                } else {
                    $stats[$queue] = (object) ['pending' => 0, 'failed' => $failed->failed];
                }
            }

            return collect($stats)->map(function ($stat) {
                return [
                    'pending' => $stat->pending ?? 0,
                    'failed' => $stat->failed ?? 0,
                ];
            })->toArray();

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get jobs for a specific queue.
     */
    public function getJobsForQueue(string $queueName): \Illuminate\Support\Collection
    {
        $queues = collect();
        $jobFiles = $this->getJobFiles();

        foreach ($jobFiles as $file) {
            $className = $this->getClassNameFromFile($file);
            if ($className && class_exists($className)) {
                $classQueueName = $this->getQueueFromClass($className);
                if ($classQueueName === $queueName) {
                    $queues->push([
                        'name' => class_basename($className),
                        'class' => $className,
                        'type' => $this->getClassType($className),
                        'file' => str_replace(base_path(), '', $file),
                    ]);
                }
            }
        }

        return $queues->sortBy('name');
    }

    /**
     * Determine the type of Laravel class.
     */
    protected function getClassType(string $className): string
    {
        try {
            $reflection = new ReflectionClass($className);

            if (str_contains($className, '\\Jobs\\')) {
                return 'Job';
            }

            if (str_contains($className, '\\Events\\')) {
                return 'Event';
            }

            if (str_contains($className, '\\Mail\\')) {
                return 'Mail';
            }

            if (str_contains($className, '\\Notifications\\')) {
                return 'Notification';
            }

            if (str_contains($className, '\\Listeners\\')) {
                return 'Listener';
            }

            if ($reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class)) {
                return 'Queueable';
            }

            if ($reflection->implementsInterface(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class)) {
                return 'Broadcasting';
            }

            return 'Class';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Extract queue names from file content using regex patterns.
     */
    public function extractQueuesFromContent(string $content): array
    {
        $queues = [];

        // Skip commented out lines
        $lines = explode("\n", $content);
        $filteredContent = collect($lines)
            ->filter(fn($line) => !preg_match('/^\s*\/\//', trim($line)))
            ->implode("\n");

        // 1. Look for public $queue property declaration
        if (preg_match_all('/public\s+\$queue\s*=\s*[\'"]([^\'"]+)[\'"]/', $filteredContent, $matches)) {
            $queues = array_merge($queues, $matches[1]);
        }

        // 2. Look for $this->queue assignment
        if (preg_match_all('/\$this->queue\s*=\s*[\'"]([^\'"]+)[\'"]/', $filteredContent, $matches)) {
            $queues = array_merge($queues, $matches[1]);
        }

        // 3. Look for onQueue() method calls
        if (preg_match_all('/->onQueue\([\'"]([^\'"]+)[\'"]\)/', $filteredContent, $matches)) {
            $queues = array_merge($queues, $matches[1]);
        }

        // 4. Look for broadcastQueue() method returns
        if (preg_match_all('/return\s*[\'"]([^\'"]+)[\'"]/', $filteredContent, $matches)) {
            $queues = array_merge($queues, $matches[1]);
        }

        return array_unique($queues);
    }
}
