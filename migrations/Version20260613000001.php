<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align password and vault tables with PasswordEntry/Vault entities';
    }

    public function up(Schema $schema): void
    {
        // Rename service_name -> title in password table (PasswordEntry entity)
        $this->addSql('ALTER TABLE password RENAME COLUMN service_name TO title');

        // Add favorite column to password table
        $this->addSql('ALTER TABLE password ADD COLUMN favorite BOOLEAN NOT NULL DEFAULT FALSE');

        // Add archived and updated_at to vault table
        $this->addSql('ALTER TABLE vault ADD COLUMN archived BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE vault ADD COLUMN updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password RENAME COLUMN title TO service_name');
        $this->addSql('ALTER TABLE password DROP COLUMN favorite');
        $this->addSql('ALTER TABLE vault DROP COLUMN archived');
        $this->addSql('ALTER TABLE vault DROP COLUMN updated_at');
    }
}
