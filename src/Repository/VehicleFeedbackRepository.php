<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\VehiclePunctualityLabel;
use Predis\ClientInterface;

final readonly class VehicleFeedbackRepository implements VehicleFeedbackRepositoryInterface
{
    private const KEY_PREFIX = 'mtw:vehicle_feedback:';
    private const EXPIRY_SEC = 86400; // keep a rolling day of feedback

    public function __construct(private ClientInterface $redis)
    {
    }

    public function recordVote(string $vehicleId, VehiclePunctualityLabel $label): array
    {
        $key = $this->key($vehicleId);

        $this->redis->hincrby($key, $label->value, 1);
        $this->redis->hincrby($key, 'total', 1);
        $this->redis->expire($key, self::EXPIRY_SEC);

        return $this->getSummary($vehicleId);
    }

    public function getSummary(string $vehicleId): array
    {
        $key  = $this->key($vehicleId);
        $data = $this->redis->hgetall($key);

        return [
            VehiclePunctualityLabel::AHEAD->value   => isset($data[VehiclePunctualityLabel::AHEAD->value]) ? (int) $data[VehiclePunctualityLabel::AHEAD->value] : 0,
            VehiclePunctualityLabel::ON_TIME->value => isset($data[VehiclePunctualityLabel::ON_TIME->value]) ? (int) $data[VehiclePunctualityLabel::ON_TIME->value] : 0,
            VehiclePunctualityLabel::LATE->value    => isset($data[VehiclePunctualityLabel::LATE->value]) ? (int) $data[VehiclePunctualityLabel::LATE->value] : 0,
            'total'                                 => isset($data['total']) ? (int) $data['total'] : 0,
        ];
    }

    private function key(string $vehicleId): string
    {
        return self::KEY_PREFIX.$vehicleId;
    }
}
