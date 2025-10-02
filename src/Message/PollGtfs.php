<?php

namespace App\Message;

use App\Enum\GtfsKindEnum;

final class PollGtfs
{
    public function __construct(public GtfsKindEnum $kind) {}
}
