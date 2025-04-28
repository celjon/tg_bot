<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240619062737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //User
        $this->addSql('ALTER TABLE users ALTER COLUMN links_parse SET DEFAULT false');
        $this->addSql('UPDATE users SET links_parse = false WHERE true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN links_parse SET DEFAULT true');
        $this->addSql('UPDATE users SET links_parse = true WHERE true');
    }
}
