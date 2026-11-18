<?php

namespace App\Repository;

use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC');
    }

    public function createForRequesterQueryBuilder(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->where('t.requester = :requester')
            ->setParameter('requester', $user)
            ->orderBy('t.createdAt', 'DESC');
    }

    public function createForOperatorQueryBuilder(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->where('t.assignedTo = :user')
            ->orWhere('t.assignedTo IS NULL')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC');
    }

    public function findOneAccessibleTicketForOperator(int $ticketId, User $user): ?Ticket
    {
        return $this->createQueryBuilder('t')
            ->where('t.id = :id')
            ->andWhere('t.assignedTo = :user OR t.assignedTo IS NULL')
            ->setParameter('id', $ticketId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
