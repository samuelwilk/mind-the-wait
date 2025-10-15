<?php

namespace App\Command;

use App\Config\GtfsConfig;
use App\Entity\Route;
use App\Entity\Stop;
use App\Entity\StopTime;
use App\Entity\Trip;
use App\Enum\DirectionEnum;
use App\Enum\GtfsSourceEnum;
use App\Enum\RouteTypeEnum;
use App\Factory\GtfsConfigFactory;
use App\Repository\RouteRepository;
use App\Repository\StopRepository;
use App\Repository\StopTimeRepository;
use App\Repository\TripRepository;
use App\Service\GtfsFeatureAdapter;
use App\Util\GtfsTimeUtils;
use Doctrine\DBAL\Exception;
use League\Csv\Reader;
use League\Csv\UnavailableStream;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ZipArchive;

use function count;
use function sprintf;

#[AsCommand(name: 'app:gtfs:load', description: 'Load static GTFS into DB (route, trip, stop, stop_time)')]
final class GtfsLoadCommand extends Command
{
    private const int ARCGIS_PAGE_SIZE = 1000;
    private const int ARCGIS_MAX_PAGES = 2000;

    public function __construct(
        private readonly RouteRepository $routes,
        private readonly StopRepository $stops,
        private readonly TripRepository $trips,
        private readonly StopTimeRepository $stopTimes,
        private readonly HttpClientInterface $http,
        private readonly GtfsConfigFactory $configFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'Local path or URL of GTFS zip')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'zip|arcgis (auto if env set)', null)
            ->addOption('routes-url', null, InputOption::VALUE_REQUIRED)
            ->addOption('stops-url', null, InputOption::VALUE_REQUIRED)
            ->addOption('trips-url', null, InputOption::VALUE_REQUIRED)
            ->addOption('stop-times-url', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $config = $this->configFactory->fromInput($input);

        return match ($config->source) {
            GtfsSourceEnum::Arcgis => $this->loadFromArcGis($io, $config),
            GtfsSourceEnum::Zip    => $this->loadFromZip($io, $config, $input),
        };
    }

    // ---------------- ZIP MODE (unchanged from your working version) ----------------

    private function loadFromZip(SymfonyStyle $io, GtfsConfig $config, InputInterface $input): int
    {
        $source   = $config->zipUrl;
        $fallback = $config->zipFallback;

        if (!$source) {
            $io->error('No source. Set MTW_GTFS_STATIC_URL or pass --source=/path/to/gtfs.zip');

            return Command::FAILURE;
        }

        $zipPath = sys_get_temp_dir().'/gtfs_'.uniqid('', true).'.zip';
        $dir     = sys_get_temp_dir().'/gtfs_'.uniqid('', true);
        (new Filesystem())->mkdir($dir);

        try {
            $download = function (string $url) use ($io, $zipPath): bool {
                $io->writeln("Downloading $url …");
                $attempts = 0;
                $max      = 6;
                $delay    = 1.5;
                while (true) {
                    try {
                        $res = $this->http->request('GET', $url, [
                            'headers'       => ['User-Agent' => 'MindTheWait/1.0 (+https://zu.com)', 'Connection' => 'close'],
                            'timeout'       => 30,
                            'max_redirects' => 3,
                        ]);
                        $status = $res->getStatusCode();
                        if ($status >= 200 && $status < 300) {
                            file_put_contents($zipPath, $res->getContent());

                            return true;
                        }
                        throw new \RuntimeException("HTTP $status");
                    } catch (\Throwable $e) {
                        if (++$attempts >= $max) {
                            $io->writeln("  failed after $attempts attempts: ".$e->getMessage());

                            return false;
                        }
                        $io->writeln(sprintf('  download failed (%s), retrying in %.1fs…', $e->getMessage(), $delay));
                        usleep((int) ($delay * 1_000_000));
                        $delay *= 1.8;
                    }
                }
            };

            if (is_file($source)) {
                $io->writeln("Using local file: $source");
                if (!@copy($source, $zipPath)) {
                    $io->error("Failed to copy local file: $source");

                    return Command::FAILURE;
                }
            } else {
                if (!$download($source)) {
                    if ($fallback && !$input->getOption('source')) {
                        $io->writeln('Trying fallback source…');
                        if (!$download($fallback)) {
                            $io->error('Download failed (primary and fallback).');

                            return Command::FAILURE;
                        }
                    } else {
                        $io->error('Download failed.');

                        return Command::FAILURE;
                    }
                }
            }

            $zip = new ZipArchive();
            $rc  = $zip->open($zipPath);
            if ($rc !== true) {
                $io->error("Failed to open GTFS zip (code: $rc)");

                return Command::FAILURE;
            }
            if (!$zip->extractTo($dir)) {
                $io->error('Failed to extract GTFS zip');
                $zip->close();

                return Command::FAILURE;
            }
            $zip->close();

            foreach (['routes.txt', 'stops.txt', 'trips.txt', 'stop_times.txt'] as $req) {
                if (!is_file("$dir/$req")) {
                    $io->error("Missing required file in zip: $req");

                    return Command::FAILURE;
                }
            }

            $this->truncateAll($io);
            $this->loadRoutes("$dir/routes.txt", $io);
            $this->loadStops("$dir/stops.txt", $io);
            $this->loadTrips("$dir/trips.txt", $io);
            $this->loadStopTimes("$dir/stop_times.txt", $io);

            $io->success('GTFS static loaded');

            return Command::SUCCESS;
        } finally {
            @is_file($zipPath) && @unlink($zipPath);
            if (is_dir($dir)) {
                $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
                $ri = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($ri as $f) {
                    $f->isDir() ? @rmdir($f) : @unlink($f);
                }
                @rmdir($dir);
            }
        }
    }

    // ---------------- ARCGIS MODE ----------------

    private function loadFromArcGis(SymfonyStyle $io, GtfsConfig $config): int
    {
        $io->title('Loading GTFS from ArcGIS FeatureServer');

        $this->truncateAll($io);

        // Routes
        $io->section('routes (ArcGIS)');
        $routesIterated = 0;
        foreach ($this->fetchArcGisFeatures($config->routesUrl) as $feat) {
            $adapter = new GtfsFeatureAdapter($feat);
            $type    = $adapter->routeType() !== null ? RouteTypeEnum::tryFrom($adapter->routeType()) : null;

            $this->routes->upsert(
                $adapter->routeId(),
                $adapter->routeShortName(),
                $adapter->routeLongName(),
                $adapter->routeColor(),
                $type
            );

            $this->flushEvery(++$routesIterated, 1000, fn () => $this->routes->flush());
        }
        $this->routes->flush();
        $io->writeln("  upserted: $routesIterated");

        // Stops
        $io->section('stops (ArcGIS)');
        $stopsIterated = 0;
        foreach ($this->fetchArcGisFeatures($config->stopsUrl) as $feat) {
            $adapter     = new GtfsFeatureAdapter($feat);
            [$lat, $lon] = array_map('floatval', $adapter->latLon());

            $this->stops->upsert(
                $adapter->stopId(),
                $adapter->stopName(),
                $lat,
                $lon
            );

            $this->flushEvery(++$stopsIterated, 1000, fn () => $this->stops->flush());
        }
        $this->stops->flush();
        $io->writeln("  upserted: $stopsIterated");

        // Trips
        $io->section('trips (ArcGIS)');
        $routesByGtfs  = $this->routes->mapByGtfsId();
        $tripsIterated = 0;
        $skippedTrips  = 0;
        foreach ($this->fetchArcGisFeatures($config->tripsUrl) as $feat) {
            $adapter = new GtfsFeatureAdapter($feat);
            $route   = $routesByGtfs[$adapter->tripRouteId()] ?? null;
            if (!$route) {
                ++$skippedTrips;
                $io->writeln("  ⚠️  Skipped trip {$adapter->tripId()} — route {$adapter->tripRouteId()} not found");
                continue;
            }

            $this->trips->upsert(
                $adapter->tripId(),
                $route,
                $adapter->serviceId(),
                DirectionEnum::from($adapter->directionId()),
                $adapter->tripHeadsign()
            );

            $this->flushEvery(++$tripsIterated, 2000, fn () => $this->trips->flush());
        }
        $this->trips->flush();
        $io->writeln("  upserted: $tripsIterated");
        if ($skippedTrips > 0) {
            $io->writeln("  skipped (missing route): $skippedTrips");
        }

        // Stop Times
        $io->section('stop_times (ArcGIS)');
        $tripIdMap = $this->trips->idMapByGtfsId();
        $stopIdMap = $this->stops->idMapByGtfsId();

        $batch             = [];
        $stopTimesIterated = 0;
        $BATCH_SIZE        = 1000;
        $skippedStopTimes  = 0;

        foreach ($this->fetchArcGisFeatures($config->stopTimesUrl) as $feat) {
            $adapter = new GtfsFeatureAdapter($feat);
            $trip    = $tripIdMap[$adapter->tripId()] ?? null;
            $stop    = $stopIdMap[$adapter->stopId()] ?? null;
            if (!$trip || !$stop) {
                $io->writeln(sprintf(
                    '  skipped: trip_id=%s (%s), stop_id=%s (%s)',
                    $adapter->tripId(),
                    $trip ? 'found' : 'missing',
                    $adapter->stopId(),
                    $stop ? 'found' : 'missing',
                ));
                ++$skippedStopTimes;
                continue;
            }

            $batch[] = [
                'trip' => $trip,
                'stop' => $stop,
                'seq'  => $adapter->stopSequence(),
                'arr'  => GtfsTimeUtils::timeToSeconds($adapter->arrivalTime()),
                'dep'  => GtfsTimeUtils::timeToSeconds($adapter->departureTime()),
            ];

            if (++$stopTimesIterated % $BATCH_SIZE === 0) {
                $this->stopTimes->bulkInsert($batch);
                $batch = [];
                $io->writeln("  inserted: $stopTimesIterated");
            }
        }
        if ($batch) {
            $this->stopTimes->bulkInsert($batch);
        }
        $io->writeln("  total inserted: $stopTimesIterated");
        if ($skippedStopTimes > 0) {
            $io->writeln("  skipped (missing trip or stop): $skippedStopTimes");
        }

        $io->success('GTFS static loaded (ArcGIS)');

        return Command::SUCCESS;
    }

    /**
     * Stream/paginate ArcGIS features with resultOffset.
     *
     * @return \Generator<array<string,mixed>>
     */
    private function fetchArcGisFeatures(string $baseUrl): \Generator
    {
        $offset    = 0;
        $pageSize  = self::ARCGIS_PAGE_SIZE;
        $maxPages  = self::ARCGIS_MAX_PAGES;
        $pageCount = 0;

        while (++$pageCount <= $maxPages) {
            $query = http_build_query([
                'where'             => '1=1',
                'outFields'         => '*',
                'outSR'             => '4326',
                'f'                 => 'json',
                'resultRecordCount' => $pageSize,
                'resultOffset'      => $offset,
            ]);

            $url = $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').$query;

            $res = $this->http->request('GET', $url, [
                'headers' => ['User-Agent' => 'MindTheWait/1.0 (+https://zu.com)'],
                'timeout' => 60,
            ]);

            if ($res->getStatusCode() !== 200) {
                break;
            }

            $json     = $res->toArray(false);
            $features = $json['features'] ?? [];

            if (empty($features)) {
                break;
            }

            foreach ($features as $f) {
                yield $f;
            }

            if (count($features) < $pageSize) {
                break; // last page
            }

            $offset += $pageSize;
        }
    }

    /**
     * @throws Exception
     */
    private function truncateAll(SymfonyStyle $io): void
    {
        $io->writeln('Truncating tables…');

        $em   = $this->stopTimes->getEntityManager();
        $conn = $em->getConnection();

        $tStopTime = $em->getClassMetadata(StopTime::class)->getTableName();
        $tTrip     = $em->getClassMetadata(Trip::class)->getTableName();
        $tStop     = $em->getClassMetadata(Stop::class)->getTableName();
        $tRoute    = $em->getClassMetadata(Route::class)->getTableName();

        foreach ([$tStopTime, $tTrip, $tStop, $tRoute] as $t) {
            $conn->executeStatement(sprintf('TRUNCATE %s RESTART IDENTITY CASCADE', $t));
        }
    }

    // ---------------- CSV (ZIP) LOADERS (unchanged) ----------------

    /**
     * @throws UnavailableStream
     * @throws \League\Csv\Exception
     */
    private function loadRoutes(string $path, SymfonyStyle $io): void
    {
        $io->section('routes');
        if (!is_file($path)) {
            $io->warning('routes.txt missing');

            return;
        }
        $csv = Reader::createFromPath($path);
        $csv->setHeaderOffset(0);

        $routesInserted = 0;
        foreach ($csv->getRecords() as $r) {
            $type = isset($r['route_type']) && $r['route_type'] !== ''
                ? (RouteTypeEnum::tryFrom((int) $r['route_type']) ?? null)
                : null;
            $this->routes->upsert(
                $r['route_id'],
                $r['route_short_name'] ?? null,
                $r['route_long_name']  ?? null,
                $r['route_color']      ?? null,
                $type
            );
            $this->flushEvery(++$routesInserted, 1000, fn () => $this->routes->flush());
        }
        $this->routes->flush();
        $io->writeln("  upserted: $routesInserted");
    }

    /**
     * @throws UnavailableStream
     * @throws \League\Csv\Exception
     */
    private function loadStops(string $path, SymfonyStyle $io): void
    {
        $io->section('stops');
        if (!is_file($path)) {
            $io->warning('stops.txt missing');

            return;
        }
        $csv = Reader::createFromPath($path);
        $csv->setHeaderOffset(0);

        $stopsInserted = 0;
        foreach ($csv->getRecords() as $r) {
            $this->stops->upsert(
                $r['stop_id'],
                $r['stop_name'],
                (float) $r['stop_lat'],
                (float) $r['stop_lon']
            );
            $this->flushEvery(++$stopsInserted, 2000, fn () => $this->stops->flush());
        }
        $this->stops->flush();
        $io->writeln("  upserted: $stopsInserted");
    }

    /**
     * @throws UnavailableStream
     * @throws \League\Csv\Exception
     */
    private function loadTrips(string $path, SymfonyStyle $io): void
    {
        $io->section('trips');
        if (!is_file($path)) {
            $io->warning('trips.txt missing');

            return;
        }
        $csv = Reader::createFromPath($path);
        $csv->setHeaderOffset(0);

        $routesByGtfs = $this->routes->mapByGtfsId();

        $tripsInserted = 0;
        foreach ($csv->getRecords() as $r) {
            $route = $routesByGtfs[$r['route_id']] ?? null;
            if (!$route) {
                continue;
            }

            $this->trips->upsert(
                $r['trip_id'],
                $route,
                $r['service_id'] ?? null,
                DirectionEnum::from((int) ($r['direction_id'] ?? 0)),
                $r['trip_headsign'] ?? null
            );
            $this->flushEvery(++$tripsInserted, 1000, fn () => $this->trips->flush());
        }
        $this->trips->flush();
        $io->writeln("  upserted: $tripsInserted");
    }

    /**
     * @throws UnavailableStream
     * @throws \League\Csv\Exception
     * @throws Exception
     */
    private function loadStopTimes(string $path, SymfonyStyle $io): void
    {
        $io->section('stop_times');
        if (!is_file($path)) {
            $io->warning('stop_times.txt missing');

            return;
        }

        $tripIdMap = $this->trips->idMapByGtfsId();
        $stopIdMap = $this->stops->idMapByGtfsId();

        $csv = Reader::createFromPath($path);
        $csv->setHeaderOffset(0);

        $batch             = [];
        $stopTimesInserted = 0;
        $BATCH_SIZE        = 1000;

        foreach ($csv->getRecords() as $r) {
            $trip = $tripIdMap[$r['trip_id']] ?? null;
            $stop = $stopIdMap[$r['stop_id']] ?? null;
            if (!$trip || !$stop) {
                continue;
            }

            $batch[] = [
                'trip' => $trip,
                'stop' => $stop,
                'seq'  => (int) $r['stop_sequence'],
                'arr'  => GtfsTimeUtils::timeToSeconds($r['arrival_time'] ?? null),
                'dep'  => GtfsTimeUtils::timeToSeconds($r['departure_time'] ?? null),
            ];
            if (++$stopTimesInserted % $BATCH_SIZE === 0) {
                $this->stopTimes->bulkInsert($batch);
                $batch = [];
                $io->writeln("  inserted: $stopTimesInserted");
            }
        }
        if ($batch) {
            $this->stopTimes->bulkInsert($batch);
        }
        $io->writeln("  total inserted: $stopTimesInserted");
    }

    private function flushEvery(int $count, int $threshold, callable $flusher): void
    {
        if ($count % $threshold === 0) {
            $flusher();
        }
    }
}
