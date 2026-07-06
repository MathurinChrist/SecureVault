<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706191456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vault.encrypted_key (per-vault encryption key, wrapped with the server master key) to support decryption by shared vault recipients';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vault ADD encrypted_key TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vault DROP encrypted_key');
    }
}
