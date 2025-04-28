<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * Class MessageRepository
 * @package App\Repository
 *
 * @method Message findOneBy(array $criteria, ?array $orderBy = null)
 * @method Message[] findAll()
 * @method Message[] findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends EntityRepository
{
    /**
     * @param int $worker
     * @return Message[]
     */
    public function findForProcessing(int $worker): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.direction = :direction')
            ->andWhere('m.status = :status')
            ->andWhere('m.type <> :type')
            ->andWhere('m.worker = :worker')
            ->setParameter('direction', Message::DIRECTION_REQUEST)
            ->setParameter('status', Message::STATUS_NOT_PROCESSED)
            ->setParameter('type', Message::TYPE_NO_ACTION)
            ->setParameter('worker', $worker)
            ->orderBy('m.id')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param int $id
     * @return Message|null
     */
    public function findOneById(int $id): ?Message
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @param int $chatId
     * @param int $workers
     * @return int
     * @throws Exception
     */
    public function getWorkerByChatId(int $chatId, int $workers): int
    {
        /** @var Message[] $messages */
        $messages = $this->createQueryBuilder('m')
            ->andWhere('m.direction = :direction')
            ->andWhere('m.status = :status')
            ->andWhere('m.type <> :type')
            ->andWhere('m.chatId = :chatId')
            ->setParameter('direction', Message::DIRECTION_REQUEST)
            ->setParameter('status', Message::STATUS_NOT_PROCESSED)
            ->setParameter('type', Message::TYPE_NO_ACTION)
            ->setParameter('chatId', $chatId)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
        if (!empty($messages) && !empty($messages[0]) && $messages[0]->getWorker()) {
            return $messages[0]->getWorker();
        }
        return $this->getFreestWorker($workers);
    }

    /**
     * @param int $workers
     * @return int
     * @throws Exception
     */
    public function getFreestWorker(int $workers): int
    {
        $rows = $this->getEntityManager()->getConnection()->prepare('
            SELECT worker, COUNT(id) as queue FROM messages
            WHERE direction = :direction AND status = :status AND type <> :type AND worker <= :workers
            GROUP BY worker ORDER BY queue, worker
        ')->executeQuery([
            'direction' => Message::DIRECTION_REQUEST,
            'status'    => Message::STATUS_NOT_PROCESSED,
            'type'      => Message::TYPE_NO_ACTION,
            'workers'   => $workers
        ])->fetchAllAssociative();
        $queues = [];
        foreach($rows as $row) {
            $queues[(int)$row['worker']] = $row['queue'];
        }
        for ($i = 1; $i <= $workers; $i++) {
            if (!isset($queues[$i])) {
                return $i;
            }
        }
        return (int)$rows[0]['worker'];
    }

    /**
     * @throws Exception
     */
    public function deleteOld(): void
    {
        $this->getEntityManager()->getConnection()->prepare("
            DELETE FROM messages
            WHERE parsed_at < (NOW() - INTERVAL '7 DAYS') AND (type = :type OR status = :status)
        ")->executeQuery([
            'type'      => Message::TYPE_NO_ACTION,
            'status'    => Message::STATUS_PROCESSED
        ]);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getWorkersQueues(): array
    {
        $rows = $this->getEntityManager()->getConnection()->prepare('
            SELECT worker, COUNT(id) as queue FROM messages
            WHERE direction = :direction AND status = :status AND type <> :type
            GROUP BY worker ORDER BY worker
        ')->executeQuery([
            'direction' => Message::DIRECTION_REQUEST,
            'status'    => Message::STATUS_NOT_PROCESSED,
            'type'      => Message::TYPE_NO_ACTION,
        ])->fetchAllAssociative();
        $queues = [];
        foreach($rows as $row) {
            $queues[(int)$row['worker']] = $row['queue'];
        }
        return $queues;
    }

    /**
     * @param int $worker
     * @return Message|null
     * @throws NonUniqueResultException
     */
    public function findOldestUnprocessedMessageByWorker(int $worker): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.direction = :direction')
            ->andWhere('m.status = :status')
            ->andWhere('m.type <> :type')
            ->andWhere('m.worker = :worker')
            ->setParameter('direction', Message::DIRECTION_REQUEST)
            ->setParameter('status', Message::STATUS_NOT_PROCESSED)
            ->setParameter('type', Message::TYPE_NO_ACTION)
            ->setParameter('worker', $worker)
            ->orderBy('m.id')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}