<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\AssigneeStatus;
use App\Enum\CompletionMode;
use App\Enum\ReviewMode;
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
#[ORM\Index(name: 'IDX_TASK_CLIENT_STATUS', fields: ['client', 'status'])]
// matrica "Pregled poslova po klijentima" (SPEC 8r): zadaci jednog meseca po klijentu
#[ORM\Index(name: 'IDX_TASK_DUE_CLIENT', fields: ['dueDate', 'client'])]
// jedan zadatak po poslu klijenta i periodu (SPEC 8i.1); ručni zadaci iz posla imaju period_key = NULL, pa ih može biti više
#[ORM\UniqueConstraint(name: 'UNIQ_TASK_JOB_PERIOD', columns: ['client_job_id', 'period_key'])]
class Task
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Unesi naslov zadatka.')]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    /** Normalizovan naziv za pretragu bez kvačica i velikih slova (TextNormalizer); puni ga TaskTitleSearchListener pri svakom čuvanju */
    #[ORM\Column(length: 255, options: ['default' => ''])]
    private string $titleSearch = '';

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
    #[Assert\NotNull(message: 'Izaberi prioritet.')]
    private TaskPriority $priority = TaskPriority::NORMALAN;

    #[ORM\Column(length: 20, enumType: TaskStatus::class)]
    private TaskStatus $status = TaskStatus::NOVO;

    #[ORM\Column(length: 3, enumType: CompletionMode::class)]
    #[Assert\NotNull(message: 'Izaberi režim završetka.')]
    private CompletionMode $completionMode = CompletionMode::ALL;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    /** Posao klijenta iz koga je zadatak nastao, i period ako ga je napravio sistem (npr. 2026-11, 2026-Q4, 2026, 2026-W45) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?ClientJob $clientJob = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $periodKey = null;

    #[ORM\Column(nullable: true)]
    private ?int $estimatedMinutes = null;

    #[ORM\Column]
    private bool $generatedAutomatically = false;

    /** Rok je ručno promenjen samo za ovaj zadatak: ni generator ni izmena rasporeda (posla, klijenta) ga ne preračunavaju (SPEC 8g.5) */
    #[ORM\Column(options: ['default' => false])]
    private bool $dueDateManuallySet = false;

    /** Generisan zadatak koji je dobila zamena jer je glavni radnik bio na odsustvu ("PREUZETO", SPEC 8i.1) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $takenOverFrom = null;

    #[ORM\ManyToOne]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    /** Način provere (AUTO = po poziciji učesnika, NONE = bez provere, SPECIAL = posebna potvrda). Vidi TaskReviewPolicy */
    #[ORM\Column(length: 10, enumType: ReviewMode::class, options: ['default' => 'AUTO'])]
    private ReviewMode $reviewMode = ReviewMode::AUTO;

    /** Ko je odlučio da zadatak ide bez provere ili na posebnu potvrdu (null: odluka dolazi iz posla ili je AUTO) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $reviewDecidedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reviewDecidedAt = null;

    /** Posebna potvrda dolazi iz posla (requiresApproval): zaključano, ne menja se na zadatku */
    #[ORM\Column(options: ['default' => false])]
    private bool $reviewFromJob = false;

    /** Kad je zadatak postao ZAVRSENO (potvrdom ili direktno bez provere); za statistiku, jer approvedAt ostaje null kad provere nije bilo */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /** Zadatak je završen direktno, bez provere (za statistiku); approvedBy i approvedAt ostaju null */
    #[ORM\Column(options: ['default' => false])]
    private bool $completedWithoutReview = false;

    /**
     * Snapshot proveravača posebne potvrde (kopira se iz posla ili bira na zadatku). Kasnija izmena posla ne menja postojeće zadatke.
     *
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'task_required_approver')]
    private Collection $requiredApprovers;

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
        $this->requiredApprovers = new ArrayCollection();
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

    public function getTitleSearch(): string
    {
        return $this->titleSearch;
    }

    /** Samo za TaskTitleSearchListener */
    public function setTitleSearch(string $titleSearch): static
    {
        $this->titleSearch = $titleSearch;

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

    public function isDueDateManuallySet(): bool
    {
        return $this->dueDateManuallySet;
    }

    public function setDueDateManuallySet(bool $manual): static
    {
        $this->dueDateManuallySet = $manual;

        return $this;
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
        $this->estimatedMinutes = $estimatedMinutes;
        $this->generatedAutomatically = true;

        return $this;
    }

    /**
     * Ručno napravljen zadatak iz ugovorenog posla ("Kreiraj zadatak sada", posao "po potrebi"): bez perioda.
     */
    public function linkJob(?ClientJob $job): static
    {
        $this->clientJob = $job;
        $this->estimatedMinutes = $job?->getEstimatedMinutes();

        return $this;
    }

    /** Za formu zadatka (polje "Posao") */
    public function setClientJob(?ClientJob $job): static
    {
        return $this->linkJob($job);
    }

    public function getTakenOverFrom(): ?User
    {
        return $this->takenOverFrom;
    }

    public function setTakenOverFrom(?User $user): static
    {
        $this->takenOverFrom = $user;

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

    public function getReviewMode(): ReviewMode
    {
        return $this->reviewMode;
    }

    public function setReviewMode(ReviewMode $mode): static
    {
        $this->reviewMode = $mode;

        return $this;
    }

    public function getReviewDecidedBy(): ?User
    {
        return $this->reviewDecidedBy;
    }

    public function getReviewDecidedAt(): ?\DateTimeImmutable
    {
        return $this->reviewDecidedAt;
    }

    public function setReviewDecision(?User $by): static
    {
        $this->reviewDecidedBy = $by;
        $this->reviewDecidedAt = null === $by ? null : new \DateTimeImmutable();

        return $this;
    }

    public function isReviewFromJob(): bool
    {
        return $this->reviewFromJob;
    }

    public function setReviewFromJob(bool $fromJob): static
    {
        $this->reviewFromJob = $fromJob;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function isCompletedWithoutReview(): bool
    {
        return $this->completedWithoutReview;
    }

    public function setCompletedWithoutReview(bool $value): static
    {
        $this->completedWithoutReview = $value;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getRequiredApprovers(): Collection
    {
        return $this->requiredApprovers;
    }

    /**
     * @param iterable<User> $users
     */
    public function setRequiredApprovers(iterable $users): static
    {
        $this->requiredApprovers->clear();
        foreach ($users as $user) {
            if (!$this->requiredApprovers->contains($user)) {
                $this->requiredApprovers->add($user);
            }
        }

        return $this;
    }

    /** Posebna potvrda: izabrana na zadatku ili iz posla (stariji zadaci sa snapshot-om bez moda važe isto) */
    public function isSpecialReview(): bool
    {
        return ReviewMode::SPECIAL === $this->reviewMode || (ReviewMode::AUTO === $this->reviewMode && !$this->requiredApprovers->isEmpty());
    }}
