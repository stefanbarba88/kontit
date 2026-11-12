<?php

namespace App\EventListener;

use App\Entity\Task;
use App\Service\TextNormalizer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Održava task.title_search (normalizovan naziv za pretragu) na SVAKO čuvanje zadatka, bez obzira ko je promenio naziv
 * (forma, generator zadataka, uvoz, komanda): prePersist za novi zadatak, onFlush za izmenu postojećeg.
 * Provereno: u aplikaciji nigde ne postoji masovni DQL/SQL UPDATE koji menja task.title. Ako se takav ikad napiše, MORA sam da
 * postavi i title_search (TextNormalizer), jer ga listener ne vidi (zaobilazi se ORM). Ako se nešto raziđe: php bin/console app:tasks:reindex-search.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::onFlush)]
final class TaskTitleSearchListener
{
    public function __construct(private readonly TextNormalizer $normalizer)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Task) {
            $entity->setTitleSearch($this->normalizer->normalize($entity->getTitle()));
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $meta = $em->getClassMetadata(Task::class);

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Task) {
                continue;
            }
            $search = $this->normalizer->normalize($entity->getTitle());
            if ($search === $entity->getTitleSearch()) {
                continue;
            }
            $entity->setTitleSearch($search);
            $uow->recomputeSingleEntityChangeSet($meta, $entity);
        }
    }
}
