<?php

namespace App\Controller\Api\Admin;

use App\Dto\Api\TicketComment\CreateUserTicketCommentDto;
use App\Entity\Ticket;
use App\Entity\TicketComment;
use App\Entity\User;
use App\Mapper\Api\TicketComment\TicketCommentMapper;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/ticket-comments', name: 'app_api_dashboard_admin_ticket_comments_')]
final class TicketCommentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TicketRepository $ticketRepository,
    ) {}

    #[Route(name: 'new', methods: ['POST'])]
    public function new(#[MapRequestPayload()] CreateUserTicketCommentDto $createUserTicketCommentDto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var ?Ticket $ticket */
        $ticket = $this->ticketRepository->find($createUserTicketCommentDto->ticketId);
        if (!$ticket) {
            return $this->json(
                ['message' => 'The ticket is not valid.'],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        $ticketComment = (new ticketComment())
            ->setContent($createUserTicketCommentDto->content)
            ->setCreatedBy($user)
            ->setTicket($ticket)
        ;

        $this->entityManager->persist($ticketComment);
        $this->entityManager->flush();
        
        return $this->json(
            TicketCommentMapper::toResponseDto($ticketComment),
            JsonResponse::HTTP_CREATED,
        );
    }
}
