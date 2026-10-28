<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use App\Repository\UserAbsenceRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * ICS pretplata korisnika (SPEC 8n): zadaci u kojima je učesnik (celodnevno na dan roka, link u opisu) i njegova odobrena odsustva.
 * Samo čitanje; opseg od 30 dana unazad do godinu dana unapred.
 */
class IcsExporter
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly UserAbsenceRepository $absences,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function export(User $user): string
    {
        $from = new \DateTimeImmutable('-30 days');
        $to = new \DateTimeImmutable('+365 days');
        $stamp = gmdate('Ymd\THis\Z');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//KontIT app//SR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:KontIT', 'X-WR-TIMEZONE:Europe/Belgrade'];

        $qb = $this->tasks->createQueryBuilder('t')
            ->addSelect('c')->leftJoin('t.client', 'c')
            ->andWhere('EXISTS (SELECT 1 FROM App\Entity\TaskAssignee a WHERE a.task = t AND a.user = :me)')
            ->andWhere('t.dueDate BETWEEN :from AND :to AND t.status <> :cancelled')
            ->setParameter('me', $user)->setParameter('from', $from)->setParameter('to', $to)
            ->setParameter('cancelled', TaskStatus::OTKAZANO);
        foreach ($qb->getQuery()->getResult() as $task) {
            $url = $this->urls->generate('task_show', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
            $done = TaskStatus::ZAVRSENO === $task->getStatus();
            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:task-'.$task->getId().'@kontit',
                'DTSTAMP:'.$stamp,
                'DTSTART;VALUE=DATE:'.$task->getDueDate()->format('Ymd'),
                'DTEND;VALUE=DATE:'.$task->getDueDate()->modify('+1 day')->format('Ymd'),
                'SUMMARY:'.$this->text(($done ? '✓ ' : '').$task->getTitle()),
                'DESCRIPTION:'.$this->text(sprintf("%s\nStatus: %s\n%s", $task->getClient()?->getShortName() ?? 'Interni zadatak', $task->getStatus()->label(), $url)),
                'URL:'.$url,
                'TRANSP:TRANSPARENT',
                'END:VEVENT',
            ]);
        }

        foreach ($this->absences->findInPeriod([$user], $from, $to, false) as $absence) {
            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:absence-'.$absence->getId().'@kontit',
                'DTSTAMP:'.$stamp,
                'DTSTART;VALUE=DATE:'.$absence->getStartDate()->format('Ymd'),
                'DTEND;VALUE=DATE:'.$absence->getEndDate()->modify('+1 day')->format('Ymd'),
                'SUMMARY:'.$this->text((string) $absence->getType()->getName()),
                'DESCRIPTION:'.$this->text($absence->summary()),
                'TRANSP:OPAQUE',
                'END:VEVENT',
            ]);
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /** Tekst po RFC 5545: \ ; , i novi red se escape-uju */
    private function text(string $value): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /** Linije duže od 75 okteta se prelamaju (nastavak počinje razmakom), bez cepanja UTF-8 znaka */
    private function fold(string $line): string
    {
        $out = '';
        while (\strlen($line) > 75) {
            $cut = 75;
            while ($cut > 0 && (\ord($line[$cut]) & 0xC0) === 0x80) {
                --$cut;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $out.$line;
    }
}
