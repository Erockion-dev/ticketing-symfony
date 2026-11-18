<?php

namespace App\Controller\BackOffice\Operator;

use App\Repository\UserRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/operator/users', name: 'app_dashboard_operator_users_')]
#[IsGranted('ROLE_OPERATOR')]
final class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    #[Route(name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        PaginatorInterface $paginator
    ): Response
    {
        $pagination = $paginator->paginate(
            $this->userRepository->findUsers(),
            $request->query->getInt('page', 1),
            25,
        );

        return $this->render('backoffice/operator/user/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }
}
