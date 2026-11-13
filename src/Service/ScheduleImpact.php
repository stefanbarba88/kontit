<?php

namespace App\Service;

use App\Entity\Task;

/**
 * Šta bi promena rasporeda uradila otvorenim zadacima (SPEC 8g.4): kojima se menja rok, a koji ostaju na starom
 * jer njihov period više ne postoji u novom rasporedu (npr. mesečno -> kvartalno).
 */
final class ScheduleImpact
{
    /**
     * @param list<array{task: Task, from: \DateTimeImmutable, to: \DateTimeImmutable}> $moves
     * @param list<Task>                                                               $stranded
     */
    public function __construct(public array $moves = [], public array $stranded = [])
    {
    }

    public function movedCount(): int
    {
        return \count($this->moves);
    }

    /**
     * Isti uticaj bez jednog zadatka (npr. onog čiji rok se baš ručno postavlja, pa ga preračun ne dira).
     */
    public function without(Task $task): self
    {
        return new self(
            array_values(array_filter($this->moves, static fn (array $m) => $m['task'] !== $task)),
            array_values(array_filter($this->stranded, static fn (Task $t) => $t !== $task)),
        );
    }

    public function merge(self $other): void
    {
        $this->moves = [...$this->moves, ...$other->moves];
        $this->stranded = [...$this->stranded, ...$other->stranded];
    }

    /**
     * Za JSON odgovor i SweetAlert: broj i spisak zadataka koji ostaju (klijent, posao, period).
     *
     * @return array{moved: int, clients: int, stranded: list<array{client: string, job: string, period: string, due: ?string}>}
     */
    public function toArray(): array
    {
        $clients = [];
        foreach ($this->moves as $move) {
            $clients[$move['task']->getClient()?->getId()] = true;
        }

        return [
            'moved' => $this->movedCount(),
            'clients' => \count($clients),
            'stranded' => array_map(static fn (Task $t) => [
                'client' => (string) $t->getClient()?->getShortName(),
                'job' => (string) $t->getClientJob()?->getJobTemplate()->getName(),
                'period' => (string) $t->getPeriodKey(),
                'due' => $t->getDueDate()?->format('d.m.Y.'),
            ], $this->stranded),
        ];
    }
}
