<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\WeatherObservation;
use App\Enum\TransitImpact;
use App\Repository\WeatherObservationRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Live component that displays current weather conditions with transit impact.
 *
 * Auto-updates every 60 seconds.
 */
#[AsLiveComponent]
final class LiveWeatherBanner
{
    use DefaultActionTrait;

    #[LiveProp]
    public bool $detailed = false;

    public function __construct(
        private readonly WeatherObservationRepository $weatherRepo,
    ) {
    }

    /**
     * Get current weather observation.
     */
    public function getWeather(): ?WeatherObservation
    {
        return $this->weatherRepo->findLatest();
    }

    /**
     * Get human-readable weather condition.
     */
    public function getConditionLabel(): string
    {
        $weather = $this->getWeather();
        if ($weather === null) {
            return 'Unknown';
        }

        return match ($weather->getWeatherCondition()) {
            'clear'        => 'Clear',
            'cloudy'       => 'Cloudy',
            'rain'         => 'Rain',
            'snow'         => 'Snow',
            'showers'      => 'Showers',
            'thunderstorm' => 'Thunderstorm',
            default        => 'Unknown',
        };
    }

    /**
     * Get weather icon emoji.
     */
    public function getWeatherIcon(): string
    {
        $weather = $this->getWeather();
        if ($weather === null) {
            return '🌡️';
        }

        return match ($weather->getWeatherCondition()) {
            'clear'        => '☀️',
            'cloudy'       => '☁️',
            'rain'         => '🌧️',
            'snow'         => '❄️',
            'showers'      => '🌦️',
            'thunderstorm' => '⛈️',
            default        => '🌡️',
        };
    }

    /**
     * Get transit impact color class for background.
     */
    public function getImpactColorClass(): string
    {
        $weather = $this->getWeather();
        if ($weather === null) {
            return 'bg-gray-100 border-gray-300';
        }

        return match ($weather->getTransitImpact()) {
            TransitImpact::NONE     => 'bg-success-50 border-success-300',
            TransitImpact::MINOR    => 'bg-warning-50 border-warning-300',
            TransitImpact::MODERATE => 'bg-amber-50 border-amber-400',
            TransitImpact::SEVERE   => 'bg-danger-50 border-danger-400',
        };
    }

    /**
     * Get transit impact label.
     */
    public function getImpactLabel(): string
    {
        $weather = $this->getWeather();
        if ($weather === null) {
            return 'Unknown';
        }

        return match ($weather->getTransitImpact()) {
            TransitImpact::NONE     => 'No Impact',
            TransitImpact::MINOR    => 'Minor Impact',
            TransitImpact::MODERATE => 'Moderate Impact',
            TransitImpact::SEVERE   => 'Severe Impact',
        };
    }

    /**
     * Get impact description for detailed view.
     */
    public function getImpactDescription(): string
    {
        $weather = $this->getWeather();
        if ($weather === null) {
            return '';
        }

        return match ($weather->getTransitImpact()) {
            TransitImpact::NONE     => 'Normal operations expected',
            TransitImpact::MINOR    => 'Slight delays possible',
            TransitImpact::MODERATE => 'Expect 5-10 minute delays',
            TransitImpact::SEVERE   => 'Major delays expected, plan extra time',
        };
    }

    /**
     * Get last update time as exact timestamp.
     * Always shows exact time since weather is polled hourly.
     */
    public function getLastUpdateTime(): string
    {
        $weather = $this->getWeather();
        if ($weather === null) {
            return 'Unknown';
        }

        $observed = $weather->getObservedAt();
        $now      = new \DateTime();

        // If today, show time only (e.g., "11:00 PM")
        if ($observed->format('Y-m-d') === $now->format('Y-m-d')) {
            return $observed->format('g:i A');
        }

        // If yesterday, show "Yesterday at HH:MM AM/PM"
        $yesterday = (new \DateTime())->modify('-1 day');
        if ($observed->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            return 'Yesterday at '.$observed->format('g:i A');
        }

        // Otherwise show full date and time (e.g., "Oct 12, 11:00 PM")
        return $observed->format('M j, g:i A');
    }
}
