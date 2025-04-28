<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

class MessageService
{
    /** @var MessageRepository */
    private $messageRepo;
    /** @var EntityManagerInterface */
    private $em;

    /**
     * MessageService constructor.
     * @param MessageRepository $messageRepo
     * @param EntityManagerInterface $em
     */
    public function __construct(MessageRepository $messageRepo, EntityManagerInterface $em)
    {
        $this->messageRepo = $messageRepo;
        $this->em = $em;
    }

    /**
     * @param User $user
     * @param int $messageId
     * @param int $chatId
     * @param string $text
     * @param int $direction
     * @param int $type
     * @param Message|null $relatedMessage
     * @param DateTimeImmutable|null $sentAt
     * @param int $status
     * @param array|null $data
     * @return Message
     */
    public function addMessage(
        User $user,
        int $messageId,
        int $chatId,
        string $text,
        int $direction,
        int $type,
        ?Message $relatedMessage = null,
        ?DateTimeImmutable $sentAt = null,
        ?array $data = null,
        int $status = Message::STATUS_NOT_PROCESSED
    ): Message {
        $parsedAt = new DateTimeImmutable();
        $chatIndex = $relatedMessage && $relatedMessage->getChatIndex()
            ? $relatedMessage->getChatIndex()
            : $user->getCurrentChatIndex();
        $message = (new Message())
            ->setUser($user)
            ->setMessageId($messageId)
            ->setChatId($chatId)
            ->setText($text)
            ->setData($data)
            ->setDirection($direction)
            ->setType($type)
            ->setStatus($status)
            ->setRelatedMessage($relatedMessage)
            ->setSentAt($sentAt ?? $parsedAt)
            ->setParsedAt($parsedAt)
            ->setChatIndex($chatIndex)
        ;
        $this->em->persist($message);
        return $message;
    }

    /**
     * @param User $user
     * @param int $messageId
     * @param int $chatId
     * @param string $text
     * @param int $date
     * @return Message
     */
    public function addCreateNewChatMessage(
        User $user,
        int $messageId,
        int $chatId,
        string $text,
        int $date
    ): Message {
        return $this->addMessage(
            $user,
            $messageId,
            $chatId,
            $text,
            Message::DIRECTION_REQUEST,
            Message::TYPE_CREATE_NEW_CHAT,
            null,
            (new DateTimeImmutable())->setTimestamp($date)
        );
    }

    /**
     * @param int $worker
     * @return Message[]
     */
    public function getMessagesForProcessing(int $worker): array
    {
        return $this->messageRepo->findForProcessing($worker);
    }

    /**
     * @throws Exception
     */
    public function deleteOldMessages(): void
    {
        $this->messageRepo->deleteOld();
    }

    /**
     * Возвращает массив проблемных сообщений, на которых зависли воркеры
     *
     * @param int $allowableTimeInterval сколько секунд сообщение может висеть в очереди, не вызывая подозрений, что воркер завис
     * @return Message[]
     * @throws Exception
     * @throws NonUniqueResultException
     */
    public function getProblemMessages(int $allowableTimeInterval): array
    {
        $allowableTimestamp = time() - $allowableTimeInterval;
        $queues = $this->messageRepo->getWorkersQueues();
        $messages = [];
        foreach ($queues as $worker => $queue) {
            if ($queue == 0) {
                continue;
            }
            $message = $this->messageRepo->findOldestUnprocessedMessageByWorker($worker);
            if ($message && $message->getParsedAt()->getTimestamp() < $allowableTimestamp) {
                $messages[] = $message;
            }
        }
        return $messages;
    }
}