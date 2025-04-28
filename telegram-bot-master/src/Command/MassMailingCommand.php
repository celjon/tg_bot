<?php

namespace App\Command;

use App\Exception\TelegramApiException;
use App\Service\KeyboardService;
use App\Service\LanguageService;
use App\Service\TgBotService;
use App\Service\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MassMailingCommand extends Command
{
    /** @var TgBotService */
    private $tgBotService;
    /** @var UserService */
    private $userService;
    /** @var KeyboardService */
    private $keyboardService;
    /** @var LoggerInterface */
    private $logger;

    private const IMAGES_DIRECTORY = __DIR__ . '/../../var/images/';

    /**
     * @param TgBotService $tgBotService
     * @param UserService $userService
     * @param KeyboardService $keyboardService
     * @param LoggerInterface $massMailingLogger
     * @param string|null $name
     */
    public function __construct(
        TgBotService $tgBotService,
        UserService $userService,
        KeyboardService $keyboardService,
        LoggerInterface $massMailingLogger,
        string $name = null
    ) {
        $this->tgBotService = $tgBotService;
        $this->userService = $userService;
        $this->keyboardService = $keyboardService;
        $this->logger = $massMailingLogger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('mass-mailing')
            ->setDescription('Mass mailing all telegram users')
            ->addArgument('image-name', InputArgument::REQUIRED, 'Image file name')
            ->addArgument('message-name', InputArgument::REQUIRED, 'Message name from lang constants');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('message-name');
        $imageUrl = $this::IMAGES_DIRECTORY . $input->getArgument('image-name');
        $photo = new \CURLFile(realpath($imageUrl));
        $lang = new LanguageService('ru');

        $count = 1;
        foreach ($this->userService->getAllUsers() as $user) {
            if (!$user->getTgId()) {
                continue;
            }

            $this->keyboardService->setUser($user);
            $keyboard = $this->keyboardService->getMainKeyboard($lang);

            try {
                $caption = $lang->getLocalizedString($name);
                $this->tgBotService->sendPhoto($user, $user->getTgId(), $photo, $caption, null, $keyboard, 'MarkdownV2');
                if ($count++ % 20 === 0) {
                    sleep(1);
                }
            } catch (TelegramApiException $e) {
                $this->logger->error($e->getMessage());
            }
        }
        return Command::SUCCESS;
    }
}