<?php

namespace App\Service\Ticket;

use App\Entity\Ticket;
use App\Enum\TicketStatus;
use App\Enum\TicketStatusAction;

final class TicketStatusManager
{
    public function changeStatus(Ticket $ticket, TicketStatusAction $action): bool
    {
        switch ($action) {
            case TicketStatusAction::ToInProgress:
                if (!$this->canToInProgress($ticket)) {
                    return false;
                }

                $this->toInProgress($ticket);
                return true;

            case TicketStatusAction::ToBlocked:
                if (!$this->canToBlocked($ticket)) {
                    return false;
                }

                $this->toBlocked($ticket);
                return true;

            case TicketStatusAction::ToResolved:
                if (!$this->canToResolved($ticket)) {
                    return false;
                }

                $this->toResolved($ticket);
                return true;

            case TicketStatusAction::ToClosed:
                if (!$this->canToClosed($ticket)) {
                    return false;
                }

                $this->toClosed($ticket);
                return true;

            default:
                return false;
        }
    }

    private function canToInProgress(Ticket $ticket): bool
    {
        return $ticket->getStatus() === TicketStatus::Open
            || $ticket->getStatus() === TicketStatus::Blocked
            || $ticket->getStatus() === TicketStatus::Resolved;
    }

    private function toInProgress(Ticket $ticket): void
    {
        $ticket->setStatus(TicketStatus::InProgress);
    }

    private function canToBlocked(Ticket $ticket): bool
    {
        return $ticket->getStatus() === TicketStatus::InProgress;
    }

    private function toBlocked(Ticket $ticket): void
    {
        $ticket->setStatus(TicketStatus::Blocked);
    }

    private function canToResolved(Ticket $ticket): bool
    {
        return $ticket->getStatus() === TicketStatus::Open
            || $ticket->getStatus() === TicketStatus::Blocked
            || $ticket->getStatus() === TicketStatus::InProgress;
    }

    private function toResolved(Ticket $ticket): void
    {
        $ticket->setStatus(TicketStatus::Resolved);
    }

    private function canToClosed(Ticket $ticket): bool
    {
        return $ticket->getStatus() === TicketStatus::Resolved;
    }

    private function toClosed(Ticket $ticket): void
    {
        $ticket->setStatus(TicketStatus::Closed);
    }

    public function getPossibility(Ticket $ticket): array
    {
        switch ($ticket->getStatus()) {
            case TicketStatus::Open:
                return [
                    TicketStatusAction::ToInProgress->value => TicketStatusAction::ToInProgress,
                    TicketStatusAction::ToResolved->value => TicketStatusAction::ToResolved,
                ];
            case TicketStatus::InProgress:
                return [
                    TicketStatusAction::ToBlocked->value => TicketStatusAction::ToBlocked,
                    TicketStatusAction::ToResolved->value => TicketStatusAction::ToResolved,
                ];
            case TicketStatus::Blocked:
                return [
                    TicketStatusAction::ToInProgress->value => TicketStatusAction::ToInProgress,
                    TicketStatusAction::ToResolved->value => TicketStatusAction::ToResolved,
                ];
            case TicketStatus::Resolved:
                return [
                    TicketStatusAction::ToClosed->value => TicketStatusAction::ToClosed,
                    TicketStatusAction::ToInProgress->value => TicketStatusAction::ToInProgress,
                ];
            default:
                return [];
        }
    }
}
