<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CashbackEntries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashbackEntries>
 */
class CashbackEntriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashbackEntries::class);
    }
}
