<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add key_version to password_entry for per-user encryption migration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_entry ADD key_version INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_entry DROP COLUMN key_version');
    }
}
