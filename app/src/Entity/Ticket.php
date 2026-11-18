<?php

namespace App\Entity;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Repository\TicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 20, enumType: TicketStatus::class)]
    private TicketStatus $status = TicketStatus::Open;

    #[ORM\Column(length: 20, enumType: TicketPriority::class)]
    private TicketPriority $priority = TicketPriority::Normal;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $requester;

    #[ORM\ManyToOne]
    private ?User $assignedTo = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'relatedTickets')]
    private ?self $parentTicket = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parentTicket')]
    private Collection $relatedTickets;

    /** @var Collection<int, TicketComment> */
    #[ORM\OneToMany(targetEntity: TicketComment::class, mappedBy: 'ticket')]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $ticketComments;

    public function __construct()
    {
        $this->relatedTickets = new ArrayCollection();
        $this->ticketComments = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): TicketStatus
    {
        return $this->status;
    }

    public function setStatus(TicketStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPriority(): TicketPriority
    {
        return $this->priority;
    }

    public function setPriority(TicketPriority $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getRequester(): User
    {
        return $this->requester;
    }

    public function setRequester(User $requester): static
    {
        $this->requester = $requester;

        return $this;
    }

    public function getAssignedTo(): ?User
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(?User $assignedTo): static
    {
        $this->assignedTo = $assignedTo;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getParentTicket(): ?self
    {
        return $this->parentTicket;
    }

    public function setParentTicket(?self $parentTicket): static
    {
        $this->parentTicket = $parentTicket;

        return $this;
    }

    /** @return Collection<int, self> */
    public function getRelatedTickets(): Collection
    {
        return $this->relatedTickets;
    }

    #[Assert\Callback]
    public function validateParentTicket(ExecutionContextInterface $context): void
    {
        if ($this->parentTicket === null) {
            return;
        }

        $message = match (true) {
            $this->parentTicket === $this => 'A ticket cannot be its own parent.',
            $this->parentTicket->getParentTicket() !== null => 'The parent ticket cannot itself be a child ticket.',
            !$this->relatedTickets->isEmpty() => 'A ticket that has child tickets cannot become a child ticket.',
            default => null,
        };

        if ($message !== null) {
            $context->buildViolation($message)
                ->atPath('parentTicket')
                ->addViolation();
        }
    }

    public function addTicketComment(TicketComment $comment): static
    {
        if (!$this->ticketComments->contains($comment)) {
            $this->ticketComments->add($comment);
            $comment->setTicket($this);
        }

        return $this;
    }

    /** @return Collection<int, TicketComment> */
    public function getTicketComments(): Collection
    {
        return $this->ticketComments;
    }
}
