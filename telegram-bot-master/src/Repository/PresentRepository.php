<?php

namespace App\Repository;

use App\Entity\Present;
use Doctrine\ORM\EntityRepository;

/**
 * Class PresentRepository
 * @package App\Repository
 *
 * @method Present findOneBy(array $criteria, ?array $orderBy = null)
 * @method Present[] findAll()
 * @method Present[] findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class PresentRepository extends EntityRepository
{

}