<?php

namespace App\Dto\Api\TicketComment;

use Symfony\Component\Validator\Constraints as Assert;

class CreateUserTicketCommentDto
{
    #[Assert\NotNull]
    #[Assert\Length(min: 2, max: 5000)]
    public ?string $content = null;

    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $ticketId = null;
}