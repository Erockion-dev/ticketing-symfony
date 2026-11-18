<?php

namespace App\Controller\BackOffice\User;

use App\Entity\Ticket;
use App\Entity\TicketComment;
use App\Entity\User;
use App\Form\TicketCommentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/dashboard/user/ticket-comments/{id}', name: 'app_dashboard_user_ticket_comments_')]
final class TicketCommentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route(name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, Ticket $ticket): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($ticket->getRequester() !== $user) {
            $this->addFlash('warning', 'You are not authorized to add a comment.');
            return $this->redirectToRoute('app_dashboard_user_tickets_index');
        }

        $ticketComment = new TicketComment();

        $ticketComment->setTicket($ticket);

        $form = $this->createForm(TicketCommentType::class, $ticketComment);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $ticketComment->setCreatedBy($user);
            $ticketComment->setTicket($ticket);
            
            $this->entityManager->persist($ticketComment);
            $this->entityManager->flush();

            $this->addFlash('success', 'Ticket comment created.');

            return $this->redirectToRoute('app_dashboard_user_tickets_show', [
                'id' => $ticket->getId(),
            ]);
        }
        
        return $this->render('backoffice/user/ticket-comment/new.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }
}
