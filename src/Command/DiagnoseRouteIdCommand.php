<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RealtimeRepository;
use App\Repository\RouteRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

#[AsCommand(
    name: 'app:diagnose:route-ids',
    description: 'Compare realtime route IDs with database route IDs'
)]
final class DiagnoseRouteIdCommand extends Command
{
    public function __construct(
        private readonly RealtimeRepository $realtime,
        private readonly RouteRepository $routes,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Route ID Mismatch Diagnostic');

        // Get realtime route IDs
        $vehicles         = $this->realtime->getVehicles();
        $realtimeRouteIds = array_unique(array_map(fn ($v) => $v->routeId, $vehicles));
        sort($realtimeRouteIds);

        // Get database routes
        $dbRoutes   = $this->routes->findAll();
        $dbRouteMap = [];
        foreach ($dbRoutes as $route) {
            $dbRouteMap[$route->getGtfsId()] = $route->getShortName();
        }

        $io->section('Realtime Route IDs (from GTFS-RT feed)');
        $io->table(
            ['Realtime Route ID', 'In Database?', 'DB Short Name'],
            array_map(function ($rtId) use ($dbRouteMap) {
                $exists = isset($dbRouteMap[$rtId]);

                return [
                    $rtId,
                    $exists ? '✓' : '✗',
                    $exists ? $dbRouteMap[$rtId] : 'N/A',
                ];
            }, $realtimeRouteIds)
        );

        $io->section('Database Routes');
        $io->table(
            ['GTFS ID', 'Short Name', 'Long Name'],
            array_map(fn ($r) => [$r->getGtfsId(), $r->getShortName(), $r->getLongName()], $dbRoutes)
        );

        // Check for mismatches
        $mismatches = array_filter($realtimeRouteIds, fn ($id) => !isset($dbRouteMap[$id]));

        if (count($mismatches) > 0) {
            $io->error(sprintf(
                '%d/%d realtime route IDs do NOT exist in database',
                count($mismatches),
                count($realtimeRouteIds)
            ));

            $io->section('Recommendation');
            $io->text([
                'The realtime feed and static GTFS database are using different ID schemes.',
                'This causes:',
                '  - No HIGH confidence predictions (can\'t use GPS interpolation)',
                '  - Route IDs in API don\'t match user expectations',
                '  - No static schedule fallback',
                '',
                'Solutions:',
                '  1. Reload static GTFS from same source as realtime feed',
                '  2. Build a route ID mapper based on short_name matching',
                '  3. Contact transit agency for ID mapping table',
            ]);
        } else {
            $io->success('All realtime route IDs exist in database!');
        }

        return Command::SUCCESS;
    }
}
