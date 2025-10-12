# 🚌 mind-the-wait

**Real-time transit headway monitoring with delightful status updates**

A comprehensive transit reliability system that monitors bus performance, predicts arrivals, correlates weather impact, and provides intuitive visualizations for riders and transit agencies.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Symfony 7.3](https://img.shields.io/badge/Symfony-7.3-000000.svg?style=flat&logo=symfony)](https://symfony.com)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4.svg?style=flat&logo=php)](https://www.php.net)

## ✨ Features

- 📊 **Real-time Headway Calculation** - Position-based service frequency monitoring
- ⏱️ **Arrival Predictions** - Countdown timers with confidence levels (HIGH/MEDIUM/LOW)
- 🎨 **6-Color Status System** - Visual spectrum from 🟢 early to 🟣 very late
- 🌤️ **Weather Integration** - Automatic correlation with transit performance
- 📈 **Historical Analysis** - Daily performance aggregation and trend tracking
- 👥 **Crowd Feedback** - Rider voting on vehicle punctuality
- 😄 **Easter Eggs** - Dad jokes for delightful user experience (10% chance)

## 🚀 Quick Start

### Prerequisites

- Docker Desktop (or Docker Engine + Compose v2.10+)
- GNU Make
- 4GB RAM minimum

### One-Command Setup

```bash
git clone https://github.com/samuelwilk/mind-the-wait.git
cd mind-the-wait
make setup
```

**That's it!** In 3-7 minutes, you'll have:
- ✅ All containers running (PostgreSQL, Redis, PHP, Python parser)
- ✅ Database with migrations applied
- ✅ GTFS static data loaded
- ✅ Initial weather data collected
- ✅ Automated scheduling (scores every 30s, weather hourly)

Access the dashboard at **https://localhost**

### What Gets Set Up

| Component | What It Does | Port |
|-----------|--------------|------|
| **Dashboard** | Overview with realtime metrics and weather | https://localhost |
| **PostgreSQL** | GTFS data, weather, performance history | 5432 |
| **Redis** | Realtime vehicle positions and scores | 6379 |
| **Python Parser** | Polls GTFS-RT feeds every 12 seconds | - |
| **Scheduler** | Automated tasks (score/weather/aggregation) | - |

## 📖 Documentation

- **[🚀 Getting Started Guide](docs/GETTING_STARTED.md)** - Complete introduction
- **[⚡ Quick Start](docs/development/quick-start.md)** - 5-minute setup
- **[📝 Changelog](CHANGELOG.md)** - Recent changes and releases
- **[🏗️ Architecture](docs/architecture/overview.md)** - System design
- **[🔌 API Reference](docs/api/endpoints.md)** - REST API documentation
- **[🧪 Testing Guide](docs/development/testing.md)** - Running tests

## 🏛️ Architecture

```
GTFS Static + Realtime → PostgreSQL + Redis → Symfony API → Dashboard/APIs
                            ↓                      ↓
                    Python Parser          Symfony Scheduler
                    (GTFS-RT polling)    (Score/Weather/Aggregation)
```

### Core Components

- **Symfony 7.3** - Web framework and API layer
- **Doctrine ORM** - Database persistence
- **PostgreSQL 16** - GTFS static data, weather, performance history
- **Redis 7** - Realtime vehicle positions and scores
- **Python GTFS-RT Parser** - Polls protobuf feeds continuously
- **Symfony Scheduler** - Automated tasks via Messenger

## 🛠️ Common Tasks

### View Real-time Data

```bash
# Check vehicle positions
curl -sk https://localhost/api/realtime | jq '.vehicles | length'

# Check headway scores
curl -sk https://localhost/api/score | jq '.scores[] | {route, grade, vehicles}'

# View logs
docker compose logs -f scheduler
docker compose logs -f pyparser
```

### Database Operations

```bash
make database                     # Reset dev database
make database-test                # Reset test database
make database-migrations-generate # Generate new migration
```

### Code Quality

```bash
make test-phpunit  # Run PHPUnit tests
make cs-fix        # Fix code style
make cs-dry-run    # Check code style
```

### Manual Operations

```bash
make score-tick      # Calculate scores now
make weather-collect # Collect weather now
make gtfs-load       # Reload GTFS data
```

## 🔧 Customization

### Change Transit Agency

1. Update `.env.local` with your GTFS feed:
   ```env
   MTW_GTFS_STATIC_URL=https://your-agency.com/gtfs.zip
   ```

2. Update `compose.override.yaml` with GTFS-RT URLs:
   ```yaml
   services:
     pyparser:
       environment:
         VEH_URL: "https://your-agency.com/VehiclePositions.pb"
         TRIP_URL: "https://your-agency.com/TripUpdates.pb"
         ALERT_URL: "https://your-agency.com/Alerts.pb"
   ```

3. Reload data:
   ```bash
   docker compose restart pyparser
   make gtfs-load
   ```

## 🐛 Troubleshooting

**No vehicles showing?**
- Check: `docker compose logs pyparser`
- Verify GTFS-RT URLs are correct
- Ensure feed is publicly accessible

**Scores always N/A?**
- Need at least 2 vehicles per route for headway calculation
- Single vehicles graded by schedule adherence (requires matching trip IDs)

**Weather not updating?**
- Check: `docker compose logs scheduler | grep weather`
- Verify: `docker compose exec php bin/console debug:scheduler`

See [docs/GETTING_STARTED.md](docs/GETTING_STARTED.md#troubleshooting) for detailed troubleshooting.

## 📊 API Endpoints

- `GET /api/realtime` - Current vehicle positions with status enrichment
- `GET /api/score` - Headway scores by route/direction
- `POST /api/vehicle-feedback` - Submit rider feedback
- `GET /api/vehicle-feedback/{vehicleId}` - Get aggregated votes

See [docs/api/endpoints.md](docs/api/endpoints.md) for complete API documentation.

## 🤝 Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

MIT License - see [LICENSE](LICENSE) for details.

## 🙏 Acknowledgments

- Built with [Symfony](https://symfony.com)
- Uses [GTFS](https://gtfs.org) and [GTFS-Realtime](https://gtfs.org/realtime/)
- Weather data from [Open-Meteo](https://open-meteo.com)
- Configured for [Saskatoon Transit](https://transit.saskatoon.ca)

## 📮 Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/samuelwilk/mind-the-wait/issues)
- **Discussions**: [GitHub Discussions](https://github.com/samuelwilk/mind-the-wait/discussions)
