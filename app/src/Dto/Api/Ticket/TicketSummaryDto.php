<?php

namespace App\Dto\Api\Ticket;

final readonly class TicketSummaryDto
{
    public function __construct(
        public int $id,
        public string $title,
        public string $status,
        public string $priority,
        public string $createdAt,
    ) {
    }
}