<?php

namespace App\Dto\Api\Ticket;

use App\Enum\TicketPriority;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateUserTicketDto
{
    public function __construct(
        #[Assert\Length(min: 2, max: 120)]
        public ?string $title = null,

        public ?TicketPriority $priority = null,

        #[Assert\Positive]
        public ?int $parentTicketId = null,
    ) {
    }
}
