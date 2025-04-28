<?php

namespace App\Repository;

use App\Entity\Model;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityRepository;

/**
 * Class ModelRepository
 * @package App\Repository
 *
 * @method Model findOneBy(array $criteria, ?array $orderBy = null)
 * @method Model[] findAll()
 * @method Model[] findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class ModelRepository extends EntityRepository
{
    /**
     * @throws Exception
     */
    public function deleteAll(): void
    {
        $this->getEntityManager()->getConnection()->prepare('DELETE FROM models WHERE TRUE')->executeQuery();
    }

    /**
     * @param string $id
     * @return Model|null
     */
    public function findOneById(string $id): ?Model
    {
        return $this->findOneBy(['id' => $id]);
    }
}