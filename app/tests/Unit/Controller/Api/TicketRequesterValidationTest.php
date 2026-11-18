<?php

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\Admin\TicketController as AdminTicketController;
use App\Controller\Api\Operator\TicketController as OperatorTicketController;
use App\Dto\Api\Ticket\CreateOperatorTicketDto;
use App\Entity\User;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\Ticket\ParentTicketValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class TicketRequesterValidationTest extends TestCase
{
    public function testAdminRejectsMissingInactiveOrDeletedRequester(): void
    {
        $inactiveRequester = $this->createUser('inactive@example.com')->setIsActive(false);
        $deletedRequester = $this->createUser('deleted@example.com')
            ->setDeletedAt(new \DateTimeImmutable());

        foreach ([null, $inactiveRequester, $deletedRequester] as $requester) {
            $response = $this->createAdminController($requester)->new($this->createDto());

            self::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
            self::assertSame(
                ['message' => 'Requester is not valid.'],
                json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
            );
        }
    }

    public function testOperatorRejectsMissingInactiveOrDeletedRequester(): void
    {
        $inactiveRequester = $this->createUser('inactive@example.com')->setIsActive(false);
        $deletedRequester = $this->createUser('deleted@example.com')
            ->setDeletedAt(new \DateTimeImmutable());

        foreach ([null, $inactiveRequester, $deletedRequester] as $requester) {
            $response = $this->createOperatorController($requester)->new($this->createDto());

            self::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
            self::assertSame(
                ['message' => 'Requester is not valid.'],
                json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
            );
        }
    }

    private function createAdminController(?User $requester): AdminTicketController
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())
            ->method('find')
            ->with(42)
            ->willReturn($requester);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $controller = new AdminTicketController(
            $entityManager,
            $this->createStub(TicketRepository::class),
            $userRepository,
            new ParentTicketValidator(),
        );
        $controller->setContainer($this->createSecurityContainer(
            $this->createUser('admin@example.com')->setRoles(['ROLE_ADMIN']),
        ));

        return $controller;
    }

    private function createOperatorController(?User $requester): OperatorTicketController
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())
            ->method('find')
            ->with(42)
            ->willReturn($requester);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $controller = new OperatorTicketController(
            $entityManager,
            $this->createStub(TicketRepository::class),
            $userRepository,
            new ParentTicketValidator(),
        );
        $controller->setContainer($this->createSecurityContainer(
            $this->createUser('operator@example.com')->setRoles(['ROLE_OPERATOR']),
        ));

        return $controller;
    }

    private function createSecurityContainer(User $user): Container
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(
            new UsernamePasswordToken($user, 'main', $user->getRoles()),
        );

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);

        return $container;
    }

    private function createDto(): CreateOperatorTicketDto
    {
        return new CreateOperatorTicketDto(
            title: 'Ticket title',
            description: 'Ticket description',
            requester: 42,
        );
    }

    private function createUser(string $email): User
    {
        return (new User())
            ->setEmail($email)
            ->setFirstname('Test')
            ->setLastname('User')
            ->setRoles(['ROLE_USER']);
    }
}
