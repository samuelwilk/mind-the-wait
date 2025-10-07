<?php

namespace App\Config;

use App\Enum\GtfsSourceEnum;

final readonly class GtfsConfig
{
    public function __construct(
        public GtfsSourceEnum $source,
        public ?string $zipUrl,
        public ?string $zipFallback,
        public ?string $routesUrl,
        public ?string $stopsUrl,
        public ?string $tripsUrl,
        public ?string $stopTimesUrl,
    ) {
    }
}
