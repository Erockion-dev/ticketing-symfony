<?php

namespace App\Enum;

enum TicketStatusAction: string
{
    case ToInProgress = 'to_in_progress';
    case ToBlocked = 'to_blocked';
    case ToResolved = 'to_resolved';
    case ToClosed = 'to_closed';
}
