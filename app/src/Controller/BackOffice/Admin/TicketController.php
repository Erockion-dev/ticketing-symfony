<?php

namespace App\Controller\BackOffice\Admin;

use App\Entity\Ticket;
use App\Entity\User;
use App\Form\TicketUserCreateType;
use App\Form\TicketOperatorUpdateType;
use App\Repository\TicketRepository;
use App\Service\Ticket\TicketStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/dashboard/admin/tickets', name: 'app_dashboard_admin_tickets_')]
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
            $this->ticketRepository->findBy([], ['createdAt' => 'DESC', 'id' => 'ASC']),
            $request->query->getInt('page', 1),
            25,
        );
        
        return $this->render('backoffice/admin/ticket/index.html.twig', [
            'pagination' => $pagination
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Ticket $ticket): Response
    {
        return $this->render('backoffice/admin/ticket/show.html.twig', [
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
        )
        {
            return $this->redirectToRoute('app_dashboard_admin_tickets_show');
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

            return $this->redirectToRoute('app_dashboard_admin_tickets_show', [
                'id' => $ticket->getId(),
            ]);
        }

        return $this->render('backoffice/admin/ticket/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(
        Ticket $ticket,
        Request $request,
        TicketStatusManager $ticketStatusManager
    ): Response
    {

        if ($ticket->getRequester() === $this->getUser()) {
            return $this->redirectToRoute('app_dashboard_admin_tickets_index');
        }

        $form = $this->createForm(
            TicketOperatorUpdateType::class,
            $ticket,
            ['status_choice' => $ticketStatusManager->getPossibility($ticket)]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $action = $form->get('status')->getData();
            if ($action !== null && !$ticketStatusManager->changeStatus($ticket, $action)) {
                $this->addFlash('danger', 'Ticket status is not updated.');

                return $this->redirectToRoute('app_dashboard_admin_tickets_show', [
                    'id' => $ticket->getId(),
                ]); 
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Ticket updated.');

            return $this->redirectToRoute('app_dashboard_admin_tickets_show', [
                'id' => $ticket->getId(),
            ]);
        }

        return $this->render('backoffice/admin/ticket/edit.html.twig', [
            'form' => $form,
            'ticket' => $ticket,
        ]);
    }

    #[Route('/{id}/soft-delete', name: 'soft_delete', methods: ['POST'])]
    public function softDelete(Request $request, Ticket $ticket): Response
    {
        if ($ticket->getDeletedAt() === null && $this->isCsrfTokenValid('admin_soft_delete'.$ticket->getId(), $request->getPayload()->getString('_token'))) {
            $ticket->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->addFlash('success', 'Ticket deleted.');
        }

        return $this->redirectToRoute('app_dashboard_admin_tickets_show', [
            'id' => $ticket->getId(),
        ]);
    }
}
