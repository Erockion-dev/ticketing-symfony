<?php

namespace App\Controller\Api\User;

use App\Entity\Ticket;
use App\Entity\User;
use App\Dto\Api\Ticket\CreateUserTicketDto;
use App\Dto\Api\Ticket\UpdateUserTicketDto;
use App\Mapper\Api\Ticket\TicketMapper;
use App\Repository\TicketRepository;
use App\Service\Ticket\ParentTicketValidator;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/user/tickets', name: 'api_user_tickets_')]
final class TicketController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TicketRepository $ticketRepository,
        private ParentTicketValidator $parentTicketValidator,
    ) {}

    #[Route(name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        PaginatorInterface $paginator
    ): JsonResponse
    {
        
        $limit = $request->query->getInt('limit', 25);

        $limit = min(max($limit, 1), 100);

        /** @var User $user */
        $user = $this->getUser();

        $pagination = $paginator->paginate(
            $this->ticketRepository->createForRequesterQueryBuilder($user),
            $request->query->getInt('page', 1),
            $limit,
        );

        $data = array_map(
            TicketMapper::toResponseDto(...),
            iterator_to_array($pagination->getItems()),
        );

        return $this->json([
            'data' => $data,
            'pagination' => [
                'page' => $pagination->getCurrentPageNumber(),
                'limit' => $pagination->getItemNumberPerPage(),
                'totalItems' => $pagination->getTotalItemCount(),
                'totalPages' => (int) ceil(
                    $pagination->getTotalItemCount() / $pagination->getItemNumberPerPage()
                ),
            ],
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Ticket $ticket): JsonResponse
    {
        if ($ticket->getRequester() !== $this->getUser()) {
            return $this->json(
                ['message' => 'You are not allowed to show this ticket.'],
                JsonResponse::HTTP_FORBIDDEN,
            );
        }

        return $this->json(TicketMapper::toResponseDto($ticket));
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(
        #[MapRequestPayload()] CreateUserTicketDto $createUserTicketDto,
    ): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $parentTicket = null;
        if ($createUserTicketDto->parentTicketId !== null) {
            $parentTicket = $this->ticketRepository->find($createUserTicketDto->parentTicketId);
            if ($parentTicket === null) {
                return $this->json(
                    ['message' => 'Parent ticket not found.'],
                    JsonResponse::HTTP_BAD_REQUEST,
                );
            }

            if ($parentTicket->getRequester() !== $user) {
                return $this->json(
                    ['message' => 'You are not allowed to use this parent ticket.'],
                    JsonResponse::HTTP_FORBIDDEN,
                );
            }

            $error = $this->parentTicketValidator->validate($parentTicket, $user);
            if ($error !== null) {
                return $this->json(
                    ['message' => $error],
                    JsonResponse::HTTP_BAD_REQUEST,
                );
            }
        }

        $ticket = (new Ticket())
            ->setTitle($createUserTicketDto->title)
            ->setDescription($createUserTicketDto->description)
            ->setRequester($user)
            ->setCreatedBy($user)
            ->setParentTicket($parentTicket)
        ;

        $this->entityManager->persist($ticket);
        $this->entityManager->flush();


        return $this->json(
            TicketMapper::toResponseDto($ticket),
            JsonResponse::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'edit', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function edit(
        Request $request,
        Ticket $ticket,
        #[MapRequestPayload()] UpdateUserTicketDto $updateUserTicketDto,
    ): JsonResponse {
        $payload = json_decode(
            $request->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $parentTicket = null;

        /** @var User $user */
        $user = $this->getUser();

        if ($ticket->getCreatedBy() !== $user && $ticket->getRequester() !== $user) {
            return $this->json(
                ['message' => 'Ticket with id '. $ticket->getId(). ' is not found.'],
                JsonResponse::HTTP_NOT_FOUND,
            );
        }

        if ($updateUserTicketDto->parentTicketId) {

            if ($updateUserTicketDto->parentTicketId === $ticket->getId()) {
                return $this->json(
                    ['message' => 'A ticket cannot be its own parent.'],
                    JsonResponse::HTTP_BAD_REQUEST,
                );
            }
            /** @var ?Ticket $parentTicket */
            $parentTicket = $this->ticketRepository->find($updateUserTicketDto->parentTicketId);
            if ($parentTicket) {
                if ($parentTicket->getParentTicket()) {
                    return $this->json(
                        ['message' => 'A child ticket cannot be used as a parent.'],
                        JsonResponse::HTTP_BAD_REQUEST,
                    );
                }
                if ($parentTicket->getDeletedAt() !== null) {
                    return $this->json(
                        ['message' => 'A deleted ticket cannot be used as a parent.'],
                        JsonResponse::HTTP_BAD_REQUEST,
                    );
                }
                if ($parentTicket->getRequester() !== $user) {
                    return $this->json(
                        ['message' => 'You are not allowed to use this ticket as a parent.'],
                        JsonResponse::HTTP_BAD_REQUEST,
                    );
                }
            } else {
                return $this->json(
                    ['message' => 'Parent ticket not found.'],
                    JsonResponse::HTTP_BAD_REQUEST,
                );
            }
        }

        TicketMapper::applyUserUpdate($updateUserTicketDto, $ticket, $parentTicket, $payload);

        $this->entityManager->flush();

        return $this->json(TicketMapper::toResponseDto($ticket));
    }
}
