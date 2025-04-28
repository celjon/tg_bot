<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230517071247 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //User
        $this->addSql('CREATE TABLE users (
            id serial not null,
            tg_id integer not null,
            first_name text not null,
            last_name text default null,
            username text default null,
            language_code char(2) default null,
            bothub_id text default null,
            bothub_group_id text default null,
            current_bothub_chat_id text default null,
            current_bothub_chat_model text default null,
            registered_at timestamp(0) default now() not null,
            bothub_access_token text default null,
            bothub_access_token_created_at timestamp(0) default null,
            state smallint default null,
            PRIMARY KEY (id),
            UNIQUE (tg_id),
            UNIQUE (bothub_id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
