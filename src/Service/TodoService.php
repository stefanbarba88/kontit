<?php

namespace App\Service;

use App\Entity\TodoItem;
use App\Entity\User;
use App\Enum\TodoRepeat;
use App\Repository\TodoItemRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Moja lista: dodavanje, završavanje (sa ponavljanjem i poništavanjem), brisanje, razvrstavanje u sekcije.
 * Sve radi nad stavkama JEDNOG vlasnika; autorizaciju (TODO_* voter + findOwned → 404) rade kontroleri.
 */
class TodoService
{
    public const SECTIONS = ['overdue', 'today', 'upcoming', 'nodue'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TodoItemRepository $items,
        private readonly TodoClock $clock,
        private readonly TodoRepeatCalculator $repeat,
    ) {
    }

    /** Brza stavka bez roka (polje "Dodaj u moju listu…") */
    public function add(User $owner, string $title): TodoItem
    {
        $title = trim($title);
        if ('' === $title) {
            throw new \DomainException('Upiši naslov.');
        }
        if (mb_strlen($title) > 200) {
            throw new \DomainException('Naslov može imati najviše 200 karaktera.');
        }
        $item = (new TodoItem($owner, $title))->setPosition($this->items->nextPosition($owner));
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    /** Čuva izmenjenu stavku; MONTHLY pamti dan u mesecu iz roka (ili podsetnika) */
    public function save(TodoItem $item): void
    {
        if (TodoRepeat::MONTHLY === $item->getRepeat()) {
            $anchor = $item->getDueAt() ?? $item->getRemindAt();
            $item->setRepeatDay(null !== $anchor ? (int) $this->clock->toLocal($anchor)->format('j') : null);
        }
        $this->em->persist($item);
        $this->em->flush();
    }

    /**
     * Završava stavku. Ako se ponavlja, nastaje sledeća (vraća se). Već završena stavka se ne menja.
     */
    public function complete(TodoItem $item): ?TodoItem
    {
        if ($item->isDone()) {
            return null;
        }
        $item->markDone($this->clock->now());
        $next = null;
        if (null !== ($dates = $this->repeat->next($item))) {
            $next = (new TodoItem($item->getOwner(), $item->getTitle()))
                ->setNote($item->getNote())
                ->setDue($dates['dueAt'], $item->hasDueTime())
                ->setRemindAt($dates['remindAt'])
                ->setRemindByEmail($item->isRemindByEmail())
                ->setRepeat($item->getRepeat())
                ->setRepeatDay($dates['repeatDay'])
                ->setPinned($item->isPinned())
                ->setClient($item->getClient())
                ->setTask($item->getTask())
                ->setRepeatSource($item)
                ->setPosition($this->items->nextPosition($item->getOwner()));
            $this->em->persist($next);
        }
        $this->em->flush();

        return $next;
    }

    /**
     * "Poništi": vraća stavku u rad. Ako je završavanjem nastala nova (ponavljanje) i još je netaknuta, briše se,
     * da ne nastanu duplikati. Vraća true kad je nova stavka obrisana.
     */
    public function reopen(TodoItem $item): bool
    {
        $item->markOpen();
        $removed = false;
        $child = $this->items->findRepeatChild($item);
        if (null !== $child && $child->isUntouched()) {
            $this->em->remove($child);
            $removed = true;
        }
        $this->em->flush();

        return $removed;
    }

    public function delete(TodoItem $item): void
    {
        $this->em->remove($item);
        $this->em->flush();
    }

    /** Broj za topbar: zakasnelo + danas (granica je početak sutrašnjeg dana po Beogradu, UTC parametar) */
    public function dueCount(User $owner): int
    {
        return $this->items->countDueBefore($owner, $this->clock->startOfTomorrow());
    }

    /** overdue | today | upcoming | nodue za nezavršenu stavku */
    public function bucket(TodoItem $item): string
    {
        $due = $item->getDueAt();
        if (null === $due) {
            return 'nodue';
        }
        $date = $this->clock->localDate($due);
        $today = $this->clock->today();
        if ($date < $today || ($item->hasDueTime() && $due < $this->clock->now())) {
            return 'overdue';
        }

        return $date === $today ? 'today' : 'upcoming';
    }

    /**
     * Sekcije liste: Zakasnelo, Danas, Predstojeće, Bez roka (zakačene prve) i poslednjih 20 završenih.
     *
     * @return array{overdue: list<TodoItem>, today: list<TodoItem>, upcoming: list<TodoItem>, nodue: list<TodoItem>, done: list<TodoItem>}
     */
    public function sections(User $owner): array
    {
        $sections = ['overdue' => [], 'today' => [], 'upcoming' => [], 'nodue' => []];
        foreach ($this->items->findOpen($owner) as $item) {
            $sections[$this->bucket($item)][] = $item;
        }
        foreach (['overdue', 'today', 'upcoming'] as $name) {
            usort($sections[$name], static fn (TodoItem $a, TodoItem $b) => [!$a->isPinned(), $a->getDueAt(), $a->getId()] <=> [!$b->isPinned(), $b->getDueAt(), $b->getId()]);
        }
        usort($sections['nodue'], static fn (TodoItem $a, TodoItem $b) => [!$a->isPinned(), $a->getPosition(), $a->getId()] <=> [!$b->isPinned(), $b->getPosition(), $b->getId()]);

        return $sections + ['done' => $this->items->findRecentDone($owner)];
    }

    /**
     * Redosled u "Bez roka" (prevlačenje): $ids su ID-evi stavki vlasnika; tuđi ID-evi se ignorišu.
     *
     * @param list<int> $ids
     */
    public function reorder(User $owner, array $ids): void
    {
        $position = 0;
        foreach ($ids as $id) {
            $item = $this->items->findOwned((int) $id, $owner);
            if (null !== $item) {
                $item->setPosition(++$position);
            }
        }
        $this->em->flush();
    }
}
