<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240627102504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //User
        $this->addSql('ALTER TABLE users ALTER COLUMN tg_id DROP NOT NULL');
        $this->addSql('ALTER TABLE users ALTER COLUMN first_name DROP NOT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN present_data text DEFAULT NULL');
        //Present
        $this->addSql('CREATE TABLE presents (
            id serial not null,
            user_id integer not null,
            tokens bigint not null,
            notified boolean not null DEFAULT false,
            parsed_at timestamp(0) default now() not null,
            notified_at timestamp(0) default null,
            PRIMARY KEY (id),
            FOREIGN KEY (user_id) REFERENCES users ON DELETE CASCADE
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN tg_id SET NOT NULL');
        $this->addSql('ALTER TABLE users ALTER COLUMN first_name SET NOT NULL');
        $this->addSql('ALTER TABLE users DROP COLUMN present_data');
        $this->addSql('DROP TABLE presents');
    }
}
