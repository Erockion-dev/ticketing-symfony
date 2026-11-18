<?php

namespace App\Dto\Api\Ticket;

final readonly class TicketResponseDto
{
    public function __construct(
        public int $id,
        public string $title,
        public string $status,
        public string $priority,
        public array $createdBy,
        public array $requester,
        public ?array $assignedTo,
        public string $createdAt,
        public ?string $deletedAt,
        public ?TicketSummaryDto $parentTicket,
        public array $relatedTickets,
        public array $ticketComments,
    ) {
    }
}
