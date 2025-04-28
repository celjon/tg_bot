<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240723140156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //UserChat
        $this->addSql("CREATE TABLE users_chats (
            id serial not null,
            user_id integer not null,
            chat_index smallint not null DEFAULT 1,
            bothub_chat_id text default null,
            bothub_chat_model text default null,
            context_remember boolean not null DEFAULT true,
            context_counter integer not null DEFAULT 0,
            links_parse boolean not null DEFAULT false,
            buffer json DEFAULT null,
            system_prompt text NOT null DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE (user_id, chat_index),
            FOREIGN KEY (user_id) REFERENCES users ON DELETE CASCADE
        )");
        //Message
        $this->addSql('ALTER TABLE messages ADD COLUMN chat_index smallint NOT null DEFAULT 1');
        //User
        $this->addSql('ALTER TABLE users ADD COLUMN current_chat_index smallint NOT null DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users_chats');
        $this->addSql('ALTER TABLE messages DROP COLUMN chat_index');
        $this->addSql('ALTER TABLE users DROP COLUMN current_chat_index');
    }
}
