<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Represents weather conditions for transit impact analysis.
 *
 * This enum provides type-safe weather condition handling with helper methods
 * for chart colors, labels, and icons.
 */
enum WeatherCondition: string
{
    case CLEAR        = 'clear';
    case CLOUDY       = 'cloudy';
    case SNOW         = 'snow';
    case RAIN         = 'rain';
    case SHOWERS      = 'showers';
    case THUNDERSTORM = 'thunderstorm';
    case FOG          = 'fog';
    case UNKNOWN      = 'unknown';

    /**
     * Get human-readable label for this weather condition.
     */
    public function label(): string
    {
        return match ($this) {
            self::CLEAR        => 'Clear',
            self::CLOUDY       => 'Cloudy',
            self::SNOW         => 'Snow',
            self::RAIN         => 'Rain',
            self::SHOWERS      => 'Showers',
            self::THUNDERSTORM => 'Thunderstorm',
            self::FOG          => 'Fog',
            self::UNKNOWN      => 'Unknown',
        };
    }

    /**
     * Get chart color (hex code) for this weather condition.
     *
     * Colors follow a consistent scheme:
     * - Clear: Warm yellow (#fef3c7)
     * - Cloudy: Neutral gray (#e5e7eb)
     * - Rain/Showers: Cool blue (#dbeafe)
     * - Snow: Purple tint (#ede9fe)
     * - Thunderstorm: Dark blue (#bfdbfe)
     * - Fog: Light gray (#f3f4f6)
     */
    public function chartColor(): string
    {
        return match ($this) {
            self::CLEAR        => '#fef3c7',
            self::CLOUDY       => '#e5e7eb',
            self::SNOW         => '#ede9fe',
            self::RAIN         => '#dbeafe',
            self::SHOWERS      => '#dbeafe',
            self::THUNDERSTORM => '#bfdbfe',
            self::FOG          => '#f3f4f6',
            self::UNKNOWN      => '#d1d5db',
        };
    }

    /**
     * Get icon name/class for this weather condition.
     *
     * These can be used with icon libraries or custom SVG components.
     */
    public function icon(): string
    {
        return match ($this) {
            self::CLEAR        => 'sun',
            self::CLOUDY       => 'cloud',
            self::SNOW         => 'snowflake',
            self::RAIN         => 'cloud-rain',
            self::SHOWERS      => 'cloud-showers',
            self::THUNDERSTORM => 'cloud-bolt',
            self::FOG          => 'smog',
            self::UNKNOWN      => 'question',
        };
    }

    /**
     * Parse weather condition from database string value.
     *
     * Case-insensitive matching with fallback to UNKNOWN.
     *
     * @param string|null $value Weather condition string from database
     *
     * @return self Matched weather condition or UNKNOWN
     */
    public static function fromString(?string $value): self
    {
        if ($value === null) {
            return self::UNKNOWN;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'clear'        => self::CLEAR,
            'cloudy'       => self::CLOUDY,
            'snow'         => self::SNOW,
            'rain'         => self::RAIN,
            'showers'      => self::SHOWERS,
            'thunderstorm' => self::THUNDERSTORM,
            'fog'          => self::FOG,
            default        => self::UNKNOWN,
        };
    }

    /**
     * Get conditions to display in charts (excludes UNKNOWN).
     *
     * @return array<WeatherCondition> Weather conditions for chart display
     */
    public static function chartConditions(): array
    {
        return [
            self::SNOW,
            self::RAIN,
            self::SHOWERS,
            self::THUNDERSTORM,
            self::CLOUDY,
            self::CLEAR,
            self::FOG,
        ];
    }

    /**
     * Get primary conditions for bunching analysis.
     *
     * Returns the core set of conditions used in bunching weather charts:
     * Snow, Rain, Cloudy, Clear.
     *
     * @return array<WeatherCondition> Primary weather conditions
     */
    public static function bunchingConditions(): array
    {
        return [
            self::SNOW,
            self::RAIN,
            self::CLOUDY,
            self::CLEAR,
        ];
    }
}
