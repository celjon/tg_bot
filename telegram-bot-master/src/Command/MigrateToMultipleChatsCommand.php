<?php

namespace App\Command;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateToMultipleChatsCommand extends Command
{
    /** @var EntityManagerInterface */
    private $em;

    /**
     * MigrateToMultipleChatsCommand constructor.
     * @param EntityManagerInterface $em
     * @param string|null $name
     */
    public function __construct(EntityManagerInterface $em, string $name = null)
    {
        $this->em = $em;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('migrate-to-multiple-chats')
            ->setDescription('Migrating to multiple chats')
            ->addArgument('back', InputArgument::OPTIONAL, 'Migrate back', false);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $back = (bool)$input->getArgument('back');
        $connection = $this->em->getConnection();
        if (!$back) {
            $output->writeln('Migrating to multiple chats');
            $users = $connection->fetchAllAssociative('SELECT * FROM users ORDER BY id');
            foreach ($users as $user) {
                $output->writeln('userId=' . $user['id']);
                $connection->executeQuery('INSERT INTO users_chats (
                        user_id, chat_index, bothub_chat_id, bothub_chat_model, context_remember,
                        context_counter, links_parse, buffer, system_prompt
                    ) VALUES (
                        :user_id, 1, :bothub_chat_id, :bothub_chat_model, :context_remember,
                        :context_counter, :links_parse, :buffer, :system_prompt
                    )
                    ON CONFLICT DO NOTHING
                ', [
                    'user_id'           => $user['id'],
                    'bothub_chat_id'    => $user['current_bothub_chat_id'],
                    'bothub_chat_model' => $user['current_bothub_chat_model'],
                    'context_remember'  => $user['context_remember'],
                    'context_counter'   => $user['context_counter'],
                    'links_parse'       => $user['links_parse'],
                    'buffer'            => $user['buffer'],
                    'system_prompt'     => $user['system_prompt'],
                ], [
                    'context_remember'  => ParameterType::BOOLEAN,
                    'links_parse'       => ParameterType::BOOLEAN,
                ]);
            }
        } else {
            $output->writeln('Migrating back to single chat');
            $chats = $connection->fetchAllAssociative('SELECT * FROM users_chats WHERE chat_index = 1 ORDER BY user_id');
            foreach ($chats as $chat) {
                $output->writeln('userId=' . $chat['user_id']);
                $connection->executeQuery('UPDATE users SET
                    current_bothub_chat_id      = :bothub_chat_id,
                    current_bothub_chat_model   = :bothub_chat_model,
                    context_remember            = :context_remember,
                    context_counter             = :context_counter,
                    links_parse                 = :links_parse,
                    buffer                      = :buffer,
                    system_prompt               = :system_prompt
                    WHERE id                    = :user_id
                ', [
                    'user_id'           => $chat['user_id'],
                    'bothub_chat_id'    => $chat['bothub_chat_id'],
                    'bothub_chat_model' => $chat['bothub_chat_model'],
                    'context_remember'  => $chat['context_remember'],
                    'context_counter'   => $chat['context_counter'],
                    'links_parse'       => $chat['links_parse'],
                    'buffer'            => $chat['buffer'],
                    'system_prompt'     => $chat['system_prompt'],
                ], [
                    'context_remember'  => ParameterType::BOOLEAN,
                    'links_parse'       => ParameterType::BOOLEAN,
                ]);
            }
        }
        $output->writeln('Done');
        return 0;
    }
}