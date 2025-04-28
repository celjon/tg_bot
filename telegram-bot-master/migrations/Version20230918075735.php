<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230918075735 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //Message
        $this->addSql('ALTER TABLE messages ADD COLUMN worker smallint DEFAULT null');
        $this->addSql('UPDATE messages SET worker=1 WHERE direction = 0 AND STATUS = 0 AND TYPE <> 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messages DROP COLUMN worker');
    }
}
