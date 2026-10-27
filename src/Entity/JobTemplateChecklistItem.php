<?php

namespace App\Entity;

use App\Repository\JobTemplateChecklistItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stavka checkliste šablona posla; kopira se u svaki generisan zadatak.
 */
#[ORM\Entity(repositoryClass: JobTemplateChecklistItemRepository::class)]
class JobTemplateChecklistItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'checklistItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?JobTemplate $jobTemplate = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Unesite tekst stavke.')]
    #[Assert\Length(max: 255)]
    private ?string $text = null;

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobTemplate(): ?JobTemplate
    {
        return $this->jobTemplate;
    }

    public function setJobTemplate(?JobTemplate $jobTemplate): static
    {
        $this->jobTemplate = $jobTemplate;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
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
}
