<?php

namespace App\Dto\Api\Ticket;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateOperatorTicketDto
{
    public function __construct(
        #[Assert\Length(min: 2, max: 120)]
        public ?string $title = null,

        public ?TicketStatus $status = null,

        public ?TicketPriority $priority = null,

        #[Assert\Positive]
        public ?int $parentTicketId = null,
    ) {
    }
}
