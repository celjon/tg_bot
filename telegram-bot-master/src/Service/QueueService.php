<?php

namespace App\Service;

use App\Entity\Message;
use App\Repository\MessageRepository;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class QueueService
{
    /** @var integer */
    private $workers;
    /** @var MessageRepository */
    private $messageRepo;

    /**
     * QueueService constructor.
     * @param ParameterBagInterface $parameterBag
     * @param MessageRepository $messageRepo
     */
    public function __construct(ParameterBagInterface $parameterBag, MessageRepository $messageRepo)
    {
        $queueSettings = $parameterBag->get('queueSettings');
        $this->workers = $queueSettings['workers'];
        $this->messageRepo = $messageRepo;
    }

    /**
     * @return int
     */
    public function getWorkers(): int
    {
        return $this->workers;
    }

    /**
     * @param Message $message
     * @return Message
     * @throws Exception
     */
    public function setWorkerForMessage(Message $message): Message
    {
        if ($message->getType() !== Message::TYPE_NO_ACTION) {
            $worker = $this->messageRepo->getWorkerByChatId($message->getChatId(), $this->workers);
            $message->setWorker($worker);
        }
        return $message;
    }
}