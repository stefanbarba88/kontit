<?php

namespace App\Service;

use App\Entity\Position;
use App\Entity\User;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Mapira staru tekstualnu poziciju na entitet Position (poređenje preko TextNormalizer-a). Nepoznata vrednost pravi novu poziciju.
 * Ne dira korisnike koji već imaju poziciju i ne pravi UserPositionLog. Koristi ga komanda app:positions:migrate-legacy.
 */
class LegacyPositionMigrator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PositionRepository $positions,
        private readonly TextNormalizer $normalizer,
    ) {
    }

    /**
     * @param array<int, string> $legacy id korisnika => stara tekstualna vrednost
     *
     * @return array{rows: list<array{0: int|null, 1: string, 2: string, 3: string}>, created: list<Position>, skipped: int}
     */
    public function migrate(array $legacy, bool $dryRun): array
    {
        /** @var array<string, Position> $byKey */
        $byKey = [];
        $maxLevel = 0;
        $count = 0;
        foreach ($this->positions->findAll() as $position) {
            $byKey[$this->normalizer->normalize($position->getName())] = $position;
            $maxLevel = max($maxLevel, $position->getLevel());
            ++$count;
        }

        $rows = [];
        $created = [];
        $skipped = 0;
        foreach ($legacy as $userId => $value) {
            $value = trim($value);
            $user = $this->em->find(User::class, $userId);
            if ('' === $value || null === $user) {
                continue;
            }
            if (null !== $user->getPosition()) {
                ++$skipped;
                continue;
            }
            $key = $this->normalizer->normalize($value);
            $isNew = false;
            if (!isset($byKey[$key])) {
                $position = new Position(mb_substr($value, 0, 100), Position::COLORS[$count % \count(Position::COLORS)], ++$maxLevel);
                ++$count;
                $byKey[$key] = $position;
                $created[] = $position;
                $isNew = true;
                if (!$dryRun) {
                    $this->em->persist($position);
                }
            }
            $position = $byKey[$key];
            $rows[] = [$user->getId(), $user->getFullName(), $value, $position->getName().($isNew ? ' (NOVA)' : '')];
            if (!$dryRun) {
                $user->setPosition($position);
            }
        }
        if (!$dryRun) {
            $this->em->flush();
        }

        return ['rows' => $rows, 'created' => $created, 'skipped' => $skipped];
    }
}
