<?php

namespace App\Tests\Functional\Controller\Api;

use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class TicketPaginationTest extends WebTestCase
{
    private const JWT_TEST_SECRET = 'ticket-pagination-functional-tests-secret';

    public function testAdminListUsesRequestedLimit(): void
    {
        $client = $this->createTestClient();
        $admin = $this->createUser('ROLE_ADMIN');
        $this->persist($admin);
        $this->persist(
            $this->createTicket($admin, 'First ticket'),
            $this->createTicket($admin, 'Second ticket'),
        );

        $this->requestAsUser($client, $admin, '/api/admin/tickets?limit=1');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['pagination']['limit']);
        self::assertCount(1, $data['data']);
    }

    public function testOperatorListUsesRequestedLimit(): void
    {
        $client = $this->createTestClient();
        $operator = $this->createUser('ROLE_OPERATOR');
        $this->persist($operator);
        $this->persist(
            $this->createTicket($operator, 'First ticket'),
            $this->createTicket($operator, 'Second ticket'),
        );

        $this->requestAsUser($client, $operator, '/api/operator/tickets?limit=1');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['pagination']['limit']);
        self::assertCount(1, $data['data']);
    }

    private function createUser(string $role): User
    {
        return (new User())
            ->setEmail('ticket-pagination-'.bin2hex(random_bytes(6)).'@example.com')
            ->setFirstname('Test')
            ->setLastname('User')
            ->setPassword('not-used')
            ->setRoles([$role])
            ->setIsActive(true);
    }

    private function createTicket(User $user, string $title): Ticket
    {
        return (new Ticket())
            ->setTitle($title)
            ->setDescription('Functional test description.')
            ->setRequester($user)
            ->setCreatedBy($user);
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

    private function requestAsUser(KernelBrowser $client, User $user, string $uri): void
    {
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $client->request('GET', $uri, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }
}
