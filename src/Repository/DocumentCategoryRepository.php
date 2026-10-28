<?php

namespace App\Repository;

use App\Entity\DocumentCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentCategory>
 */
class DocumentCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentCategory::class);
    }

    /**
     * @return list<DocumentCategory>
     */
    public function findOrdered(bool $activeOnly = false): array
    {
        return $this->findBy($activeOnly ? ['isActive' => true] : [], ['position' => 'ASC', 'name' => 'ASC']);
    }
}
