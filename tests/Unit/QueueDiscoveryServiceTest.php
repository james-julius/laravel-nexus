<?php

use Illuminate\Support\Facades\File;
use JamesJulius\LaravelNexus\Services\QueueDiscoveryService;

beforeEach(function () {
    $this->service = app(QueueDiscoveryService::class);
});

it('can discover queues from files', function () {
    // Create a temporary job file for testing
    $jobPath = app_path('Jobs/TestJob.php');
    $jobContent = '<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
class TestJob implements ShouldQueue
{
    public $queue = "test-queue";
}';

    File::ensureDirectoryExists(dirname($jobPath));
    File::put($jobPath, $jobContent);

    $queues = $this->service->discoverQueues();

    expect($queues)->toBeArray();

    // Clean up
    File::delete($jobPath);
});

it('extracts queue names from queue property', function () {
    $content = 'public $queue = "payments";';
    $queues = $this->service->extractQueuesFromContent($content);

    expect($queues)->toContain('payments');
});

it('extracts queue names from onQueue method', function () {
    $content = '$this->onQueue("notifications");';
    $queues = $this->service->extractQueuesFromContent($content);

    expect($queues)->toContain('notifications');
});

it('extracts queue names from broadcastQueue method', function () {
    $content = 'public function broadcastQueue() { return "broadcasting"; }';
    $queues = $this->service->extractQueuesFromContent($content);

    expect($queues)->toContain('broadcasting');
});

it('extracts multiple queue names from the same file', function () {
    $content = '
        public $queue = "queue1";
        $this->onQueue("queue2");
        return "queue3";
    ';
    $queues = $this->service->extractQueuesFromContent($content);

    expect($queues)->toContain('queue1');
    expect($queues)->toContain('queue2');
    expect($queues)->toContain('queue3');
});

it('returns empty array when no queues found', function () {
    $content = 'class TestClass { }';
    $queues = $this->service->extractQueuesFromContent($content);

    expect($queues)->toBeArray();
    expect($queues)->toBeEmpty();
});

it('ignores commented out queue definitions', function () {
    $content = '// public $queue = "commented-queue";';
    $queues = $this->service->extractQueuesFromContent($content);

    expect($queues)->not->toContain('commented-queue');
});

it('handles single and double quotes', function () {
    $content = "
        public \$queue = 'single-quotes';
        \$this->onQueue(\"double-quotes\");
    ";
    $queues = $this->service->extractQueuesFromContent($content);

    expect($queues)->toContain('single-quotes');
    expect($queues)->toContain('double-quotes');
});
