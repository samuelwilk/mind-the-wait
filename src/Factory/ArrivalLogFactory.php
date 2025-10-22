<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\ArrivalLog;
use App\Enum\PredictionConfidence;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ArrivalLog>
 */
final class ArrivalLogFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ArrivalLog::class;
    }

    protected function defaults(): array
    {
        $predictedAt = self::faker()->dateTimeBetween('-1 hour', 'now');
        $arrivalAt   = (clone $predictedAt)->modify('+10 minutes');

        return [
            'vehicleId'          => self::faker()->regexify('veh-[0-9]{4}'),
            'tripId'             => self::faker()->regexify('trip-[0-9]{6}'),
            'route'              => RouteFactory::new(),
            'stop'               => StopFactory::new(),
            'predictedAt'        => \DateTimeImmutable::createFromMutable($predictedAt),
            'predictedArrivalAt' => \DateTimeImmutable::createFromMutable($arrivalAt),
            'confidence'         => PredictionConfidence::HIGH,
            'delaySec'           => 0,
        ];
    }

    public function bunched(\DateTimeImmutable $time): self
    {
        return $this->with([
            'predictedArrivalAt' => $time,
            'predictedAt'        => $time->modify('-5 minutes'),
        ]);
    }
}
