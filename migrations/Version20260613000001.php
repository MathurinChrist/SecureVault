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
        // No-op: password_entry already has title/favorite; vault already has archived/updated_at
        // (all created by Version20260609195115)
    }

    public function down(Schema $schema): void
    {
        // No-op
    }
}
