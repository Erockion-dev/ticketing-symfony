<?php

namespace App\Tests\Functional;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Enum\Role;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait AuthenticatedClientTrait
{
    abstract protected static function createClient(array $options = [], array $server = []): KernelBrowser;

    abstract protected static function getContainer(): ContainerInterface;

    private function createClientLoggedInAs(string $email): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser($this->ensureTestUser($email));

        return $client;
    }

    protected function ensureTestUser(string $email): User
    {
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => $email]);
        if ($user !== null) {
            return $user;
        }

        $accountType = str_starts_with($email, 'admin') ? 'admin' : 'user';
        $user = (new User())
            ->setEmail($email)
            ->setFirstname('Test')
            ->setLastname('User')
            ->setRoles([
                $accountType === 'admin' ? Role::Admin->value : Role::User->value,
            ])
            ->setIsActive(true);
        $user->setPassword(static::getContainer()
            ->get(UserPasswordHasherInterface::class)
            ->hashPassword($user, UserFixtures::PASSWORD.$accountType));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
