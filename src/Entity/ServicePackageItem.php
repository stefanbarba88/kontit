<?php

namespace App\Entity;

use App\Repository\ServicePackageItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServicePackageItemRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PACKAGE_JOB', columns: ['package_id', 'job_template_id'])]
class ServicePackageItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ServicePackage $package;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private JobTemplate $jobTemplate;

    #[ORM\Column]
    private int $position = 0;

    public function __construct(ServicePackage $package, JobTemplate $jobTemplate, int $position = 0)
    {
        $this->package = $package;
        $this->jobTemplate = $jobTemplate;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPackage(): ServicePackage
    {
        return $this->package;
    }

    public function getJobTemplate(): JobTemplate
    {
        return $this->jobTemplate;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
