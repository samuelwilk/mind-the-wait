# mind-the-wait Documentation

Welcome to the mind-the-wait documentation! This transit headway monitoring system tracks real-time vehicle performance and provides delightful, informative status updates.

## Documentation Structure

### Getting Started
- **[🚀 Getting Started Guide](GETTING_STARTED.md)** - Complete introduction for new users
- [Quick Start Guide](development/quick-start.md) - Get up and running in 5 minutes
- [Architecture Overview](architecture/overview.md) - System design and data flow
- [Development Guide](development/setup.md) - Detailed development environment setup
- [📝 Changelog](../CHANGELOG.md) - Recent changes and version history

### API Documentation
- [REST API Reference](api/endpoints.md) - Complete API endpoint documentation
- [Arrival Predictions](api/arrival-predictions.md) - ⭐ Realtime countdown timers with confidence levels
- [Data Models](api/models.md) - Request/response schemas and DTOs
- [Vehicle Status System](api/vehicle-status.md) - Status colors, severity labels, and feedback

### Architecture Deep Dives
- [Headway Calculation](architecture/headway-calculation.md) - How we compute observed headways
- [Position Interpolation](architecture/position-interpolation.md) - GPS-based arrival estimation
- [GTFS Integration](architecture/gtfs-integration.md) - Static and realtime feed processing
- [Redis Data Model](architecture/redis-schema.md) - Cache structure and keys

### Development
- [Testing Guide](development/testing.md) - Writing and running tests
- [Code Style](development/code-style.md) - Conventions and linting
- [Deployment](development/deployment.md) - Production deployment guide

## Key Features

⏱️ **Arrival Predictions** - Countdown timers with HIGH/MEDIUM/LOW confidence levels

🎨 **6-Color Status Spectrum** - From 🟢 warp speed early to 🟣 ghost bus late

😄 **Easter Egg Dad Jokes** - 10% chance for delightful transit humor

📊 **Realtime Headway Scoring** - Position-based calculation with intelligent fallbacks

👥 **Crowd Feedback** - Riders vote on punctuality (ahead/on_time/late)

## Quick Links

- [GitHub Repository](https://github.com/samuelwilk/mind-the-wait)
- [Issue Tracker](https://github.com/samuelwilk/mind-the-wait/issues)
- [Main README](../README.md)
- [CLAUDE.md](../CLAUDE.md) - AI assistant context

## Support

For questions or issues:
1. Check the [Troubleshooting Guide](development/troubleshooting.md)
2. Search [existing issues](https://github.com/samuelwilk/mind-the-wait/issues)
3. Open a new issue with detailed context
