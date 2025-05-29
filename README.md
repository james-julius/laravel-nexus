# Laravel Nexus

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jamesjulius/laravel-nexus.svg?style=flat-square)](https://packagist.org/packages/jamesjulius/laravel-nexus)
[![Total Downloads](https://img.shields.io/packagist/dt/jamesjulius/laravel-nexus.svg?style=flat-square)](https://packagist.org/packages/jamesjulius/laravel-nexus)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jamesjulius/laravel-nexus/ci.yml?branch=main&label=tests&style=flat-square)](https://github.com/jamesjulius/laravel-nexus/actions?query=workflow%3Aci+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jamesjulius/laravel-nexus/ci.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/jamesjulius/laravel-nexus/actions?query=workflow%3Aci+branch%3Amain)
[![License](https://img.shields.io/packagist/l/jamesjulius/laravel-nexus.svg?style=flat-square)](https://packagist.org/packages/jamesjulius/laravel-nexus)

**Laravel Nexus** is your central hub for queue worker management. It automatically discovers your queues, provides interactive configuration, and manages multiple workers with beautiful real-time logging and hot reload capabilities.

## 🤔 Why Laravel Nexus?

**Tired of this?** 😤

```bash
# The daily struggle with complex Laravel apps...
php artisan queue:work --queue=default &
php artisan queue:work --queue=broadcasting --timeout=30 &
php artisan queue:work --queue=mail --timeout=120 &
php artisan queue:work --queue=notifications --sleep=1 &
php artisan queue:work --queue=edi-processing --timeout=300 --memory=256 &
php artisan queue:work --queue=payments --tries=5 &

# ...and trying to manage/monitor them all locally! 😵‍💫
```

**Laravel Nexus was born from this exact frustration!**

Instead of juggling multiple terminal windows and remembering different queue configurations, Laravel Nexus lets you:

✨ **Run ONE command** to manage ALL your queues
🔍 **Auto-discover** all queues in your app
⚙️ **Configure once**, run everywhere
📺 **Monitor everything** in one beautiful interface

```bash
# The Laravel Nexus way ✨
php artisan nexus:work --log
```

**That's it!** All your queues running with optimized settings, beautiful logs, and zero mental overhead.

## ✨ Features

- 🔍 **Auto-Discovery** - Scans Jobs, Events, Mail, Notifications, and Listeners
- ⚙️ **Interactive Setup** - Beautiful Laravel Prompts for easy configuration
- 📺 **Live Log Streaming** - Real-time, color-coded worker output
- 🎛️ **Process Management** - Start, stop, restart, and monitor workers
- 🔥 **Hot Reload** - Auto-restart workers when files change (perfect for development)
- 🔄 **Auto-restart** - Responds to Laravel's `queue:restart` signals
- 📊 **Smart Defaults** - Optimized settings based on queue type
- 🚀 **Production Ready** - Built for reliability and performance
- ✅ **Thoroughly Tested** - Comprehensive test suite with 31 tests covering all functionality

## 🧪 Testing & Quality

Laravel Nexus is thoroughly tested with a comprehensive test suite:

- **31 Tests** covering all package functionality
- **Unit Tests** for core services and queue discovery
- **Feature Tests** for all commands and workflows
- **Architecture Tests** ensuring code quality and standards
- **CI/CD Pipeline** testing against PHP 8.1, 8.2, 8.3 and Laravel 10.x, 11.x, 12.x
- **Code Style** enforcement with Laravel Pint
- **Security Auditing** with automated dependency scanning

```bash
# Run the test suite
composer test

# Check code formatting
composer format-test

# Auto-fix code style
composer format
```

## Installation

Install the package via Composer:

```bash
composer require jamesjulius/laravel-nexus
```

The package will automatically register its service provider.

Optionally, publish the configuration file:

```bash
# Simple way
php artisan nexus:publish

# Or using vendor:publish directly
php artisan vendor:publish --provider="JamesJulius\LaravelNexus\NexusServiceProvider" --tag="nexus-config"
```

## Quick Start

### 1. Interactive Setup (Recommended)

Run the interactive configuration to discover and set up your queues:

```bash
php artisan nexus:configure
```

This will:
- 🔍 Scan your app for Jobs, Events, Mail, and Notifications
- 📋 Show discovered queues with job counts and statistics
- ⚙️ Let you configure worker settings interactively
- 💾 Save configuration to `config/nexus.php`
- 🚀 Optionally start workers immediately

### 2. Start Workers

Start your configured workers:

```bash
# Basic daemon mode
php artisan nexus:work

# Development with live logs
php artisan nexus:work --log

# Development with hot reload
php artisan nexus:work --watch

# Debug mode with job IDs and timestamps
php artisan nexus:work --detailed
```

### 3. Management Commands

```bash
# Publish configuration (optional)
php artisan nexus:publish

# Check worker status
php artisan nexus:work --status

# Stop all workers
php artisan nexus:work --stop

# Restart all workers
php artisan nexus:work --restart

# Get comprehensive help
php artisan nexus:help
```

## Commands Reference

### Configuration Management

```bash
# Publish configuration file
php artisan nexus:publish
php artisan nexus:publish --force  # Overwrite existing

# Interactive setup flow
php artisan nexus:configure

# Discovery only (no configuration)
php artisan nexus:configure --discover

# List all jobs by queue
php artisan nexus:configure --list-jobs

# List jobs for specific queue
php artisan nexus:configure --list-jobs --queue=broadcasting
```

### Worker Management

```bash
# Start all workers
php artisan nexus:work

# Start with live log streaming
php artisan nexus:work --log

# Start with detailed logging (job IDs, dates, colors)
php artisan nexus:work --detailed

# Start with file watching + auto-reload
php artisan nexus:work --watch

# Worker lifecycle management
php artisan nexus:work --status    # Check status
php artisan nexus:work --stop      # Stop all workers
php artisan nexus:work --restart   # Restart workers

# Start specific worker only
php artisan nexus:work --worker=broadcasting
```

### Help & Documentation

```bash
# Comprehensive help guide
php artisan nexus:help

# Command-specific help
php artisan help nexus:configure
php artisan help nexus:work
```

## Configuration

The package automatically generates a `config/nexus.php` file with your queue configurations:

```php
<?php

return [
    'workers' => [
        'default' => [
            'queue' => 'default',
            'connection' => env('QUEUE_CONNECTION', 'database'),
            'tries' => 3,
            'timeout' => 60,
            'sleep' => 3,
            'memory' => 128,
            'processes' => 2,
            'max_jobs' => 1000,
            'max_time' => 3600,
        ],
        'broadcasting' => [
            'queue' => 'broadcasting',
            'connection' => env('QUEUE_CONNECTION', 'database'),
            'tries' => 3,
            'timeout' => 30,
            'sleep' => 1,
            'memory' => 128,
            'processes' => 1,
            'max_jobs' => 500,
            'max_time' => 3600,
        ],
        // ... more workers
    ],

    'environment' => env('APP_ENV', 'production'),
    'prefix' => env('NEXUS_PREFIX', 'nexus'),
    'auto_restart' => true,
    'restart_signal_file' => storage_path('framework/cache/laravel-queue-restart'),
];
```

## Queue Auto-Discovery

Laravel Nexus automatically discovers queues from:

- **Jobs** with `$queue` property or `onQueue()` calls
- **Broadcasting Events** with `broadcastQueue()` method
- **Mail Classes** using the `Queueable` trait
- **Notifications** implementing `ShouldQueue`
- **Event Listeners** that are queued

### Example Queue Detection

```php
// Jobs
class ProcessPayment implements ShouldQueue
{
    public $queue = 'payments'; // ✅ Detected
}

// Events
class OrderShipped implements ShouldBroadcast
{
    public function broadcastQueue()
    {
        return 'broadcasting'; // ✅ Detected
    }
}

// Mail
class WelcomeEmail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('mail'); // ✅ Detected
    }
}

// Notifications
class InvoiceGenerated extends Notification implements ShouldQueue
{
    public $queue = 'notifications'; // ✅ Detected
}
```

## Development Workflow

### Hot Reload

Perfect for development - automatically restarts workers when files change:

```bash
php artisan nexus:work --watch
```

Monitors:
- `app/` directory
- `config/` directory
- `routes/` directory
- `database/migrations/` directory
- `resources/views/` directory

### Live Logging

Stream worker logs in real-time with beautiful color coding:

```bash
# Basic live logs
php artisan nexus:work --log

# Detailed logs with job IDs and timestamps
php artisan nexus:work --detailed
```

**Log Features:**
- ✅ Color-coded job statuses (Processing=Orange, Success=Green, Failed=Red)
- 🎯 Job class highlighting
- 📊 Memory usage and duration highlighting
- 🆔 Job ID extraction in detailed mode
- ⏰ Smart timestamp formatting

## Production Usage

### Basic Setup

```bash
# 1. Configure queues
php artisan nexus:configure

# 2. Start workers (use process manager like Supervisor)
php artisan nexus:work
```

### Supervisor Configuration

```ini
[program:nexus-workers]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan nexus:work
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/nexus.log
numprocs=1
```

### Health Monitoring

```bash
# Check worker status in scripts/cron
php artisan nexus:work --status

# Restart workers during deployment
php artisan nexus:work --restart

# Or use Laravel's built-in restart signal
php artisan queue:restart  # Nexus auto-detects this
```

## Environment Variables

```env
# Queue connection (standard Laravel)
QUEUE_CONNECTION=database

# Nexus-specific settings
NEXUS_PREFIX=nexus
```

## Troubleshooting

### No Queues Detected

```bash
# Re-scan for queues
php artisan nexus:configure --discover

# Check specific queue
php artisan nexus:configure --list-jobs --queue=default
```

### Workers Not Starting

```bash
# Check configuration
cat config/nexus.php

# Check worker status
php artisan nexus:work --status

# Force restart
php artisan nexus:work --restart
```

### Performance Issues

1. **Adjust worker counts** based on your server capacity
2. **Tune memory limits** per queue type in config
3. **Monitor with** `--status` command
4. **Use appropriate timeouts** for different job types

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/jamesjulius/laravel-nexus.git
cd laravel-nexus

# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer format-test

# Fix code style
composer format
```

### Testing Guidelines

- All new features must include tests
- Tests should cover both success and failure scenarios
- Run the full test suite before submitting PRs
- Follow the existing test patterns and structure

### Pull Request Process

1. Ensure all tests pass: `composer test`
2. Ensure code style compliance: `composer format-test`
3. Update documentation if needed
4. Submit PR with clear description of changes

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- **James Julius** - Initial development
- Built with ❤️ for the Laravel community

---

**Laravel Nexus** - Your central hub for queue worker management 🚀