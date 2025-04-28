<?php

namespace App\Repository;

use App\Entity\Plan;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityRepository;

/**
 * Class PlanRepository
 * @package App\Repository
 *
 * @method Plan findOneBy(array $criteria, ?array $orderBy = null)
 * @method Plan[] findAll()
 * @method Plan[] findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class PlanRepository extends EntityRepository
{
    /**
     * @throws Exception
     */
    public function deleteAll(): void
    {
        $this->getEntityManager()->getConnection()->prepare('DELETE FROM plans WHERE TRUE')->executeQuery();
    }

    /**
     * @param array $currencies
     * @return Plan[]
     */
    public function findByCurrencies(array $currencies): array
    {
        return $this->findBy(['currency' => $currencies]);
    }

    /**
     * @param string $type
     * @return Plan[]
     */
    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type]);
    }
}