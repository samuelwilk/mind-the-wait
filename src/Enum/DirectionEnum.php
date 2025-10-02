<?php
namespace App\Enum;

/**
 * NOTE: GTFS `direction_id` semantics are feed-specific (0/1 labels vary).
 * Use neutral names; map to labels in UI with your static GTFS.
 */
enum DirectionEnum: int
{
    case Zero = 0;
    case One  = 1;
}
