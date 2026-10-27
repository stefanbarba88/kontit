<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\CompletionMode;
use App\Enum\JobCategory;
use App\Enum\PriceUnit;
use App\Enum\Recurrence;
use App\Enum\TaskPriority;
use App\Repository\JobTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Posao (šablon): od njega nastaju zadaci (SPEC 8b). Cenu vidi samo admin+.
 */
#[ORM\Entity(repositoryClass: JobTemplateRepository::class)]
#[ORM\HasLifecycleCallbacks]
class JobTemplate
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Unesite naziv posla.')]
    #[Assert\Length(max: 150)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30, enumType: JobCategory::class)]
    private JobCategory $category = JobCategory::OSTALO;

    #[ORM\Column(length: 20, enumType: Recurrence::class)]
    private Recurrence $recurrence = Recurrence::MONTHLY;

    /** Dan u mesecu (MONTHLY, QUARTERLY: poslednji mesec kvartala; YEARLY: dan u dueMonth) */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 31)]
    private ?int $dueDay = 15;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 12)]
    private ?int $dueMonth = null;

    /** Koliko dana pre roka se kreira zadatak */
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 120)]
    private int $generateDaysBefore = 10;

    #[ORM\Column(length: 20, enumType: TaskPriority::class)]
    private TaskPriority $defaultPriority = TaskPriority::NORMALAN;

    #[ORM\Column(length: 10, enumType: CompletionMode::class)]
    private CompletionMode $completionMode = CompletionMode::ALL;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $estimatedMinutes = 60;

    #[ORM\Column(length: 20, enumType: PriceUnit::class)]
    private PriceUnit $priceUnit = PriceUnit::PO_IZVRSENJU;

    #[ORM\Column]
    private bool $isActive = true;

    /** @var Collection<int, JobTemplateChecklistItem> */
    #[ORM\OneToMany(targetEntity: JobTemplateChecklistItem::class, mappedBy: 'jobTemplate', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $checklistItems;

    public function __construct()
    {
        $this->checklistItems = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validateRecurrence(ExecutionContextInterface $context): void
    {
        if (in_array($this->recurrence, [Recurrence::MONTHLY, Recurrence::QUARTERLY, Recurrence::YEARLY], true) && null === $this->dueDay) {
            $context->buildViolation('Unesite dan roka.')->atPath('dueDay')->addViolation();
        }
        if (Recurrence::YEARLY === $this->recurrence && null === $this->dueMonth) {
            $context->buildViolation('Unesite mesec roka.')->atPath('dueMonth')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

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

    public function getCategory(): JobCategory
    {
        return $this->category;
    }

    public function setCategory(JobCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getRecurrence(): Recurrence
    {
        return $this->recurrence;
    }

    public function setRecurrence(Recurrence $recurrence): static
    {
        $this->recurrence = $recurrence;

        return $this;
    }

    public function getDueDay(): ?int
    {
        return $this->dueDay;
    }

    public function setDueDay(?int $dueDay): static
    {
        $this->dueDay = $dueDay;

        return $this;
    }

    public function getDueMonth(): ?int
    {
        return $this->dueMonth;
    }

    public function setDueMonth(?int $dueMonth): static
    {
        $this->dueMonth = $dueMonth;

        return $this;
    }

    public function getGenerateDaysBefore(): int
    {
        return $this->generateDaysBefore;
    }

    public function setGenerateDaysBefore(int $days): static
    {
        $this->generateDaysBefore = $days;

        return $this;
    }

    public function getDefaultPriority(): TaskPriority
    {
        return $this->defaultPriority;
    }

    public function setDefaultPriority(TaskPriority $priority): static
    {
        $this->defaultPriority = $priority;

        return $this;
    }

    public function getCompletionMode(): CompletionMode
    {
        return $this->completionMode;
    }

    public function setCompletionMode(CompletionMode $mode): static
    {
        $this->completionMode = $mode;

        return $this;
    }

    public function getEstimatedMinutes(): int
    {
        return $this->estimatedMinutes;
    }

    public function setEstimatedMinutes(int $minutes): static
    {
        $this->estimatedMinutes = $minutes;

        return $this;
    }

    public function getPriceUnit(): PriceUnit
    {
        return $this->priceUnit;
    }

    public function setPriceUnit(PriceUnit $unit): static
    {
        $this->priceUnit = $unit;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $active): static
    {
        $this->isActive = $active;

        return $this;
    }

    /**
     * @return Collection<int, JobTemplateChecklistItem>
     */
    public function getChecklistItems(): Collection
    {
        return $this->checklistItems;
    }

    public function addChecklistItem(JobTemplateChecklistItem $item): static
    {
        if (!$this->checklistItems->contains($item)) {
            $item->setJobTemplate($this);
            $item->setPosition($this->checklistItems->count());
            $this->checklistItems->add($item);
        }

        return $this;
    }

    public function removeChecklistItem(JobTemplateChecklistItem $item): static
    {
        $this->checklistItems->removeElement($item);

        return $this;
    }
}
