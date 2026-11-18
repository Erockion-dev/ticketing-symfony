<?php

namespace App\Repository;

use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findAllPaginated(int $page, int $limit): array
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $users = $queryBuilder->getQuery()->getResult();

        $total = $this->count([]);

        return [
            'users' => $users,
            'total' => $total,
        ];
    }

    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->select(sprintf(
                'NEW %s(u, (SELECT COUNT(t.id) FROM %s t WHERE t.requester = u))',
                \App\Dto\BackOffice\User\UserListItemDto::class,
                Ticket::class,
            ))
            ->orderBy('u.id', 'DESC')
        ;
    }

    public function findUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :isActive')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('u.id IN (:ids)')
            ->setParameter('isActive', true)
            ->setParameter('ids', $this->findIdsByRole('ROLE_USER'))
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /** @return list<int> */
    private function findIdsByRole(string $role): array
    {
        return $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT id FROM "user" WHERE roles::text LIKE :role',
            ['role' => '%"'.$role.'"%'],
        );
    }
}
