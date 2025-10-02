<?php

namespace App\MessageHandler;

use App\Message\ProcessSnapshot;
use Predis\ClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessSnapshotHandler
{
    public function __construct(private ClientInterface $redis) {}

    public function __invoke(ProcessSnapshot $m): void
    {
        $key = $m->kind->redisKey(); // "mtw:vehicles" | "mtw:trips" | "mtw:alerts"
        $this->redis->hmset($key, [
            'ts'   => $m->headerTs,
            'json' => json_encode($m->payload, JSON_UNESCAPED_UNICODE),
        ]);
        $this->redis->expire($key, 180);
    }
}
