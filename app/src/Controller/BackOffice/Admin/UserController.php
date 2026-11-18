<?php

namespace App\Controller\BackOffice\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/admin/users', name: 'app_dashboard_admin_users_')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    ) {}

    #[Route(name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        PaginatorInterface $paginator
    ): Response
    {
        $pagination = $paginator->paginate(
            $this->userRepository->createListQueryBuilder(),
            $request->query->getInt('page', 1),
            25,
        );
        return $this->render('backoffice/admin/user/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'User created.');

            return $this->redirectToRoute('app_dashboard_admin_users_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('backoffice/admin/user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('backoffice/admin/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(UserType::class, $user, ['password_required' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            if ($plain) {
                $user->setPassword($hasher->hashPassword($user, $plain));
            }
            $this->entityManager->flush();

            $this->addFlash('success', 'User updated.');

            return $this->redirectToRoute('app_dashboard_admin_users_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('backoffice/admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/soft-delete', name: 'soft_delete', methods: ['POST'])]
    public function softDelete(Request $request, User $user): Response
    {
        if (
            $user->getDeletedAt() === null
            && $this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))
        ) {
            $user->setDeletedAt(new \DateTimeImmutable());
            $user->setIsActive(false);
            $this->entityManager->flush();

            $this->addFlash('success', 'User deleted.');
        }

        return $this->redirectToRoute('app_dashboard_admin_users_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/restore', name: 'restore', methods: ['POST'])]
    public function restore(Request $request, User $user): Response
    {
        if (
            $user->getDeletedAt() !== null
            &&
            $this->isCsrfTokenValid('restore'.$user->getId(), $request->getPayload()->getString('_token'))
        ) {
            $user->setDeletedAt(null);
            $this->entityManager->flush();

            $this->addFlash('success', 'User restored.');
        } else {
            $this->addFlash('success', 'User not restored.');
        }

        return $this->redirectToRoute('app_dashboard_admin_users_index');
    }
}
