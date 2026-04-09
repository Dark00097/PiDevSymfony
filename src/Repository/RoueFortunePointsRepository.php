<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RoueFortunePoints;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoueFortunePoints>
 */
class RoueFortunePointsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoueFortunePoints::class);
    }
}
