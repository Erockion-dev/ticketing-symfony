<?php

namespace App\Service\Ticket;

use App\Entity\Ticket;
use App\Entity\User;

final class ParentTicketValidator
{
    public function validate(Ticket $parentTicket, User $requester): ?string
    {
        if ($parentTicket->getRequester() !== $requester) {
            return 'You are not allowed to use this parent ticket.';
        }

        if ($parentTicket->getDeletedAt() !== null) {
            return 'A deleted ticket cannot be used as a parent.';
        }

        if ($parentTicket->getParentTicket() !== null) {
            return 'A child ticket cannot be used as a parent ticket.';
        }

        return null;
    }
}