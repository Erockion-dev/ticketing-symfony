<?php

namespace App\Dto\Api\Ticket;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateUserTicketDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 120)]
        public string $title,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 5000)]
        public string $description,

        #[Assert\Positive]
        public ?int $parentTicketId = null,
    ) {
    }
}
