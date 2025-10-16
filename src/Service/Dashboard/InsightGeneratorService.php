<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use OpenAI;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates AI-powered narrative insights from weather and performance data.
 *
 * Uses OpenAI GPT to create dynamic, data-driven stories that change based on
 * actual transit performance patterns.
 */
final readonly class InsightGeneratorService
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        private string $openaiApiKey,
    ) {
    }

    /**
     * Generate winter operations insight narrative.
     *
     * @param array<string, mixed> $stats Statistics from WeatherAnalysisService
     */
    public function generateWinterOperationsInsight(array $stats): string
    {
        $cacheKey = 'insight_winter_ops_'.md5(serialize($stats));
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return (string) $item->get();
        }

        $prompt = <<<PROMPT
You are a transit data analyst writing insights for a public-facing transit dashboard.

Based on this real data about route performance in clear vs snow conditions:
- Worst affected route: {$stats['worstRoute']}
- Performance in clear weather: {$stats['clearPerf']}%
- Performance in snow: {$stats['snowPerf']}%
- Performance drop: {$stats['performanceDrop']}%

Write a brief 2-paragraph insight (max 150 words):
1. State the key finding and explain why this route is vulnerable
2. Describe what factors typically contribute to this performance drop

Tone: Professional but accessible. Avoid jargon. Use HTML paragraph tags (<p>).
Be concise and scannable. Focus on data analysis, not recommendations.

Do not use any emojis.
PROMPT;

        $content = $this->generateContent($prompt);

        $item->set($content);
        $item->expiresAfter(86400); // 24 hours
        $this->cache->save($item);

        return $content;
    }

    /**
     * Generate temperature threshold insight narrative.
     *
     * @param array<string, mixed> $stats Statistics from WeatherAnalysisService
     */
    public function generateTemperatureThresholdInsight(array $stats): string
    {
        $cacheKey = 'insight_temp_threshold_'.md5(serialize($stats));
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return (string) $item->get();
        }

        $prompt = <<<PROMPT
You are a transit data analyst writing insights for a public-facing transit dashboard.

Based on this real data about transit performance at different temperatures:
- Performance above -20°C: {$stats['aboveThreshold']}%
- Performance below -20°C: {$stats['belowThreshold']}%
- Performance drop: {$stats['performanceDrop']}%
- Days observed above -20°C: {$stats['daysAbove']}
- Days observed below -20°C: {$stats['daysBelow']}

Write a brief 2-paragraph insight (max 150 words):
1. State the critical temperature threshold and describe the performance impact
2. Explain the main contributing factors that cause this temperature sensitivity

Tone: Professional but accessible. Use HTML paragraph tags (<p>).
Be concise. Focus on data analysis, not recommendations.

Do not use any emojis.
PROMPT;

        $content = $this->generateContent($prompt);

        $item->set($content);
        $item->expiresAfter(86400); // 24 hours
        $this->cache->save($item);

        return $content;
    }

    /**
     * Generate weather impact matrix insight narrative.
     *
     * @param array<string, mixed> $stats Statistics from WeatherAnalysisService
     */
    public function generateWeatherImpactMatrixInsight(array $stats): string
    {
        $cacheKey = 'insight_impact_matrix_'.md5(serialize($stats));
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return (string) $item->get();
        }

        $prompt = <<<PROMPT
You are a transit data analyst writing insights for a public-facing transit dashboard.

Based on this real data about system-wide weather impact:
- Worst weather condition: {$stats['worstCondition']}
- Average performance in this condition: {$stats['avgPerformance']}%
- Days observed: {$stats['dayCount']}

Write a brief 2-paragraph insight (max 120 words):
1. Explain how to read the heatmap (red = poor performance, green = good)
2. Highlight the worst condition's impact and why this helps identify vulnerable routes

Tone: Professional but accessible. Use HTML paragraph tags (<p>).
Be concise.

Do not use any emojis.
PROMPT;

        $content = $this->generateContent($prompt);

        $item->set($content);
        $item->expiresAfter(86400); // 24 hours
        $this->cache->save($item);

        return $content;
    }

    /**
     * Generate bunching by weather insight narrative.
     *
     * @param array<string, mixed> $stats Statistics from WeatherAnalysisService
     */
    public function generateBunchingByWeatherInsight(array $stats): string
    {
        $cacheKey = 'insight_bunching_'.md5(serialize($stats));
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return (string) $item->get();
        }

        $prompt = <<<PROMPT
You are a transit data analyst writing insights for a public-facing transit dashboard.

Based on this data about vehicle bunching in different weather conditions:
- Snow incidents: {$stats['snowIncidents']}
- Rain incidents: {$stats['rainIncidents']}
- Clear weather incidents: {$stats['clearIncidents']}
- Multiplier (snow vs clear): {$stats['multiplier']}x

Note: Bunching detection is in development; these are representative samples.

Write a brief 2-paragraph insight (max 140 words):
1. Explain what bunching is and why weather increases it
2. Describe the observed patterns and their impact on riders

Include a brief note that bunching detection is in development.
Tone: Professional but accessible. Use HTML paragraph tags (<p>).
Focus on data analysis, not recommendations.

Do not use any emojis.
PROMPT;

        $content = $this->generateContent($prompt);

        $item->set($content);
        $item->expiresAfter(86400); // 24 hours
        $this->cache->save($item);

        return $content;
    }

    /**
     * Generate key takeaway summary for weather impact page.
     */
    public function generateWeatherImpactKeyTakeaway(): string
    {
        $cacheKey = 'insight_key_takeaway';
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return (string) $item->get();
        }

        $prompt = <<<PROMPT
You are a transit data analyst writing a summary for a public-facing transit dashboard's weather impact analysis page.

Write a 2-3 sentence key takeaway that:
1. Summarizes why weather impact analysis matters for riders and transit understanding
2. Highlights the key patterns observed in the data
3. Explains what these patterns reveal about transit system behavior

Tone: Professional, informative, focused on understanding. Keep it concise and impactful.
Focus on data insights, not recommendations.

Do not use any emojis.
PROMPT;

        $content = $this->generateContent($prompt);

        $item->set($content);
        $item->expiresAfter(604800); // 7 days (less dynamic)
        $this->cache->save($item);

        return $content;
    }

    /**
     * Generate dashboard insight card: Winter Weather Impact.
     *
     * @param array<string, mixed> $stats Quick stats for the card
     */
    public function generateDashboardWinterImpactCard(array $stats): string
    {
        $cacheKey = 'insight_dashboard_winter_'.md5(serialize($stats));
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return (string) $item->get();
        }

        $prompt = <<<PROMPT
You are writing a brief insight card for a transit dashboard homepage.

Based on system-wide data showing routes experience an average {$stats['avgDrop']}% drop in performance during snow vs clear conditions.

Write 1-2 sentences that:
1. State the key finding concisely
2. Make it compelling enough to click "View Full Analysis"

Tone: Punchy, informative. Use HTML paragraph tags (<p>). Use <strong> for the percentage.

Do not use any emojis.
PROMPT;

        $content = $this->generateContent($prompt);

        $item->set($content);
        $item->expiresAfter(86400); // 24 hours
        $this->cache->save($item);

        return $content;
    }

    /**
     * Generate dashboard insight card: Temperature Threshold.
     *
     * @param array<string, mixed> $stats Quick stats for the card
     */
    public function generateDashboardTemperatureCard(array $stats): string
    {
        $cacheKey = 'insight_dashboard_temp_'.md5(serialize($stats));
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return (string) $item->get();
        }

        $prompt = <<<PROMPT
You are writing a brief insight card for a transit dashboard homepage.

Based on data showing performance drops sharply below {$stats['threshold']}°C, with delays increasing by {$stats['delayIncrease']} minutes on average.

Write 1-2 sentences that:
1. State the temperature threshold and impact
2. Make it compelling enough to click "View Full Analysis"

Tone: Punchy, informative. Use HTML paragraph tags (<p>). Use <strong> for temperature and time.

Do not use any emojis.
PROMPT;

        $content = $this->generateContent($prompt);

        $item->set($content);
        $item->expiresAfter(86400); // 24 hours
        $this->cache->save($item);

        return $content;
    }

    /**
     * Generate content using OpenAI API with retry logic for errors.
     */
    private function generateContent(string $prompt): string
    {
        $maxRetries = 2;
        $retryDelay = 5; // seconds - shorter delay since paid tier has higher limits

        for ($attempt = 1; $attempt <= $maxRetries; ++$attempt) {
            try {
                $client = OpenAI::client($this->openaiApiKey);

                $response = $client->chat()->create([
                    'model'    => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a professional transit data analyst writing clear, accessible insights for a public-facing dashboard. Focus on being informative, accurate, and helpful. Never use emojis.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 300,
                ]);

                $content = $response->choices[0]->message->content ?? '';

                if (empty($content)) {
                    $this->logger->warning('OpenAI returned empty content', ['prompt' => $prompt]);

                    return $this->getFallbackContent();
                }

                return $content;
            } catch (\Exception $e) {
                $isRateLimit = str_contains($e->getMessage(), 'rate limit') || str_contains($e->getMessage(), 'Rate limit');

                if ($isRateLimit && $attempt < $maxRetries) {
                    $this->logger->warning("Rate limit hit, retrying in {$retryDelay}s (attempt {$attempt}/{$maxRetries})", [
                        'prompt' => substr($prompt, 0, 100),
                    ]);
                    sleep($retryDelay);
                    continue;
                }

                $this->logger->error('Failed to generate AI insight', [
                    'error'    => $e->getMessage(),
                    'prompt'   => substr($prompt, 0, 200),
                    'attempts' => $attempt,
                ]);

                return $this->getFallbackContent();
            }
        }

        return $this->getFallbackContent();
    }

    /**
     * Get fallback content when AI generation fails.
     */
    private function getFallbackContent(): string
    {
        return '<p>Insight generation temporarily unavailable. Please check back later.</p>';
    }
}
