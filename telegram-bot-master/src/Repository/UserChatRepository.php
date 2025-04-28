<?php

namespace App\Repository;

use App\Entity\UserChat;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * Class ChatRepository
 * @package App\Repository
 *
 * @method UserChat findOneBy(array $criteria, ?array $orderBy = null)
 * @method UserChat[] findAll()
 * @method UserChat[] findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class UserChatRepository extends EntityRepository
{
    /**
     * @param int $userId
     * @param int $chatIndex
     * @return UserChat|null
     */
    public function findOneByUserIdAndChatIndex(int $userId, int $chatIndex): ?UserChat
    {
        return $this->findOneBy(['user' => $userId, 'chatIndex' => $chatIndex]);
    }

    /**
     * @param int $userId
     * @param int $page
     * @param int $itemsPerPage
     * @return array
     */
    public function findPaginatedExcludingFirstFive(int $userId, int $page, int $itemsPerPage): array
    {
        $offset = ($page - 2) * $itemsPerPage;
        return $this->createQueryBuilder('uc')
            ->where('uc.chatIndex > 5')
            ->andWhere('uc.user = :userId')
            ->orderBy('uc.chatIndex', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($itemsPerPage)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    public function getTotalPages(int $userId, int $itemsPerPage): int
    {
        $qb = $this->createQueryBuilder('uc')
            ->select('COUNT(uc.id)')
            ->where('uc.chatIndex > 5')
            ->andWhere('uc.user = :userId')
            ->setParameter('userId', $userId);

        try {
            $totalCount = (int) $qb->getQuery()->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException $e) {
            $totalCount = 0;
        }

        return ceil(($totalCount + 5) / $itemsPerPage);
    }

    /**
     * @param int $userId
     * @return int
     */
    public function getLastChatIndex(int $userId): int
    {
        $qb = $this->createQueryBuilder('uc')
            ->select('uc.chatIndex')
            ->where('uc.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('uc.chatIndex', 'DESC')
            ->setMaxResults(1);

        $lastDefaultChatIndex = 5;
        try {
            $lastIndex = (int) $qb->getQuery()->getSingleScalarResult();
            if ($lastIndex <= $lastDefaultChatIndex) {
                $lastIndex = $lastDefaultChatIndex;
            }
        } catch (NoResultException|NonUniqueResultException $e) {
            $lastIndex = $lastDefaultChatIndex;
        }

        return $lastIndex;
    }
}