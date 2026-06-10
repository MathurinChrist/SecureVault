<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610091718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD is2fa_enabled BOOLEAN DEFAULT FALSE');
        $this->addSql('UPDATE "user" SET is2fa_enabled = FALSE');
        $this->addSql('ALTER TABLE "user" ALTER COLUMN is2fa_enabled SET NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD two_factor_secret VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP is2fa_enabled');
        $this->addSql('ALTER TABLE "user" DROP two_factor_secret');
    }
}
