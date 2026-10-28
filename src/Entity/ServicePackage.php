<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\ServicePackageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paket: skup poslova sa jednom mesečnom cenom (bez PDV-a). Cenu vidi samo admin+.
 */
#[ORM\Entity(repositoryClass: ServicePackageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ServicePackage
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Unesi naziv paketa.')]
    #[Assert\Length(max: 150)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Predložena mesečna cena (popunjava dogovorenu cenu pri dodeli klijentu; važi samo cena na klijentu) */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $price = null;

    #[ORM\Column]
    private bool $isActive = true;

    /** @var Collection<int, ServicePackageItem> */
    #[ORM\OneToMany(targetEntity: ServicePackageItem::class, mappedBy: 'package', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
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

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

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
     * @return Collection<int, ServicePackageItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @return list<JobTemplate>
     */
    public function getJobTemplates(): array
    {
        return array_values($this->items->map(static fn (ServicePackageItem $i) => $i->getJobTemplate())->toArray());
    }

    /**
     * Postavlja poslove paketa na zadatu listu (redosled se čuva). Postojeće stavke se zadržavaju.
     *
     * @param iterable<JobTemplate> $jobs
     */
    public function setJobTemplates(iterable $jobs): static
    {
        $wanted = [];
        foreach ($jobs as $job) {
            $wanted[$job->getId()] = $job;
        }

        foreach ($this->items->toArray() as $item) {
            if (!isset($wanted[$item->getJobTemplate()->getId()])) {
                $this->items->removeElement($item);
            }
        }

        $existing = [];
        foreach ($this->items as $item) {
            $existing[$item->getJobTemplate()->getId()] = $item;
        }
        $position = 0;
        foreach ($wanted as $id => $job) {
            if (isset($existing[$id])) {
                $existing[$id]->setPosition($position);
            } else {
                $this->items->add(new ServicePackageItem($this, $job, $position));
            }
            ++$position;
        }

        return $this;
    }
}
