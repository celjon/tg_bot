<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230518085906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //Message
        $this->addSql('CREATE TABLE messages (
            id serial not null,
            user_id integer not null,
            message_id integer not null,
            direction smallint not null,
            type smallint not null,
            status smallint not null,
            chat_id integer not null,
            text text not null,
            sent_at timestamp(0) default now() not null,
            parsed_at timestamp(0) default now() not null,
            related_message_id integer default null,
            PRIMARY KEY (id),
            FOREIGN KEY (user_id) REFERENCES users ON DELETE CASCADE,
            FOREIGN KEY (related_message_id) REFERENCES messages ON DELETE CASCADE
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messages');
    }
}
