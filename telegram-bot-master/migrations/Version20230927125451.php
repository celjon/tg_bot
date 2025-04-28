<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230927125451 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //Message
        $this->addSql('ALTER TABLE messages ADD COLUMN data json DEFAULT null');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messages DROP COLUMN data');
    }
}
