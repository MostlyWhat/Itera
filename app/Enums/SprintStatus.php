<?php

namespace App\Enums;

/**
 * The life of a Sprint. A Sprint is open from its creation until its review
 * (ITERA-10) completes it. Whether an open Sprint is current or upcoming
 * comes from its dates, not from this status.
 */
enum SprintStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
}
