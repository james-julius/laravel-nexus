# Changelog

All notable changes to `laravel-nexus` will be documented in this file.

## [1.0.0] - 2024-01-XX

### Added
- Initial release of Laravel Nexus
- Queue auto-discovery for Jobs, Events, Mail, Notifications, and Listeners
- Interactive queue configuration with Laravel Prompts
- Multi-worker process management
- Real-time log streaming with color coding
- File watching with auto-reload for development
- Worker lifecycle management (start, stop, restart, status)
- Smart defaults based on queue types
- Comprehensive help system
- Production-ready process management
- Auto-restart on Laravel's queue:restart signals
- Detailed logging with job IDs and timestamps
- Beautiful CLI interface with emojis and colors

### Commands
- `nexus:configure` - Interactive queue configuration and discovery
- `nexus:work` - Worker management and monitoring
- `nexus:help` - Comprehensive help and usage guide

### Features
- Auto-discovery of queue names from Laravel classes
- Support for multiple queue connections
- Configurable worker processes per queue
- Memory and timeout management
- File change detection for development workflow
- Process monitoring and automatic restart
- Color-coded job status indicators
- Job ID extraction and display
- Queue statistics (pending/failed job counts)
- Supervisor-friendly process management

### Configuration
- Publishable configuration file
- Environment variable support
- Smart queue-specific defaults
- Customizable worker settings per queue

### Documentation
- Comprehensive README with examples
- Quick start guide
- Production deployment guide
- Troubleshooting section
- API reference documentation