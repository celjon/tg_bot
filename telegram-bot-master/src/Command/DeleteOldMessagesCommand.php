<?php

namespace App\Command;

use App\Service\MessageService;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteOldMessagesCommand extends Command
{
    /** @var MessageService */
    private $messageService;

    /**
     * DeleteOldMessagesCommand constructor.
     * @param MessageService $messageService
     * @param string|null $name
     */
    public function __construct(MessageService $messageService, string $name = null)
    {
        $this->messageService = $messageService;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('delete-old-messages')->setDescription('Removing old messages');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->messageService->deleteOldMessages();
        return 0;
    }
}