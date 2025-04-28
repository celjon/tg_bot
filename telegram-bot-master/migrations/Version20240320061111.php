<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240320061111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //Message
        $this->addSql('CREATE INDEX type_status_index ON messages (type, status)');
        $this->addSql('CREATE INDEX parsed_at_index ON messages (parsed_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX type_status_index');
        $this->addSql('DROP INDEX parsed_at_index');
    }
}
