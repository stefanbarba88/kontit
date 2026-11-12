<?php

namespace App\Entity;

use App\Enum\AssigneeStatus;
use App\Repository\TaskAssigneeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskAssigneeRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_TASK_ASSIGNEE', fields: ['task', 'user'])]
#[ORM\Index(name: 'IDX_TASK_ASSIGNEE_USER_TASK', fields: ['user', 'task'])]
class TaskAssignee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'assignees')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 20, enumType: AssigneeStatus::class)]
    private AssigneeStatus $status = AssigneeStatus::NOVO;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function __construct(Task $task, User $user)
    {
        $this->task = $task;
        $this->user = $user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatus(): AssigneeStatus
    {
        return $this->status;
    }

    public function setStatus(AssigneeStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = '' === $note ? null : $note;

        return $this;
    }
}
