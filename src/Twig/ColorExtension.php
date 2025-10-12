<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for color manipulation and contrast calculation.
 */
final class ColorExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('route_badge_classes', [$this, 'getRouteBadgeClasses']),
            new TwigFunction('route_badge_text_style', [$this, 'getRouteBadgeTextStyle']),
        ];
    }

    /**
     * Generate CSS classes for route badge with proper text contrast.
     *
     * @param string|null $hexColor     6-character hex color (without #) from GTFS route_color
     * @param string      $fallbackBg   Fallback Tailwind class for background (e.g., 'bg-primary-100')
     * @param string      $fallbackText Fallback Tailwind class for text (e.g., 'text-primary-800')
     */
    public function getRouteBadgeClasses(?string $hexColor, string $fallbackBg = 'bg-gray-100', string $fallbackText = 'text-gray-800'): string
    {
        // If no color provided, use fallback
        if ($hexColor === null || trim($hexColor) === '') {
            return "$fallbackBg $fallbackText";
        }

        // Clean the hex color (remove # if present, trim whitespace)
        $hex = ltrim(trim($hexColor), '#');

        // Validate hex color (must be 6 characters)
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            return "$fallbackBg $fallbackText";
        }

        // Calculate luminance and determine text color
        $textColor = $this->shouldUseLightText($hex) ? 'text-white' : 'text-gray-900';

        return $textColor;
    }

    /**
     * Get inline text color style for proper contrast on colored backgrounds.
     *
     * @param string|null $hexColor 6-character hex color (without #) from GTFS route_color
     *
     * @return string Inline style attribute value (e.g., "color: #ffffff;") or empty string
     */
    public function getRouteBadgeTextStyle(?string $hexColor): string
    {
        // If no color provided, no inline style needed
        if ($hexColor === null || trim($hexColor) === '') {
            return '';
        }

        // Clean the hex color (remove # if present, trim whitespace)
        $hex = ltrim(trim($hexColor), '#');

        // Validate hex color (must be 6 characters)
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            return '';
        }

        // Calculate luminance and determine text color
        $textColor = $this->shouldUseLightText($hex) ? '#ffffff' : '#1f2937';

        return "color: {$textColor};";
    }

    /**
     * Determine if light text should be used on a given background color.
     * Uses relative luminance calculation per WCAG 2.1 guidelines with gamma correction.
     */
    private function shouldUseLightText(string $hex): bool
    {
        // Convert hex to RGB (0-255)
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Convert to sRGB (0-1)
        $r /= 255;
        $g /= 255;
        $b /= 255;

        // Apply gamma correction (WCAG 2.1 formula)
        $r = $r <= 0.03928 ? $r / 12.92 : (($r + 0.055) / 1.055) ** 2.4;
        $g = $g <= 0.03928 ? $g / 12.92 : (($g + 0.055) / 1.055) ** 2.4;
        $b = $b <= 0.03928 ? $b / 12.92 : (($b + 0.055) / 1.055) ** 2.4;

        // Calculate relative luminance using WCAG 2.1 coefficients
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        // Use white text if luminance is below 0.75
        // Higher threshold = more aggressive about using white text
        // This ensures maximum readability for all but the lightest colors
        return $luminance < 0.75;
    }
}
