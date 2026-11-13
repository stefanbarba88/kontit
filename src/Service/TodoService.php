<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TodoItem;
use App\Entity\User;
use App\Enum\TodoRepeat;
use App\Repository\TodoItemRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Podsetnik: dodavanje, završavanje (sa ponavljanjem i poništavanjem), brisanje, razvrstavanje u sekcije.
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
        private readonly WorkingDayService $workingDays,
    ) {
    }

    /** Brza stavka bez roka */
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

    /** Broj svih otvorenih stavki: ikonica u topbaru ima tačku kad je veći od 0 */
    public function openCount(User $owner): int
    {
        return $this->items->countOpen($owner);
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
    /**
     * Primenjuje polja iz zahteva (samo ona koja su poslata). Datumi su lokalni (Europe/Belgrade): dueDate "Y-m-d", dueTime "H:i".
     * Bez dueTime rok je ceo dan. Podsetnik bez vremena je u 09:00. Greške su DomainException sa porukom za korisnika.
     *
     * @param array<string, mixed> $data
     */
    public function fill(TodoItem $item, array $data): void
    {
        if (\array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ('' === $title || mb_strlen($title) > 200) {
                throw new \DomainException('Naslov je obavezan i može imati najviše 200 karaktera.');
            }
            $item->setTitle($title);
        }
        if (\array_key_exists('note', $data)) {
            $note = null === $data['note'] ? null : (string) $data['note'];
            if (null !== $note && mb_strlen($note) > 5000) {
                throw new \DomainException('Napomena je predugačka.');
            }
            $item->setNote($note);
        }
        if (\array_key_exists('dueDate', $data)) {
            $date = $this->date($data['dueDate']);
            $time = null === $date ? null : $this->time($data['dueTime'] ?? null);
            $item->setDue(null === $date ? null : (null !== $time ? $this->clock->fromLocal($date, $time) : $this->clock->startOfDay($date)), null !== $time);
        }
        if (\array_key_exists('remindDate', $data)) {
            $date = $this->date($data['remindDate']);
            $item->setRemindAt(null === $date ? null : $this->clock->fromLocal($date, $this->time($data['remindTime'] ?? null) ?? '09:00'));
        }
        if (\array_key_exists('remindByEmail', $data)) {
            $item->setRemindByEmail((bool) $data['remindByEmail']);
        }
        if (\array_key_exists('pinned', $data)) {
            $item->setPinned((bool) $data['pinned']);
        }
        if (\array_key_exists('repeat', $data)) {
            $repeat = TodoRepeat::tryFrom((string) $data['repeat']);
            if (null === $repeat) {
                throw new \DomainException('Nepoznato ponavljanje.');
            }
            $item->setRepeat($repeat);
        }
        if (TodoRepeat::NONE !== $item->getRepeat() && null === $item->getDueAt() && null === $item->getRemindAt()) {
            throw new \DomainException('Ponavljanje traži rok ili vreme obaveštenja.');
        }
    }

    private function date(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        $value = (string) $value;
        if (1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new \DomainException('Neispravan datum.');
        }

        return $value;
    }

    private function time(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (1 !== preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $value)) {
            throw new \DomainException('Neispravno vreme.');
        }

        return (string) $value;
    }

    /**
     * Podaci stavke za "Poništi" brisanja (JS ih vraća na /ajax/todo/restore).
     *
     * @return array<string, mixed>
     */
    public function snapshot(TodoItem $item): array
    {
        return $this->formValues($item) + [
            'title' => $item->getTitle(),
            'note' => $item->getNote(),
            'remindByEmail' => $item->isRemindByEmail(),
            'repeat' => $item->getRepeat()->value,
            'pinned' => $item->isPinned(),
            'clientId' => $item->getClient()?->getId(),
            'taskId' => $item->getTask()?->getId(),
        ];
    }

    /**
     * Polja za formu stavke, u lokalnoj zoni (prazan string kad vrednosti nema).
     *
     * @return array{dueDate: string, dueTime: string, remindDate: string, remindTime: string}
     */
    public function formValues(TodoItem $item): array
    {
        $due = $item->getDueAt();
        $remind = $item->getRemindAt();

        return [
            'dueDate' => null !== $due ? $this->clock->localDate($due) : '',
            'dueTime' => null !== $due && $item->hasDueTime() ? $this->clock->localTime($due) : '',
            'remindDate' => null !== $remind ? $this->clock->localDate($remind) : '',
            'remindTime' => null !== $remind ? $this->clock->localTime($remind) : '',
        ];
    }

    /**
     * Brzi čipovi za rok i podsetnik. Čip sa vremenom postavlja i rok i podsetnik na to vreme, "Danas" samo rok (ceo dan).
     *
     * @return list<array{key: string, label: string, date: string, time: ?string}>
     */
    public function chips(): array
    {
        $today = new \DateTimeImmutable($this->clock->today());

        return [
            ['key' => 'today', 'label' => 'Danas', 'date' => $today->format('Y-m-d'), 'time' => null],
            ['key' => 'tomorrow', 'label' => 'Sutra 9:00', 'date' => $today->modify('+1 day')->format('Y-m-d'), 'time' => '09:00'],
            ['key' => 'monday', 'label' => 'Ponedeljak 9:00', 'date' => $today->modify('next monday')->format('Y-m-d'), 'time' => '09:00'],
        ];
    }

    /** "Danas" / "Sutra" / "Juče" / "13.11." (+ vreme) za rok stavke; null kad nema roka */
    public function dueLabel(TodoItem $item): ?string
    {
        $due = $item->getDueAt();

        return null === $due ? null : $this->dayLabel($due).($item->hasDueTime() ? ' '.$this->clock->localTime($due) : '');
    }

    public function remindLabel(TodoItem $item): ?string
    {
        $remind = $item->getRemindAt();

        return null === $remind ? null : $this->dayLabel($remind).' '.$this->clock->localTime($remind);
    }

    private function dayLabel(\DateTimeInterface $moment): string
    {
        $date = $this->clock->localDate($moment);
        $today = new \DateTimeImmutable($this->clock->today());

        return match ($date) {
            $today->format('Y-m-d') => 'Danas',
            $today->modify('+1 day')->format('Y-m-d') => 'Sutra',
            $today->modify('-1 day')->format('Y-m-d') => 'Juče',
            default => (new \DateTimeImmutable($date))->format('d.m.'),
        };
    }

    /**
     * Pretraga po naslovu i napomeni, samo među stavkama vlasnika.
     *
     * @return list<TodoItem>
     */
    public function search(User $owner, string $query): array
    {
        return $this->items->search($owner, $query);
    }
    public const SNOOZE_PRESETS = ['10m', '1h', 'tomorrow'];

    /**
     * Odloži obaveštenje: 10 min, 1 h ili sutra u 9:00 (sutra = sledeći radni dan). Ponovo se šalje i ponovo prikazuje.
     * Rok stavke se ne menja.
     */
    public function snooze(TodoItem $item, string $preset): void
    {
        $now = $this->clock->now();
        $at = match ($preset) {
            '10m' => $now->modify('+10 minutes'),
            '1h' => $now->modify('+1 hour'),
            'tomorrow' => $this->clock->fromLocal($this->workingDays->nextWorkingDay(new \DateTimeImmutable($this->clock->today().' +1 day'))->format('Y-m-d'), '09:00'),
            default => throw new \DomainException('Nepoznato odlaganje.'),
        };
        $item->setRemindAt($at);
        $this->em->flush();
    }
    /** Posle čuvanja zadatka iz stavke ("Pretvori u zadatak"): stavka se završava i dobija link na zadatak */
    public function markConverted(TodoItem $item, Task $task): void
    {
        $item->setCreatedTask($task);
        $this->complete($item);
        $this->em->flush();
    }
}
