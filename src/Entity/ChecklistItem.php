<?php

namespace App\Entity;

use App\Repository\ChecklistItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChecklistItemRepository::class)]
class ChecklistItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'checklistItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    #[ORM\Column(length: 255)]
    private string $text;

    #[ORM\Column]
    private int $position = 0;

    /** Prazno = zajednička stavka */
    #[ORM\ManyToOne]
    private ?User $assignedTo = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDone = false;

    #[ORM\ManyToOne]
    private ?User $doneBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

    public function __construct(Task $task, string $text, int $position, ?User $assignedTo = null)
    {
        $this->task = $task;
        $this->text = $text;
        $this->position = $position;
        $this->assignedTo = $assignedTo;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

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

    public function isDone(): bool
    {
        return $this->isDone;
    }

    public function getDoneBy(): ?User
    {
        return $this->doneBy;
    }

    public function getDoneAt(): ?\DateTimeImmutable
    {
        return $this->doneAt;
    }

    public function markDone(User $by): static
    {
        $this->isDone = true;
        $this->doneBy = $by;
        $this->doneAt = new \DateTimeImmutable();

        return $this;
    }

    public function markUndone(): static
    {
        $this->isDone = false;
        $this->doneBy = null;
        $this->doneAt = null;

        return $this;
    }
}
