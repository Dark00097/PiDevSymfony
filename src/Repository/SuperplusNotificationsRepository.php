<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SuperplusNotifications;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SuperplusNotifications>
 */
class SuperplusNotificationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuperplusNotifications::class);
    }
}
