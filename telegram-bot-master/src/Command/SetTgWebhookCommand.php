<?php

namespace App\Command;

use App\Service\TgBotService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetTgWebhookCommand extends Command
{
    /** @var TgBotService */
    private $tgBotService;

    /**
     * SetTgWebhookCommand constructor.
     * @param TgBotService $tgBotService
     * @param string|null $name
     */
    public function __construct(TgBotService $tgBotService, string $name = null)
    {
        $this->tgBotService = $tgBotService;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('set-tg-webhook')
            ->setDescription('Setting a webhook for Telegram')
            ->addArgument('url', InputArgument::REQUIRED, 'Webhook url');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->tgBotService->setWebhook($input->getArgument('url'));
        } catch (Exception $e) {
            $output->writeln($e->getMessage());
            $output->writeln($e->getTraceAsString());
        }
        $output->writeln('Success');
        return 0;
    }
}