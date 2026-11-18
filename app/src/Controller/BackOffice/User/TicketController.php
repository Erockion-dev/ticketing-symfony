<?php

namespace App\Controller\BackOffice\User;

use App\Entity\Ticket;
use App\Entity\User;
use App\Form\TicketUserCreateType;
use App\Form\TicketUserUpdateType;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/dashboard/user/tickets', name: 'app_dashboard_user_tickets_')]
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
            $this->ticketRepository->findBy(
                ['requester' => $user->getId()],
                ['createdAt' => 'DESC']
            ),
            $request->query->getInt('page', 1),
            25,
        );
        
        return $this->render('backoffice/user/ticket/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Ticket $ticket): Response
    {
        if ($ticket->getRequester() !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard_user_tickets_index');
        }

        return $this->render('backoffice/user/ticket/show.html.twig', [
            'ticket' => $ticket,
        ]);
    }

    #[Route('/new/{parent?}', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, ?Ticket $parent = null): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($parent) {
            if (
                $parent->getRequester() !== $user
                || $parent->getParentTicket() !== null
                || $parent->getDeletedAt() !== null
            ) {
                $this->addFlash('warning', 'You are not allowed to use this ticket as a parent.');
                return $this->redirectToRoute('app_dashboard_user_tickets_index');
            }
        }

        $ticket = new Ticket();
        $ticket->setRequester($user);
        $ticket->setCreatedBy($user);

        if ($parent) {
            $ticket->setParentTicket($parent);
        }

        $form = $this->createForm(TicketUserCreateType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($ticket);
            $this->entityManager->flush();

            $this->addFlash('success', 'Ticket created.');

            return $this->redirectToRoute('app_dashboard_user_tickets_show', [
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
    ): Response {

        if ($ticket->getRequester() !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard_user_tickets_index');
        }

        $form = $this->createForm(TicketUserUpdateType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Ticket updated.');

            return $this->redirectToRoute('app_dashboard_user_tickets_show', [
                'id' => $ticket->getId(),
            ]);
        }

        return $this->render('backoffice/user/ticket/edit.html.twig', [
            'form' => $form,
            'ticket' => $ticket,
        ]);
    }
}
