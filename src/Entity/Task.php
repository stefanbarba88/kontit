<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\AssigneeStatus;
use App\Enum\CompletionMode;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'IDX_TASK_STATUS_DUE', fields: ['status', 'dueDate'])]
class Task
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Unesite naslov zadatka.')]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Prazan klijent = interni zadatak */
    #[ORM\ManyToOne]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(length: 20, enumType: TaskPriority::class)]
    #[Assert\NotNull(message: 'Izaberite prioritet.')]
    private TaskPriority $priority = TaskPriority::NORMALAN;

    #[ORM\Column(length: 20, enumType: TaskStatus::class)]
    private TaskStatus $status = TaskStatus::NOVO;

    #[ORM\Column(length: 3, enumType: CompletionMode::class)]
    #[Assert\NotNull(message: 'Izaberite režim završetka.')]
    private CompletionMode $completionMode = CompletionMode::ALL;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    /** Ponavljajući zadatak: posao klijenta iz koga je nastao i period (npr. 2026-11, 2026-Q4, 2026, 2026-W45) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?ClientJob $clientJob = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $periodKey = null;

    /** "idPosla:period"; jedinstveno, pa komanda ne može da napravi dva zadatka za isti posao i period */
    #[ORM\Column(length: 40, nullable: true, unique: true)]
    private ?string $generationKey = null;

    #[ORM\Column(nullable: true)]
    private ?int $estimatedMinutes = null;

    #[ORM\Column]
    private bool $generatedAutomatically = false;

    #[ORM\ManyToOne]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    /** @var Collection<int, TaskAssignee> */
    #[ORM\OneToMany(targetEntity: TaskAssignee::class, mappedBy: 'task', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $assignees;

    /** @var Collection<int, ChecklistItem> */
    #[ORM\OneToMany(targetEntity: ChecklistItem::class, mappedBy: 'task', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $checklistItems;

    /** @var Collection<int, TaskComment> */
    #[ORM\OneToMany(targetEntity: TaskComment::class, mappedBy: 'task', cascade: ['remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $comments;

    public function __construct()
    {
        $this->assignees = new ArrayCollection();
        $this->checklistItems = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = '' === $description ? null : $description;

        return $this;
    }

    public function getClientJob(): ?ClientJob
    {
        return $this->clientJob;
    }

    public function getPeriodKey(): ?string
    {
        return $this->periodKey;
    }

    public function getEstimatedMinutes(): ?int
    {
        return $this->estimatedMinutes;
    }

    public function isGeneratedAutomatically(): bool
    {
        return $this->generatedAutomatically;
    }

    /**
     * Označava zadatak kao generisan iz posla klijenta za period.
     */
    public function markGenerated(ClientJob $job, string $periodKey, ?int $estimatedMinutes): static
    {
        $this->clientJob = $job;
        $this->periodKey = $periodKey;
        $this->generationKey = $job->getId().':'.$periodKey;
        $this->estimatedMinutes = $estimatedMinutes;
        $this->generatedAutomatically = true;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function setPriority(TaskPriority $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCompletionMode(): CompletionMode
    {
        return $this->completionMode;
    }

    public function setCompletionMode(CompletionMode $completionMode): static
    {
        $this->completionMode = $completionMode;

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): static
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    /**
     * @return Collection<int, TaskAssignee>
     */
    public function getAssignees(): Collection
    {
        return $this->assignees;
    }

    public function addAssignee(TaskAssignee $assignee): static
    {
        if (!$this->assignees->contains($assignee)) {
            $this->assignees->add($assignee);
        }

        return $this;
    }

    public function removeAssignee(TaskAssignee $assignee): static
    {
        $this->assignees->removeElement($assignee);

        return $this;
    }

    public function findAssignee(User $user): ?TaskAssignee
    {
        foreach ($this->assignees as $assignee) {
            if ($assignee->getUser() === $user) {
                return $assignee;
            }
        }

        return null;
    }

    public function hasAssignee(User $user): bool
    {
        return null !== $this->findAssignee($user);
    }

    /**
     * @return list<User>
     */
    public function getAssigneeUsers(): array
    {
        return array_values($this->assignees->map(static fn (TaskAssignee $a) => $a->getUser())->toArray());
    }

    /**
     * @return Collection<int, ChecklistItem>
     */
    public function getChecklistItems(): Collection
    {
        return $this->checklistItems;
    }

    public function addChecklistItem(ChecklistItem $item): static
    {
        if (!$this->checklistItems->contains($item)) {
            $this->checklistItems->add($item);
        }

        return $this;
    }

    public function removeChecklistItem(ChecklistItem $item): static
    {
        $this->checklistItems->removeElement($item);

        return $this;
    }

    /**
     * @return Collection<int, TaskComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function getChecklistTotal(): int
    {
        return $this->checklistItems->count();
    }

    public function getChecklistDone(): int
    {
        return $this->checklistItems->filter(static fn (ChecklistItem $i) => $i->isDone())->count();
    }

    public function getFinishedAssigneesCount(): int
    {
        return $this->assignees->filter(static fn (TaskAssignee $a) => AssigneeStatus::ZAVRSIO === $a->getStatus())->count();
    }

    public function isOverdue(): bool
    {
        return null !== $this->dueDate
            && $this->status->isOpen()
            && $this->dueDate < new \DateTimeImmutable('today');
    }
}
