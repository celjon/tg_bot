<?php

namespace App\Command;

use App\Exception\TelegramUpdateException;
use App\Service\TgBotService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LongPollingCommand extends Command
{
    /** @var TgBotService */
    private $tgBotService;
    /** @var EntityManagerInterface */
    private $em;
    /** @var LoggerInterface */
    private $logger;

    /**
     * @param TgBotService $tgBotService
     * @param EntityManagerInterface $em
     * @param LoggerInterface $logger
     * @param string|null $name
     */
    public function __construct(
        TgBotService $tgBotService,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        string $name = null
    ) {
        $this->tgBotService = $tgBotService;
        $this->em = $em;
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('long-polling')->setDescription('Long polling instead of webhooks');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $offset = 0;

        while (true) {
            try {
                $this->em->beginTransaction();

                $updates = $this->tgBotService->getUpdates($offset);

                foreach ($updates as $update) {
                    $this->tgBotService->parseUpdate($update->toJson());

                    $offset = $update->getUpdateId() + 1;
                }

                $this->em->flush();
                $this->em->commit();
            } catch (TelegramUpdateException|Exception $e) {
                $this->logger->error($e->getMessage(), ['exception' => $e]);
                $this->em->rollback();
            }

            sleep(1);
        }
    }
}