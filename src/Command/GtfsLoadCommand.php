<?php

namespace App\Command;

use App\Entity\Route;
use App\Entity\Stop;
use App\Entity\StopTime;
use App\Entity\Trip;
use App\Enum\DirectionEnum;
use App\Enum\RouteTypeEnum;
use App\Repository\RouteRepository;
use App\Repository\StopRepository;
use App\Repository\StopTimeRepository;
use App\Repository\TripRepository;
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
    public function __construct(
        private readonly RouteRepository $routes,
        private readonly StopRepository $stops,
        private readonly TripRepository $trips,
        private readonly StopTimeRepository $stopTimes,
        private readonly HttpClientInterface $http,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'Local path or URL of GTFS zip');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1) Resolve source (CLI > env)
        $source   = $input->getOption('source')       ?? ($_ENV['MTW_GTFS_STATIC_URL'] ?? null);
        $fallback = $_ENV['MTW_GTFS_STATIC_FALLBACK'] ?? null;
        if (!$source) {
            $io->error('No source. Set MTW_GTFS_STATIC_URL or pass --source=/path/to/gtfs.zip');

            return Command::FAILURE;
        }

        $zipPath = sys_get_temp_dir().'/gtfs_'.uniqid('', true).'.zip';
        $dir     = sys_get_temp_dir().'/gtfs_'.uniqid('', true);
        (new Filesystem())->mkdir($dir);

        try {
            // 2) Download/copy with retries
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

            // 3) Extract zip (with checks)
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

            // 4) Reset + load
            $this->truncateAll($io);                 // or deleteAllWithDql() if you switched to ORM-only
            $this->loadRoutes("$dir/routes.txt", $io);
            $this->loadStops("$dir/stops.txt", $io);
            $this->loadTrips("$dir/trips.txt", $io);
            $this->loadStopTimes("$dir/stop_times.txt", $io);

            $io->success('GTFS static loaded');

            return Command::SUCCESS;
        } finally {
            // 5) Cleanup temp artifacts
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

        // child → parent to satisfy FKs
        foreach ([$tStopTime, $tTrip, $tStop, $tRoute] as $t) {
            $conn->executeStatement(sprintf('TRUNCATE %s RESTART IDENTITY CASCADE', $t));
        }
    }

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

        $n = 0;
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
            if (++$n % 1000 === 0) {
                $this->routes->flush();
            }
        }
        $this->routes->flush();
        $io->writeln("  upserted: $n");
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

        $n = 0;
        foreach ($csv->getRecords() as $r) {
            $this->stops->upsert(
                $r['stop_id'],
                $r['stop_name'],
                (float) $r['stop_lat'],
                (float) $r['stop_lon']
            );
            if (++$n % 2000 === 0) {
                $this->stops->flush();
            }
        }
        $this->stops->flush();
        $io->writeln("  upserted: $n");
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

        $n = 0;
        foreach ($csv->getRecords() as $r) {
            $route = $routesByGtfs[$r['route_id']] ?? null;
            if (!$route) {
                continue;
            } // skip trips for routes we didn't load

            $this->trips->upsert(
                $r['trip_id'],
                $route,
                $r['service_id'] ?? null,
                DirectionEnum::from((int) ($r['direction_id'] ?? 0)),
                $r['trip_headsign'] ?? null
            );
            if (++$n % 2000 === 0) {
                $this->trips->flush();
            }
        }
        $this->trips->flush();
        $io->writeln("  upserted: $n");
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

        $batch = [];
        $n     = 0;
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
                'arr'  => $this->toSec($r['arrival_time'] ?? null),
                'dep'  => $this->toSec($r['departure_time'] ?? null),
            ];
            if (++$n % 5000 === 0) {
                $this->stopTimes->bulkInsert($batch);
                $batch = [];
                $io->writeln("  inserted: $n");
            }
        }
        if ($batch) {
            $this->stopTimes->bulkInsert($batch);
        }
        $io->writeln("  total inserted: $n");
    }

    private function toSec(?string $hhmmss): ?int
    {
        if (!$hhmmss) {
            return null;
        }
        $p = array_map('intval', explode(':', $hhmmss));
        if (count($p) !== 3) {
            return null;
        }

        return $p[0] * 3600 + $p[1] * 60 + $p[2];
    }
}
