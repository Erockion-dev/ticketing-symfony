<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Ticket;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TicketTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testTicketWithAParentIsValid(): void
    {
        $child = (new Ticket())->setParentTicket(new Ticket());

        $this->assertCount(0, $this->validator->validate($child));
    }

    public function testTicketCannotBeItsOwnParent(): void
    {
        $ticket = new Ticket();
        $ticket->setParentTicket($ticket);

        $this->assertCount(1, $this->validator->validate($ticket));
    }

    public function testParentCannotBeAChildItself(): void
    {
        $grandParent = new Ticket();
        $parent = (new Ticket())->setParentTicket($grandParent);
        $child = (new Ticket())->setParentTicket($parent);

        $this->assertCount(1, $this->validator->validate($child));
    }

    public function testTicketWithChildrenCannotBecomeAChild(): void
    {
        $ticket = new Ticket();
        $ticket->getRelatedTickets()->add(new Ticket());
        $ticket->setParentTicket(new Ticket());

        $this->assertCount(1, $this->validator->validate($ticket));
    }
}
