<?php

namespace App\Tests\Functional\Controller\Api;

use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

final class UserTicketControllerTest extends WebTestCase
{
    private const JWT_TEST_SECRET = 'ticket-controller-functional-tests-secret-32-bytes';

    public function testCreateTicket(): void
    {
        $client = $this->createTestClient();
        $user = $this->createUser();
        $this->persist($user);

        $this->requestAsUser($client, $user, 'POST', '/api/user/tickets/new', [
            'title' => 'Functional test ticket',
            'description' => 'A ticket created through the API.',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Functional test ticket', $data['title']);
        self::assertSame('open', $data['status']);
        self::assertSame('normal', $data['priority']);
        self::assertSame($user->getId(), $data['requester']['id']);
        self::assertSame($user->getId(), $data['createdBy']['id']);
    }

    public function testCreateChildTicketWithOwnParent(): void
    {
        $client = $this->createTestClient();
        $user = $this->createUser();
        $this->persist($user);
        $parent = $this->createTicket($user, 'Parent ticket');
        $this->persist($parent);

        $this->requestAsUser($client, $user, 'POST', '/api/user/tickets/new', [
            'title' => 'Child ticket',
            'description' => 'A child ticket created through the API.',
            'parentTicketId' => $parent->getId(),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($parent->getId(), $data['parentTicket']['id']);
    }

    public function testListOnlyContainsTheRequestingUsersTicketsAndUsesRequestedLimit(): void
    {
        $client = $this->createTestClient();
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $this->persist($user, $otherUser);
        $this->persist(
            $this->createTicket($user, 'My ticket'),
            $this->createTicket($user, 'My other ticket'),
            $this->createTicket($otherUser, 'Other ticket'),
        );

        $this->requestAsUser($client, $user, 'GET', '/api/user/tickets?limit=1');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['pagination']['limit']);
        self::assertSame(2, $data['pagination']['totalItems']);
        self::assertCount(1, $data['data']);
        self::assertContains(
            $data['data'][0]['title'],
            ['My ticket', 'My other ticket'],
        );
    }

    public function testShowAllowsTheRequesterAndForbidsAnotherUsersTicket(): void
    {
        $client = $this->createTestClient();
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $this->persist($user, $otherUser);
        $ownTicket = $this->createTicket($user, 'My ticket');
        $otherTicket = $this->createTicket($otherUser, 'Other ticket');
        $this->persist($ownTicket, $otherTicket);

        $this->requestAsUser($client, $user, 'GET', '/api/user/tickets/'.$ownTicket->getId());

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('My ticket', $data['title']);

        $this->requestAsUser($client, $user, 'GET', '/api/user/tickets/'.$otherTicket->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(
            ['message' => 'You are not allowed to show this ticket.'],
            json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testEditUpdatesOnlyTheRequestingUsersTicket(): void
    {
        $client = $this->createTestClient();
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $this->persist($user, $otherUser);
        $ticket = $this->createTicket($user, 'Original title');
        $otherTicket = $this->createTicket($otherUser, 'Private title');
        $this->persist($ticket, $otherTicket);

        $this->requestAsUser($client, $user, 'PATCH', '/api/user/tickets/'.$ticket->getId(), [
            'title' => 'Updated title',
            'priority' => 'high',
            'parentTicketId' => null,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Updated title', $data['title']);
        self::assertSame('high', $data['priority']);

        $this->requestAsUser($client, $user, 'PATCH', '/api/user/tickets/'.$otherTicket->getId(), [
            'title' => 'Unauthorized update',
            'parentTicketId' => null,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('Private title', $otherTicket->getTitle());
    }

    private function createUser(): User
    {
        return (new User())
            ->setEmail('ticket-test-'.bin2hex(random_bytes(6)).'@example.com')
            ->setFirstname('Test')
            ->setLastname('Requester')
            ->setPassword('not-used')
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true);
    }

    private function createTicket(User $requester, string $title): Ticket
    {
        return (new Ticket())
            ->setTitle($title)
            ->setDescription('Functional test description.')
            ->setRequester($requester)
            ->setCreatedBy($requester);
    }

    private function persist(object ...$entities): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($entities as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
    }

    private function createTestClient(): KernelBrowser
    {
        putenv('JWT_SECRET='.self::JWT_TEST_SECRET);
        $_ENV['JWT_SECRET'] = self::JWT_TEST_SECRET;
        $_SERVER['JWT_SECRET'] = self::JWT_TEST_SECRET;

        return static::createClient();
    }

    private function requestAsUser(
        KernelBrowser $client,
        User $user,
        string $method,
        string $uri,
        ?array $payload = null,
    ): void {
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $client->request(
            $method,
            $uri,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
