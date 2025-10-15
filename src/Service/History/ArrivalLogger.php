<?php

declare(strict_types=1);

namespace App\Service\History;

use App\Dto\ArrivalPredictionDto;
use App\Dto\VehicleDto;
use App\Entity\ArrivalLog;
use App\Repository\ArrivalLogRepository;
use App\Repository\StopRepository;
use App\Repository\TripRepository;
use Psr\Log\LoggerInterface;

use function sprintf;

/**
 * Logs arrival predictions to database for historical analysis.
 *
 * Keeps logic out of controllers/predictors.
 */
final readonly class ArrivalLogger
{
    public function __construct(
        private ArrivalLogRepository $arrivalLogRepo,
        private StopRepository $stopRepo,
        private TripRepository $tripRepo,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Log an arrival prediction to the database.
     *
     * Returns false if logging failed (e.g., invalid foreign keys).
     */
    public function logPrediction(ArrivalPredictionDto $prediction, VehicleDto $vehicle): bool
    {
        // Find entities for foreign keys
        $stop = $this->stopRepo->findOneBy(['gtfsId' => $prediction->stopId]);
        if ($stop === null) {
            $this->logger->warning('Cannot log arrival: stop not found', ['stop_id' => $prediction->stopId]);

            return false;
        }

        $trip = $this->tripRepo->findOneByGtfsId($prediction->tripId);
        if ($trip === null) {
            $this->logger->warning('Cannot log arrival: trip not found', ['trip_id' => $prediction->tripId]);

            return false;
        }

        $route = $trip->getRoute();
        if ($route === null) {
            $this->logger->warning('Cannot log arrival: route not found for trip', ['trip_id' => $prediction->tripId]);

            return false;
        }

        // Calculate scheduled arrival time (if delay available)
        $scheduledArrivalAt = null;
        if ($prediction->delaySec !== null) {
            $scheduledTimestamp = $prediction->arrivalAt - $prediction->delaySec;
            $scheduledArrivalAt = (new \DateTimeImmutable())->setTimestamp($scheduledTimestamp);
        }

        // Create and persist log entry
        $log = new ArrivalLog();
        $log->setVehicleId($prediction->vehicleId);
        $log->setRoute($route);
        $log->setTripId($prediction->tripId);
        $log->setStop($stop);
        $log->setPredictedArrivalAt((new \DateTimeImmutable())->setTimestamp($prediction->arrivalAt));
        $log->setScheduledArrivalAt($scheduledArrivalAt);
        $log->setDelaySec($prediction->delaySec);
        $log->setConfidence($prediction->confidence);
        $log->setStopsAway($prediction->currentLocation['stops_away'] ?? null);
        $log->setPredictedAt(new \DateTimeImmutable());

        try {
            $this->arrivalLogRepo->save($log, flush: true);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to save arrival log', [
                'vehicle_id' => $prediction->vehicleId,
                'trip_id'    => $prediction->tripId,
                'stop_id'    => $prediction->stopId,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Log multiple predictions in a batch (more efficient).
     *
     * @param list<array{prediction: ArrivalPredictionDto, vehicle: VehicleDto}> $items
     *
     * @return array{success: int, failed: int}
     */
    public function logBatch(array $items): array
    {
        $success = 0;
        $failed  = 0;

        foreach ($items as $item) {
            if ($this->logPrediction($item['prediction'], $item['vehicle'])) {
                ++$success;
            } else {
                ++$failed;
            }
        }

        $this->logger->info(sprintf('Batch logged %d arrivals (%d failed)', $success, $failed));

        return ['success' => $success, 'failed' => $failed];
    }
}
