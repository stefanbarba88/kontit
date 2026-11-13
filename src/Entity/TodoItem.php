<?php

namespace App\Entity;

use App\Doctrine\Type\UtcDateTimeImmutableType;
use App\Entity\Trait\TimestampableTrait;
use App\Enum\TodoRepeat;
use App\Repository\TodoItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stavka lične liste ("Podsetnik"). PRIVATNO: vidi i menja je samo vlasnik (TodoVoter), ni admin ni superadmin.
 * Nije zadatak: nema provere, nema klijenta u statistici, ne beleži se u ActivityLog, ne ulazi u izvoz.
 * Sva vremena su UTC (tip utc_datetime_immutable); konverziju radi {@see \App\Service\TodoClock}.
 */
#[ORM\Entity(repositoryClass: TodoItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'IDX_TODO_OWNER', fields: ['owner', 'isDone', 'dueAt'])]
#[ORM\Index(name: 'IDX_TODO_REMIND', fields: ['remindAt', 'reminderSentAt'])]
class TodoItem
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Upiši naslov.')]
    #[Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    /** Rok. Bez vremena (dueHasTime=false) je to početak lokalnog dana roka, a stavka kasni tek posle ponoći tog dana. */
    #[ORM\Column(type: UtcDateTimeImmutableType::NAME, nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $dueHasTime = false;

    #[ORM\Column(type: UtcDateTimeImmutableType::NAME, nullable: true)]
    private ?\DateTimeImmutable $remindAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $remindByEmail = false;

    /** Kad je podsetnik poslat (postavlja se atomski, zaštita od duplog slanja) */
    #[ORM\Column(type: UtcDateTimeImmutableType::NAME, nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    /** Kad je podsetnik prikazan kao toast u jednom od tabova (atomski, toast dobija samo jedan tab) */
    #[ORM\Column(type: UtcDateTimeImmutableType::NAME, nullable: true)]
    private ?\DateTimeImmutable $shownAt = null;

    #[ORM\Column(name: 'repeat_mode', length: 10, enumType: TodoRepeat::class, options: ['default' => 'NONE'])]
    private TodoRepeat $repeat = TodoRepeat::NONE;

    /** Dan u mesecu iz kog se računa MONTHLY (31. ne "klizi" na 28. posle februara) */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $repeatDay = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDone = false;

    #[ORM\Column(type: UtcDateTimeImmutableType::NAME, nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $pinned = false;

    /** Kontekst: klijent ili zadatak. Link se prikazuje samo ako vlasnik i dalje sme da ih vidi. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Task $task = null;

    /** Zadatak nastao iz ove stavke ("Pretvori u zadatak") */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Task $createdTask = null;

    /** Stavka čijim je završetkom nastala ova (ponavljanje); "Poništi" briše netaknutu novu stavku */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TodoItem $repeatSource = null;

    public function __construct(User $owner, string $title = '')
    {
        $this->owner = $owner;
        $this->title = $title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = null !== $note && '' !== trim($note) ? $note : null;

        return $this;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function hasDueTime(): bool
    {
        return $this->dueHasTime;
    }

    public function setDue(?\DateTimeImmutable $dueAt, bool $hasTime): static
    {
        $this->dueAt = $dueAt;
        $this->dueHasTime = null !== $dueAt && $hasTime;

        return $this;
    }

    public function getRemindAt(): ?\DateTimeImmutable
    {
        return $this->remindAt;
    }

    /** Novo vreme podsetnika vraća stavku u stanje "još nije poslato / prikazano" */
    public function setRemindAt(?\DateTimeImmutable $remindAt): static
    {
        if ($remindAt != $this->remindAt) {
            $this->reminderSentAt = null;
            $this->shownAt = null;
        }
        $this->remindAt = $remindAt;

        return $this;
    }

    public function isRemindByEmail(): bool
    {
        return $this->remindByEmail;
    }

    public function setRemindByEmail(bool $remindByEmail): static
    {
        $this->remindByEmail = $remindByEmail;

        return $this;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function getShownAt(): ?\DateTimeImmutable
    {
        return $this->shownAt;
    }

    public function getRepeat(): TodoRepeat
    {
        return $this->repeat;
    }

    public function setRepeat(TodoRepeat $repeat): static
    {
        $this->repeat = $repeat;
        if (TodoRepeat::MONTHLY !== $repeat) {
            $this->repeatDay = null;
        }

        return $this;
    }

    public function getRepeatDay(): ?int
    {
        return $this->repeatDay;
    }

    public function setRepeatDay(?int $repeatDay): static
    {
        $this->repeatDay = $repeatDay;

        return $this;
    }

    public function isDone(): bool
    {
        return $this->isDone;
    }

    public function getDoneAt(): ?\DateTimeImmutable
    {
        return $this->doneAt;
    }

    public function markDone(\DateTimeImmutable $at): static
    {
        $this->isDone = true;
        $this->doneAt = $at;

        return $this;
    }

    public function markOpen(): static
    {
        $this->isDone = false;
        $this->doneAt = null;

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

    public function isPinned(): bool
    {
        return $this->pinned;
    }

    public function setPinned(bool $pinned): static
    {
        $this->pinned = $pinned;

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

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(?Task $task): static
    {
        $this->task = $task;

        return $this;
    }

    public function getCreatedTask(): ?Task
    {
        return $this->createdTask;
    }

    public function setCreatedTask(?Task $createdTask): static
    {
        $this->createdTask = $createdTask;

        return $this;
    }

    public function getRepeatSource(): ?TodoItem
    {
        return $this->repeatSource;
    }

    public function setRepeatSource(?TodoItem $repeatSource): static
    {
        $this->repeatSource = $repeatSource;

        return $this;
    }

    /** Stavka nije menjana od kreiranja (za "Poništi" završetka ponavljajuće stavke) */
    public function isUntouched(): bool
    {
        return !$this->isDone && $this->createdAt == $this->updatedAt;
    }
}
