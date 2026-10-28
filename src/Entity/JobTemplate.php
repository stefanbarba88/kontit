<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\CompletionMode;
use App\Enum\PriceUnit;
use App\Enum\TaskPriority;
use App\Repository\JobTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Posao (šablon): od njega nastaju zadaci (SPEC 8g). Raspored je ugrađen Schedule. Cenu vidi samo admin+.
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
    #[Assert\NotBlank(message: 'Unesi naziv posla.')]
    #[Assert\Length(max: 150)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Izaberi kategoriju.')]
    private ?JobCategory $category = null;

    /** Raspored izvršavanja (SPEC 8g.3); "Po potrebi" (NONE) = sistem ne pravi zadatke sam */
    #[ORM\Embedded(class: Schedule::class)]
    #[Assert\Valid]
    private Schedule $schedule;

    /** Predložena cena kad se posao ugovara pojedinačno (stvarna cena je ClientJob.price) */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $defaultPrice = null;

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
        $this->schedule = Schedule::monthly();
    }

    #[Assert\Callback]
    public function validateSchedule(ExecutionContextInterface $context): void
    {
        if ($this->schedule->isEmpty()) {
            $context->buildViolation('Izaberi raspored.')->atPath('schedule.type')->addViolation();
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

    public function getCategory(): ?JobCategory
    {
        return $this->category;
    }

    public function setCategory(?JobCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule;
    }

    public function setSchedule(Schedule $schedule): static
    {
        $this->schedule = $schedule;

        return $this;
    }

    public function getDefaultPrice(): ?string
    {
        return $this->defaultPrice;
    }

    public function setDefaultPrice(?string $defaultPrice): static
    {
        $this->defaultPrice = $defaultPrice;

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
            // pozicija dolazi iz checklist editora; CatalogService::saveJob ponovo numeriše
            $item->setJobTemplate($this);
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
