<?php

namespace App\Message;

use App\Enum\GtfsKindEnum;

final class ProcessSnapshot
{
    public function __construct(
        public GtfsKindEnum $kind,
        public array        $payload,
        public int          $headerTs
    ) {}
}
