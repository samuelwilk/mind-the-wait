<?php

namespace App\Factory;

use App\Config\GtfsConfig;
use App\Enum\GtfsSourceEnum;
use Symfony\Component\Console\Input\InputInterface;

final readonly class GtfsConfigFactory
{
    public function __construct(
        private string $gtfsStaticUrl,
        private ?string $gtfsStaticFallback,
        private string $arcgisRoutesUrl,
        private string $arcgisStopsUrl,
        private string $arcgisTripsUrl,
        private string $arcgisStopTimesUrl,
    ) {
    }

    public function fromInput(InputInterface $input): GtfsConfig
    {
        $mode     = $input->getOption('mode');
        $source   = $input->getOption('source') ?? $this->gtfsStaticUrl;
        $fallback = $this->gtfsStaticFallback;

        $routesUrl    = $input->getOption('routes-url')     ?? $this->arcgisRoutesUrl;
        $stopsUrl     = $input->getOption('stops-url')      ?? $this->arcgisStopsUrl;
        $tripsUrl     = $input->getOption('trips-url')      ?? $this->arcgisTripsUrl;
        $stopTimesUrl = $input->getOption('stop-times-url') ?? $this->arcgisStopTimesUrl;

        $arcgisSet      = $routesUrl && $stopsUrl && $tripsUrl && $stopTimesUrl;
        $resolvedSource = match ($mode) {
            'arcgis' => GtfsSourceEnum::Arcgis,
            'zip'    => GtfsSourceEnum::Zip,
            null     => $arcgisSet ? GtfsSourceEnum::Arcgis : GtfsSourceEnum::Zip,
            default  => throw new \InvalidArgumentException("Invalid GTFS mode: $mode"),
        };

        return new GtfsConfig(
            source: $resolvedSource,
            zipUrl: $source,
            zipFallback: $fallback,
            routesUrl: $routesUrl,
            stopsUrl: $stopsUrl,
            tripsUrl: $tripsUrl,
            stopTimesUrl: $stopTimesUrl,
        );
    }
}
