<?php

namespace App\Twig;

use App\Entity\JobCategory;
use App\Entity\JobTemplate;
use App\Repository\JobTemplateRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * catalog_groups(): aktivni poslovi grupisani po kategoriji, za job picker (SPEC 8h.2).
 * Poslovi iz $include (npr. već izabrani, a u međuvremenu deaktivirani) se prikazuju i kad nisu aktivni.
 */
class CatalogExtension extends AbstractExtension
{
    public function __construct(private readonly JobTemplateRepository $jobs)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('catalog_groups', $this->groups(...))];
    }

    /**
     * @param list<int> $include
     *
     * @return list<array{category: JobCategory, jobs: list<JobTemplate>}>
     */
    public function groups(array $include = []): array
    {
        $groups = [];
        foreach ($this->jobs->findForCatalog() as $job) {
            if (!$job->isActive() && !\in_array($job->getId(), $include, true)) {
                continue;
            }
            $category = $job->getCategory();
            $groups[$category->getId()] ??= ['category' => $category, 'jobs' => []];
            $groups[$category->getId()]['jobs'][] = $job;
        }

        return array_values($groups);
    }
}
