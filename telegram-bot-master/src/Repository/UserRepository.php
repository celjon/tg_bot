<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * Class UserRepository
 * @package App\Repository
 *
 * @method User findOneBy(array $criteria, ?array $orderBy = null)
 * @method User[] findAll()
 * @method User[] findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends EntityRepository
{
    /**
     * @param int|null $tgId
     * @param string|null $username
     * @return User|null
     * @throws NonUniqueResultException
     */
    public function findOneByTgIdOrUsername(?int $tgId, ?string $username): ?User
    {
        if ($tgId && $username) {
            return $this->createQueryBuilder('u')
                ->andWhere('u.tgId = :tgId OR u.username = :username')
                ->setParameter('tgId', $tgId)
                ->setParameter('username', $username)
                ->getQuery()
                ->getOneOrNullResult()
            ;
        } elseif ($tgId) {
            return $this->findOneBy(['tgId' => $tgId]);
        } elseif ($username) {
            return $this->findOneBy(['username' => $username]);
        } else {
            return null;
        }
    }

    /**
     * @param string $bothubId
     * @return User|null
     */
    public function findOneByBothubId(string $bothubId): ?User
    {
        return $this->findOneBy(['bothubId' => $bothubId]);
    }
}