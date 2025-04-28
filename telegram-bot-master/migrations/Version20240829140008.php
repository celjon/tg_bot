<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240829140008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //UserChat
        $this->addSql("
            UPDATE users_chats SET context_remember=false,
            system_prompt='Исправь грамматические и стилистические ошибки в тексте. Не давай никаких комментариев.'
            WHERE chat_index=5 AND system_prompt=''
        ");
    }
}
