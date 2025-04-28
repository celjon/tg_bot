<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230720061252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //User
        $this->addSql('ALTER TABLE users ALTER COLUMN tg_id TYPE bigint');
        //Message
        $this->addSql('ALTER TABLE messages
            ALTER COLUMN user_id TYPE bigint,
            ALTER COLUMN message_id TYPE bigint,
            ALTER COLUMN chat_id TYPE bigint
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN tg_id TYPE integer');
        $this->addSql('ALTER TABLE messages
            ALTER COLUMN user_id TYPE integer,
            ALTER COLUMN message_id TYPE integer,
            ALTER COLUMN chat_id TYPE integer
        ');
    }
}
