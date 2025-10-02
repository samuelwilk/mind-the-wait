<?php

namespace App\MessageHandler;

use App\Message\PollGtfs;
use App\Message\ProcessSnapshot;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class PollGtfsHandler
{
    public function __construct(
        private HttpClientInterface $http,
        private MessageBusInterface $bus,
        private LoggerInterface     $log,
    ) {}

    public function __invoke(PollGtfs $m): void
    {
        // Example: fetch JSON (NOT protobuf) if you add such endpoints later.
        $env = $m->kind->envVar();
        $url = $_ENV[$env] ?? null;
        if (!$url) {
            $this->log->debug('No URL for kind', ['kind' => $m->kind->value]);
            return;
        }

        $res = $this->http->request('GET', $url, [
            'headers' => ['User-Agent' => 'MindTheWait/1.0'],
            'timeout' => 8,
        ]);

        if (200 !== $res->getStatusCode()) {
            $this->log->warning('poll status != 200', [
                'kind' => $m->kind->value,
                'status' => $res->getStatusCode()
            ]);
            return;
        }

        $json = $res->toArray(false);
        $headerTs = (int)($json['ts'] ?? time());
        $payload  = $json['data'] ?? ($json['vehicles'] ?? $json ?? []);

        $this->bus->dispatch(new ProcessSnapshot($m->kind, $payload, $headerTs));
    }
}
