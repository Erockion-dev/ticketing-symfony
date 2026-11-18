<?php

namespace App\Mapper\Api\Ticket;

use App\Dto\Api\Ticket\CreateUserTicketDto;
use App\Dto\Api\Ticket\TicketResponseDto;
use App\Dto\Api\Ticket\TicketSummaryDto;
use App\Dto\Api\Ticket\UpdateUserTicketDto;
use App\Dto\Api\Ticket\UpdateOperatorTicketDto;
use App\Entity\Ticket;
use App\Entity\User;

final class TicketMapper
{
    public static function toResponseDto(Ticket $ticket): TicketResponseDto
    {
        return new TicketResponseDto(
            id: $ticket->getId(),
            title: $ticket->getTitle(),
            status: $ticket->getStatus()->value,
            priority: $ticket->getPriority()->value,
            createdBy: [
                'id' => $ticket->getCreatedBy()->getId(),
                'name' => $ticket->getCreatedBy()->getFirstname() . ' ' .  $ticket->getCreatedBy()->getLastname(),
            ],
            requester: [
                'id' => $ticket->getRequester()->getId(),
                'name' => $ticket->getRequester()->getFirstname() . ' ' .  $ticket->getRequester()->getLastname(),
            ],
            assignedTo: $ticket->getAssignedTo()
                ? [
                    'id' => $ticket->getAssignedTo()->getId(),
                    'name' => $ticket->getAssignedTo()->getFirstname() . ' ' .  $ticket->getAssignedTo()->getLastname(),
                ]
                : null,
            createdAt: $ticket->getCreatedAt()->format(\DateTimeInterface::ATOM),
            deletedAt: $ticket->getDeletedAt()
                ? $ticket->getDeletedAt()->format(\DateTimeInterface::ATOM)
                : null,
            parentTicket: $ticket->getParentTicket()
                ? self::toSummary($ticket->getParentTicket())
                : null,
            relatedTickets: array_map(
                self::toSummary(...),
                $ticket->getRelatedTickets()->toArray(),
            ),
            ticketComments: array_map(
                static fn ($comment) => [
                    'id' => $comment->getId(),
                    'content' => $comment->getContent(),
                    'createdBy' => [
                        'id' => $comment->getCreatedBy()->getId(),
                        'name' => $comment->getCreatedBy()->getFirstname() . ' ' .$comment->getCreatedBy()->getLastname(),
                    ],
                    'createdAt' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $ticket->getTicketComments()->toArray(),
            ),
        );
    }

    public static function toSummary(Ticket $ticket): TicketSummaryDto
    {
        return new TicketSummaryDto(
            id: $ticket->getId(),
            title: $ticket->getTitle(),
            status: $ticket->getStatus()->value,
            priority: $ticket->getPriority()->value,
            createdAt: $ticket->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public static function createDtoToEntity(
        CreateUserTicketDto $dto,
        User $user,
        ?Ticket $parent = null,
    ): Ticket {
        $ticket = new Ticket();

        $ticket->setTitle($dto->title);
        $ticket->setDescription($dto->description);
        $ticket->setRequester($user);
        $ticket->setCreatedBy($user);
        $ticket->setParentTicket($parent);

        return $ticket;
    }

    public static function applyUserUpdate(
        UpdateUserTicketDto $dto, Ticket $ticket,
        ?Ticket $parentTicket = null,
        $payload = null
    ): void
    {
        if (array_key_exists('title', $payload) && $dto->title !== null) {
            $ticket->setTitle($dto->title);
        }
        if (array_key_exists('priority', $payload) && $dto->priority !== null) {
            $ticket->setPriority($dto->priority);
        }
        
        if (array_key_exists('parentTicketId', $payload)) {
            $ticket->setParentTicket($parentTicket);
        }
    }

    public static function applyOperatorUpdate(
        UpdateOperatorTicketDto $dto, Ticket $ticket,
        ?Ticket $parentTicket = null,
        $payload = null
    ): void
    {
        if (array_key_exists('title', $payload) && $dto->title !== null) {
            $ticket->setTitle($dto->title);
        }
        if (array_key_exists('status', $payload) && $dto->status !== null) {
            $ticket->setStatus($dto->status);
        }

        if (array_key_exists('priority', $payload) && $dto->priority !== null) {
            $ticket->setPriority($dto->priority);
        }
        
        if (array_key_exists('parentTicketId', $payload)) {
            $ticket->setParentTicket($parentTicket);
        }
    }
}
