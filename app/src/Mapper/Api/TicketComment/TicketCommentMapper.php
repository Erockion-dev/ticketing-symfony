<?php

namespace App\Mapper\Api\TicketComment;

use App\Dto\Api\TicketComment\TicketCommentResponseDto;
use App\Entity\TicketComment;

final class TicketCommentMapper
{
    public static function toResponseDto(TicketComment $ticketComment): TicketCommentResponseDto
    {
        return new TicketCommentResponseDto(
            id: $ticketComment->getId(),
            content: $ticketComment->getContent(),
            createdBy: [
                'id' => $ticketComment->getCreatedBy()->getId(),
                'name' => $ticketComment->getCreatedBy()->getFirstname() . ' ' .  $ticketComment->getCreatedBy()->getLastname(),
            ],
            createdAt: $ticketComment->getCreatedAt()->format(\DateTimeInterface::ATOM),
            deletedAt: $ticketComment->getDeletedAt()
                ? $ticketComment->getDeletedAt()->format(\DateTimeInterface::ATOM)
                : null,
        );
    }
}