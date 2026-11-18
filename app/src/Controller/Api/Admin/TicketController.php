<?php

namespace App\Controller\Api\Admin;

use App\Dto\Api\Ticket\CreateOperatorTicketDto;
use App\Dto\Api\Ticket\UpdateOperatorTicketDto;
use App\Entity\Ticket;
use App\Entity\User;
use App\Mapper\Api\Ticket\TicketMapper;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\Ticket\ParentTicketValidator;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/tickets', name: 'app_api_admin_tickets_')]
final class TicketController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TicketRepository $ticketRepository,
        private UserRepository $userRepository,
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

        $pagination = $paginator->paginate(
            $this->ticketRepository->createListQueryBuilder(),
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
        return $this->json(TicketMapper::toResponseDto($ticket));
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(
        #[MapRequestPayload()] CreateOperatorTicketDto $createOperatorTicketDto,
    ): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $requester = $this->userRepository->find($createOperatorTicketDto->requester);

        if (
            $requester === null
            || $requester->getDeletedAt() !== null
            || !$requester->isActive()
        ) {
            return $this->json(
                ['message' => 'Requester is not valid.'],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        $parentTicket = null;
        if ($createOperatorTicketDto->parentTicketId) {
            /** @var ?Ticket $parentTicket */
            $parentTicket = $this->ticketRepository->find($createOperatorTicketDto->parentTicketId);
            if ($parentTicket === null) {
                return $this->json(
                    ['message' => 'ParentTicket is not exist.'],
                    JsonResponse::HTTP_BAD_REQUEST,
                );
            }
        }

        if ($parentTicket) {
            $error = $this->parentTicketValidator->validate($parentTicket, $requester);
            if ($error !== null) {
                return $this->json(
                    ['message' => $error],
                    JsonResponse::HTTP_BAD_REQUEST,
                );
            }
        }

        $ticket = (new Ticket())
            ->setTitle($createOperatorTicketDto->title)
            ->setDescription($createOperatorTicketDto->description)
            ->setRequester($requester)
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
        #[MapRequestPayload()] UpdateOperatorTicketDto $updateUserTicketDto,
    ): JsonResponse {
        $payload = json_decode(
            $request->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $parentTicket = null;

        /** @var User $user */
        $user = $this->getUser();

        if ($ticket->getAssignedTo() !== $user) {
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
            } else {
                return $this->json(
                    ['message' => 'Parent ticket not found.'],
                    JsonResponse::HTTP_BAD_REQUEST,
                );
            }
        }

        TicketMapper::applyOperatorUpdate($updateUserTicketDto, $ticket, $parentTicket, $payload);

        $this->entityManager->flush();

        return $this->json(TicketMapper::toResponseDto($ticket));
    }

    #[Route('/{id}', name: 'soft_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function softDelete(Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($ticket->getAssignedTo() !== $user) {
            return $this->json(
                ['message' => "You cannot delete this ticket."],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }
        
        if ($ticket->getDeletedAt() !== null) {
            return $this->json(
                ['message' => 'This ticket has already been deleted.'],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        $ticket->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/claim', name: 'claim', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function claim(Ticket $ticket): JsonResponse
    {
        if ($ticket->getAssignedTo() !== null) {
            return $this->json(
                ['message' => 'Ticket with id '.$ticket->getId().' has already been claimed.'],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }
        
        if ($ticket->getDeletedAt() !== null) {
            return $this->json(
                ['message' => 'Ticket with id '.$ticket->getId().' is deleted.'],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        /** @var User $user */
        $user = $this->getUser();

        $ticket->setAssignedTo($user);

        $this->entityManager->flush();

        return $this->json(TicketMapper::toResponseDto($ticket));
    }
}
