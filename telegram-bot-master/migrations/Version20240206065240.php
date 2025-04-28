<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240206065240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //Model
        $this->addSql('ALTER TABLE models ALTER COLUMN label DROP not null');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE models ALTER COLUMN label SET not null');
    }
}
