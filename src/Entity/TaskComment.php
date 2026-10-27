<?php

namespace App\Entity;

use App\Enum\CommentType;
use App\Repository\TaskCommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskCommentRepository::class)]
class TaskComment
{
    /** Koliko minuta posle objave autor sme da izmeni komentar */
    public const EDIT_WINDOW_MINUTES = 15;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(length: 30, enumType: CommentType::class)]
    private CommentType $type;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $editedAt = null;

    public function __construct(Task $task, User $author, string $body, CommentType $type = CommentType::KOMENTAR)
    {
        $this->task = $task;
        $this->author = $author;
        $this->body = $body;
        $this->type = $type;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getType(): CommentType
    {
        return $this->type;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEditedAt(): ?\DateTimeImmutable
    {
        return $this->editedAt;
    }

    public function edit(string $body): static
    {
        $this->body = $body;
        $this->editedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isEditableBy(User $user): bool
    {
        return CommentType::KOMENTAR === $this->type
            && $this->author === $user
            && $this->createdAt > new \DateTimeImmutable(sprintf('-%d minutes', self::EDIT_WINDOW_MINUTES));
    }
}
