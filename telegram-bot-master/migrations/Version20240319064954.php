<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240319064954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //Message
        $this->addSql('CREATE INDEX direction_status_type_index ON messages (direction, status, type)');
        $this->addSql('CREATE INDEX worker_index ON messages (worker)');
        $this->addSql('CREATE INDEX chat_id_index ON messages (chat_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX direction_status_type_index');
        $this->addSql('DROP INDEX worker_index');
        $this->addSql('DROP INDEX chat_id_index');
    }
}
