<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RealtimeRepository;
use App\Repository\StopTimeRepository;
use App\Service\Prediction\ArrivalPredictorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

/**
 * Polls active vehicles and generates arrival predictions to populate arrival_log table.
 *
 * Runs every 2-3 minutes via scheduler to build historical arrival data for performance analysis.
 */
#[AsCommand(
    name: 'app:collect:arrival-logs',
    description: 'Generate arrival predictions for active vehicles to populate arrival logs',
)]
final class CollectArrivalLogsCommand extends Command
{
    private const int PREDICTIONS_PER_VEHICLE = 3; // Log predictions for next 3 stops per vehicle

    public function __construct(
        private readonly RealtimeRepository $realtimeRepo,
        private readonly StopTimeRepository $stopTimeRepo,
        private readonly ArrivalPredictorInterface $arrivalPredictor,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Collecting arrival predictions for active vehicles...');

        // Get all active vehicles from Redis
        $snapshot = $this->realtimeRepo->snapshot();
        $vehicles = $snapshot['vehicles'] ?? [];

        if (count($vehicles) === 0) {
            $io->warning('No active vehicles found in realtime feed');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d active vehicles', count($vehicles)));

        $totalPredictions = 0;
        $successfulLogs   = 0;
        $failedLogs       = 0;

        foreach ($vehicles as $vehicle) {
            $vehicleId = $vehicle['id']    ?? null;
            $tripId    = $vehicle['trip']  ?? null;
            $routeId   = $vehicle['route'] ?? null;

            // Skip vehicles without required data
            if ($vehicleId === null || $tripId === null || $routeId === null) {
                $this->logger->debug('Skipping vehicle without required data', [
                    'vehicle_id' => $vehicleId,
                    'trip_id'    => $tripId,
                ]);
                continue;
            }

            // Get upcoming stops for this trip
            $stopTimes = $this->stopTimeRepo->getStopTimesForTrip($tripId);
            if ($stopTimes === null || count($stopTimes) === 0) {
                $this->logger->debug('No stop times found for trip', ['trip_id' => $tripId]);
                continue;
            }

            // Generate predictions for next N stops
            $predictedStops = 0;
            foreach ($stopTimes as $stopTime) {
                if ($predictedStops >= self::PREDICTIONS_PER_VEHICLE) {
                    break;
                }

                $stopId = $stopTime['stop_id'] ?? null;
                if ($stopId === null) {
                    continue;
                }

                try {
                    // Generate prediction (this will automatically log to arrival_log table)
                    $prediction = $this->arrivalPredictor->predictArrival($stopId, $tripId, $vehicleId);

                    if ($prediction !== null) {
                        ++$totalPredictions;
                        ++$successfulLogs;
                        ++$predictedStops;

                        $this->logger->debug('Logged arrival prediction', [
                            'vehicle_id' => $vehicleId,
                            'route_id'   => $routeId,
                            'stop_id'    => $stopId,
                            'confidence' => $prediction->confidence->value,
                        ]);
                    } else {
                        ++$failedLogs;
                    }
                } catch (\Exception $e) {
                    ++$failedLogs;
                    $this->logger->error('Failed to generate prediction', [
                        'vehicle_id' => $vehicleId,
                        'stop_id'    => $stopId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        // Output summary
        if ($totalPredictions > 0) {
            $io->success(sprintf(
                'Collected %d arrival predictions (%d successful, %d failed)',
                $totalPredictions,
                $successfulLogs,
                $failedLogs
            ));
        } else {
            $io->warning('No arrival predictions were generated. Check that trips and stops are loaded.');
        }

        $io->table(
            ['Metric', 'Count'],
            [
                ['Active Vehicles', count($vehicles)],
                ['Total Predictions', $totalPredictions],
                ['Successful Logs', $successfulLogs],
                ['Failed Logs', $failedLogs],
            ]
        );

        return Command::SUCCESS;
    }
}
