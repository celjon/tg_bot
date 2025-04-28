<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230720075019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //User
        $this->addSql('ALTER TABLE users ALTER COLUMN tg_id TYPE text');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN tg_id TYPE bigint');
    }
}
