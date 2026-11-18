<?php

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\User\TicketController;
use App\Dto\Api\Ticket\CreateUserTicketDto;
use App\Dto\Api\Ticket\UpdateUserTicketDto;
use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\TicketRepository;
use App\Service\Ticket\ParentTicketValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class UserTicketControllerTest extends TestCase
{
    public function testShowReturnsForbiddenForAnotherUsersTicket(): void
    {
        $user = $this->createUser('user@example.com');
        $ticket = (new Ticket())
            ->setTitle('Private ticket')
            ->setDescription('Private description')
            ->setRequester($this->createUser('another-user@example.com'));

        $controller = $this->createController($user);

        $response = $controller->show($ticket);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(
            ['message' => 'You are not allowed to show this ticket.'],
            json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testCreateRejectsAnotherUsersParentTicket(): void
    {
        $user = $this->createUser('user@example.com');
        $parent = (new Ticket())
            ->setTitle('Another user ticket')
            ->setDescription('Description')
            ->setRequester($this->createUser('another-user@example.com'));
        $repository = $this->createMock(TicketRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(12)
            ->willReturn($parent);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $controller = $this->createController($user, $repository, $entityManager);

        $response = $controller->new(new CreateUserTicketDto(
            title: 'Child ticket',
            description: 'Description',
            parentTicketId: 12,
        ));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(
            ['message' => 'You are not allowed to use this parent ticket.'],
            json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testCreateRejectsChildTicketAsParent(): void
    {
        $user = $this->createUser('user@example.com');
        $parent = (new Ticket())
            ->setTitle('Parent ticket')
            ->setDescription('Description')
            ->setRequester($user)
            ->setParentTicket(new Ticket());
        $repository = $this->createMock(TicketRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(12)
            ->willReturn($parent);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $controller = $this->createController($user, $repository, $entityManager);

        $response = $controller->new(new CreateUserTicketDto(
            title: 'Child ticket',
            description: 'Description',
            parentTicketId: 12,
        ));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            ['message' => 'A child ticket cannot be used as a parent ticket.'],
            json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testCreateRejectsMissingParentTicket(): void
    {
        $user = $this->createUser('user@example.com');
        $repository = $this->createMock(TicketRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(12)
            ->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $controller = $this->createController($user, $repository, $entityManager);

        $response = $controller->new(new CreateUserTicketDto(
            title: 'Child ticket',
            description: 'Description',
            parentTicketId: 12,
        ));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Parent ticket not found.'],
            json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testEditReturnsNotFoundForAnotherUsersTicket(): void
    {
        $user = $this->createUser('user@example.com');
        $anotherUser = $this->createUser('another-user@example.com');
        $ticket = (new Ticket())
            ->setTitle('Private ticket')
            ->setDescription('Description')
            ->setRequester($anotherUser)
            ->setCreatedBy($anotherUser);
        (new \ReflectionProperty(Ticket::class, 'id'))->setValue($ticket, 42);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $controller = $this->createController($user, entityManager: $entityManager);

        $response = $controller->edit(
            Request::create('/api/user/tickets/1', 'PATCH', content: '{"title":"Updated","parentTicketId":null}'),
            $ticket,
            new UpdateUserTicketDto(title: 'Updated', priority: null, parentTicketId: null),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Ticket with id 42 is not found.'],
            json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testEditRejectsChildTicketAsParent(): void
    {
        $user = $this->createUser('user@example.com');
        $ticket = (new Ticket())
            ->setTitle('Ticket')
            ->setDescription('Description')
            ->setRequester($user)
            ->setCreatedBy($user);
        $childParent = (new Ticket())
            ->setTitle('Child')
            ->setDescription('Description')
            ->setParentTicket(new Ticket());
        $repository = $this->createMock(TicketRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(12)
            ->willReturn($childParent);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $controller = $this->createController($user, $repository, $entityManager);

        $response = $controller->edit(
            Request::create('/api/user/tickets/1', 'PATCH', content: '{"parentTicketId":12}'),
            $ticket,
            new UpdateUserTicketDto(title: null, priority: null, parentTicketId: 12),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            ['message' => 'A child ticket cannot be used as a parent.'],
            json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    private function createController(
        User $user,
        ?TicketRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
    ): TicketController {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(
            new UsernamePasswordToken($user, 'main', $user->getRoles()),
        );

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);

        $controller = new TicketController(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $repository ?? $this->createStub(TicketRepository::class),
            new ParentTicketValidator(),
        );
        $controller->setContainer($container);

        return $controller;
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
