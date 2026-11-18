<?php

namespace App\Enum;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
