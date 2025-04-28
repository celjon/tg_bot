<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240126094815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //User
        $this->addSql('ALTER TABLE users
            ADD COLUMN gpt_model text DEFAULT null,
            ADD COLUMN image_generation_model text DEFAULT null,
            ADD COLUMN tool text DEFAULT NULL
        ');
        //Model
        $this->addSql('CREATE TABLE models (
            id text not null,
            label text not null,
            max_tokens integer not null,
            PRIMARY KEY (id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users
            DROP COLUMN gpt_model,
            DROP COLUMN image_generation_model,
            DROP COLUMN tool
        ');
        $this->addSql('DROP TABLE models');
    }
}
