<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixtures extends Fixture
{
    public const PASSWORD = '123_';
    public const EMAIL = '@example.com';
    public const NBR_ADMIN = 3;
    public const NBR_OPE = 8;
    public const NBR_USER = 100;

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $accounts = [
            ['admin', self::NBR_ADMIN, [Role::Admin->value]],
            ['ope', self::NBR_OPE, [Role::Operator->value]],
            ['user', self::NBR_USER, [Role::User->value]],
        ];

        foreach ($accounts as [$firstname, $count, $roles]) {
            for ($i = 1; $i <= $count; $i++) {
                $email = $firstname . $i . self::EMAIL;

                $user = new User();
                $user->setEmail($email);
                $user->setFirstname($firstname . $i);
                $user->setLastname($firstname . $i);
                $user->setRoles($roles);
                $user->setIsActive(true);
                $user->setPassword(
                    $this->hasher->hashPassword($user, self::PASSWORD . $firstname)
                );

                $manager->persist($user);
                $this->addReference($email, $user);
            }
        }

        $manager->flush();
    }
}
