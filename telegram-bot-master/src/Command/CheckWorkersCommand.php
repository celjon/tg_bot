<?php

namespace App\Command;

use App\Entity\Message;
use App\Exception\BothubException;
use App\Service\BothubService;
use App\Service\KeyboardService;
use App\Service\LanguageService;
use App\Service\MessageService;
use App\Service\TgBotService;
use App\Service\UserChatService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckWorkersCommand extends Command
{
    /** @var MessageService */
    private $messageService;
    /** @var TgBotService */
    private $tgBotService;
    /** @var KeyboardService */
    private $keyboardService;
    /** @var BothubService */
    private $bothubService;
    /** @var UserChatService */
    private $userChatService;
    /** @var EntityManagerInterface */
    private $em;
    /** @var LoggerInterface */
    private $logger;

    /**
     * CheckWorkersCommand constructor.
     * @param MessageService $messageService
     * @param TgBotService $tgBotService
     * @param KeyboardService $keyboardService
     * @param BothubService $bothubService
     * @param UserChatService $userChatService
     * @param EntityManagerInterface $em
     * @param LoggerInterface $logger
     * @param string|null $name
     */
    public function __construct(
        MessageService $messageService,
        TgBotService $tgBotService,
        KeyboardService $keyboardService,
        BothubService $bothubService,
        UserChatService $userChatService,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        string $name = null
    ) {
        $this->messageService = $messageService;
        $this->tgBotService = $tgBotService;
        $this->keyboardService = $keyboardService;
        $this->bothubService = $bothubService;
        $this->userChatService = $userChatService;
        $this->em = $em;
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('check-workers')->setDescription('Checking workers');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     * @throws NonUniqueResultException
     * @throws BothubException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $problemMessages = $this->messageService->getProblemMessages(720);
        $alerts = [];
        foreach ($problemMessages as $message) {
            $alert = 'Проблема с воркером ' . $message->getWorker() . ' - сообщение ' . $message->getId();
            $this->logger->error($alert);
            $type = $message->getType();
            $data = $message->getData();
            if (in_array($type, [Message::TYPE_VOICE_MESSAGE, Message::TYPE_DOCUMENT_MESSAGE, Message::TYPE_VIDEO_MESSAGE]) || (
                $type == Message::TYPE_BUFFER_MESSAGE && !empty($data) && (
                    !empty($data['message']['voice']) || !empty($data['message']['audio']) ||
                    !empty($data['message']['video_note']) || !empty($data['message']['video'])
                )
            )) {
                $user = $message->getUser();
                $lang = new LanguageService($user->getLanguageCode());
                $message->setStatus(Message::STATUS_PROCESSED);
                $this->keyboardService->setUser($user);
                $userChat = $this->userChatService->getUserChat($user);
                $webSearch = $userChat && $this->bothubService->getWebSearch($userChat);
                $this->keyboardService->setIsWebSearch($webSearch);
                $keyboard = $this->keyboardService->getMainKeyboard($lang);
                $content = $lang->getLocalizedString('L_ERROR_TOKEN_LIMIT_EXCEEDED');
                $this->logger->error('Проблема может быть связана со слишком большим размером загруженного файла, пытаемся автоматически отменить его обработку и высылаем пользователю ' . $user->getId() . ' сообщение об ошибке');
                try {
                    $this->tgBotService->sendMessage($user, $message->getChatId(), $content, $message, $keyboard);
                } catch (\Exception $e) {
                    $this->logger->error('Ошибка при отправке сообщения пользователю ' . $user->getId() . ': ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
                }
                $this->em->flush();
            } else {
                $alerts[] = $alert;
            }
        }
        if (!empty($alerts)) {
            try {
                $this->tgBotService->sendServiceAlert(implode(PHP_EOL . PHP_EOL, $alerts));
            } catch (\Exception $e) {
                $this->logger->error('Ошибка при отправке сообщения в служебный канал: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            }
        }
        return 0;
    }
}