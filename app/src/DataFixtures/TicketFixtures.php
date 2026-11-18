<?php

namespace App\DataFixtures;

use App\Entity\Ticket;
use App\Entity\TicketComment;
use App\Entity\User;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class TicketFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $staff = [];
        for ($i = 1; $i <= UserFixtures::NBR_ADMIN; $i++) {
            $staff[] = $this->getReference('ope' . $i . UserFixtures::EMAIL, User::class);
        }

        $operators = [];
        for ($i = 1; $i <= UserFixtures::NBR_OPE; $i++) {
            $operator = $this->getReference('ope' . $i . UserFixtures::EMAIL, User::class);
            $staff[] = $operator;
            $operators[] = $operator;
        }

        $users = [];
        for ($i = 1; $i <= UserFixtures::NBR_USER; $i++) {
            if ($i % 8 !== 0) {
                $users[] = $this->getReference('user' . $i . UserFixtures::EMAIL, User::class);
            }
        }

        foreach ($users as $user) {

            $nbrticket = random_int(0, 5);
            for ($i = 1; $i <= $nbrticket; $i++) {
                $createdBy = random_int(1, 100) <= 5
                    ? $staff[array_rand($staff)]
                    : $user
                ;

                $ticket = $this->createTicket($user, $createdBy);

                $this->setAssignedTo($ticket, $operators);

                $manager->persist($ticket);

                $this->addComment($manager, $user, $ticket);

                if (random_int(0, 1) === 1) {
                    $loop = random_int(0, 3);
                    for ($y = 1; $y <= $loop; $y++) {
                        $childTicket = $this->createChildTicket($user, $ticket);

                        $this->setAssignedTo($childTicket, $operators);

                        $manager->persist($childTicket);
                        
                        $this->addComment($manager, $user, $childTicket);
                    }
                }
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    private function createTicket(User $user, ?User $createdBy = null)
    {
        $statuses = TicketStatus::cases();
        $priorities = TicketPriority::cases();

        return (new Ticket())
            ->setTitle('title ' . $user->getId())
            ->setDescription("Ticket of test " . $user->getId())
            ->setCreatedBy($createdBy ?? $user)
            ->setRequester($user)
            ->setStatus($statuses[array_rand($statuses)])
            ->setPriority($priorities[array_rand($priorities)])
        ;
    }

    private function createChildTicket(User $user, Ticket $parentTicket)
    {
        $ticket = $this->createTicket($user);
        return $ticket
            ->setDescription('Child ticket, '. $ticket->getDescription())
            ->setParentTicket($parentTicket);
        ;
    }

    private function addComment(ObjectManager $manager, User $user, Ticket $ticket)
    {
        // if (random_int(0, 1) === 1) {
        if (random_int(1, 100) <= 70) {
            $loop = random_int(0, 3);
            for ($i = 1; $i <= $loop; $i++) {
                $comment = $this->createComment($user, $ticket, $i);
                $manager->persist($comment);
            }
        }
    }

    private function createComment(User $user, Ticket $ticket, int $number)
    {
        return (new TicketComment())
            ->setContent('Comment ' . $number . ' of ticket.')
                ->setTicket($ticket)
                ->setCreatedBy($user)
        ;
    }

    private function setAssignedTo(Ticket $ticket, Array $operators)
    {
        $ticket->setAssignedTo($ticket->getStatus() !== TicketStatus::Open || random_int(1, 100) <= 20
            ? $operators[array_rand($operators)]
            : null
        );
    }
}
