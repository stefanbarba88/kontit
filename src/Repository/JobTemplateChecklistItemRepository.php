<?php

namespace App\Repository;

use App\Entity\JobTemplateChecklistItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobTemplateChecklistItem>
 */
class JobTemplateChecklistItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobTemplateChecklistItem::class);
    }
}
