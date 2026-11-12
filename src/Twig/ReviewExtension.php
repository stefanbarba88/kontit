<?php

namespace App\Twig;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\SpecialApprovalService;
use App\Service\TaskReviewPolicy;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * task_review(task): značka "ko proverava" (TaskReviewPolicy::describe).
 * review_preview(task, user): da li "Moj deo je gotov" završava zadatak bez provere (za SweetAlert).
 * special_approval_count(): brojač u meniju za admina i superadmina (posebne potvrde koje čekaju ulogovanog).
 */
class ReviewExtension extends AbstractExtension
{
    private ?int $count = null;

    public function __construct(
        private readonly TaskReviewPolicy $policy,
        private readonly TaskRepository $tasks,
        private readonly Security $security,
        private readonly SpecialApprovalService $special,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('task_review', $this->policy->describe(...)),
            new TwigFunction('finishes_without_review', $this->finishesWithoutReview(...)),
            new TwigFunction('special_approval_count', $this->specialCount(...)),
            new TwigFunction('special_wait', $this->wait(...)),
        ];
    }

    /**
     * Da li bi "Moj deo je gotov" od $user završio zadatak odmah, bez provere (ishod NONE uz sve ostale učesnike koji su već gotovi).
     */
    public function finishesWithoutReview(Task $task, User $user): bool
    {
        return $this->policy->wouldFinishWithoutReview($task, $user);
    }

    /** @return array{days: int, overdue: bool, text: string} koliko radnih dana posebna potvrda čeka */
    public function wait(Task $task): array
    {
        $days = $this->special->waitingWorkingDays($task);
        $text = 0 === $days ? 'Čeka od danas' : sprintf('Čeka %d %s', $days, 1 === $days ? 'radni dan' : 'radna dana');

        return ['days' => $days, 'overdue' => $this->special->isOverdue($task), 'text' => $text];
    }

    public function specialCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->security->isGranted('SPECIAL_APPROVALS_VIEW')) {
            return 0;
        }

        return $this->count ??= $this->tasks->countAwaitingSpecialApproval($user, false);
    }
}
