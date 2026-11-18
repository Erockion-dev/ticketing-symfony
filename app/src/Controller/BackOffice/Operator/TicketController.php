<?php

namespace App\Controller\BackOffice\Operator;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;
use App\Form\TicketUserCreateType;
use App\Form\TicketOperatorUpdateType;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_OPERATOR')]
#[Route('/dashboard/operator/tickets', name: 'app_dashboard_operator_tickets_')]
final class TicketController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TicketRepository $ticketRepository,
    ) {}

    #[Route(name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        PaginatorInterface $paginator
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $pagination = $paginator->paginate(
            $this->ticketRepository->createForOperatorQueryBuilder($user),
            $request->query->getInt('page', 1),
            25,
        );
        
        return $this->render('backoffice/operator/ticket/index.html.twig', [
            'pagination' => $pagination
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Ticket $ticket): Response
    {
        if (
            $ticket->getAssignedTo() !== $this->getUser()
            && $ticket->getAssignedTo() !== null
        ) {
            return $this->redirectToRoute('app_dashboard_operator_tickets_index');
        }

        return $this->render('backoffice/operator/ticket/show.html.twig', [
            'ticket' => $ticket,
        ]);
    }

    #[Route('/new/for-requester/{id}', name: 'new_for_requester', methods: ['GET', 'POST'], requirements: ['requester' => '\d+'])]
    public function new(Request $request, User $requester ): Response {

        /** @var User $user */
        $user = $this->getUser();

        if (
            $requester->getDeletedAt() !== null
            || !$requester->isActive()
            || $requester === $user
        ) {
            return $this->redirectToRoute('app_dashboard_operator_tickets_show');
        }

        $ticket = new Ticket();
        $ticket->setRequester($requester);
        $ticket->setCreatedBy($user);

        $form = $this->createForm(TicketUserCreateType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($ticket);
            $this->entityManager->flush();

            $this->addFlash('success', 'Ticket created.');

            return $this->redirectToRoute('app_dashboard_operator_tickets_show', [
                'id' => $ticket->getId(),
            ]);
        }

        return $this->render('backoffice/user/ticket/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(
        Ticket $ticket,
        Request $request,
    ): Response
    {

        if ($ticket->getAssignedTo() !== $this->getUser()) {
            $this->addFlash('warning', 'You can t updated this ticket.');

            return $this->redirectToRoute('app_dashboard_operator_tickets_index');
        }

        $form = $this->createForm(TicketOperatorUpdateType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Ticket updated.');

            return $this->redirectToRoute('app_dashboard_operator_tickets_show', [
                'id' => $ticket->getId(),
            ]);
        }

        return $this->render('backoffice/operator/ticket/edit.html.twig', [
            'form' => $form,
            'ticket' => $ticket,
        ]);
    }

    #[Route('/{id}/soft-delete', name: 'soft_delete', methods: ['POST'])]
    public function softDelete(Request $request, Ticket $ticket): Response
    {
        if ($this->isCsrfTokenValid('admin_soft_delete'.$ticket->getId(), $request->getPayload()->getString('_token'))) {
            $ticket->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        $this->addFlash('success', 'Ticket deleted.');

        return $this->redirectToRoute('app_dashboard_admin_tickets_show', [
            'id' => $ticket->getId(),
        ]);
    }

    #[Route('/{id}/assign-to-me', name: 'assign_to_me', methods: ['POST'])]
    public function assignToMe(Request $request, Ticket $ticket): Response
    {
        if (
            $ticket->getAssignedTo() !== null
            || $ticket->getStatus() == TicketStatus::Closed
            || $ticket->getDeletedAt() !== null
        ) {
            $this->addFlash('success', 'Ticket not assigned.');

            return $this->redirectToRoute('app_dashboard_operator_tickets_index');
        }

        if ($this->isCsrfTokenValid('assign_to_me'.$ticket->getId(), $request->getPayload()->getString('_token'))) {
            $ticket->setAssignedTo($this->getUser());
            $this->entityManager->flush();
        }

        $this->addFlash('success', 'Ticket assigned.');

        return $this->redirectToRoute('app_dashboard_operator_tickets_index', [
            'id' => $ticket->getId(),
        ]);
    }
}
