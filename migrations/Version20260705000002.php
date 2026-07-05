<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'STI: merge alert + notification into base_notification (Single Table Inheritance)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE base_notification (
            id          SERIAL NOT NULL,
            user_id     INT NOT NULL,
            discr       VARCHAR(20) NOT NULL,
            title       VARCHAR(255) NOT NULL,
            type        VARCHAR(50) DEFAULT \'info\' NOT NULL,
            is_read     BOOLEAN DEFAULT FALSE NOT NULL,
            created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            description TEXT DEFAULT NULL,
            category    VARCHAR(50) DEFAULT NULL,
            message     TEXT DEFAULT NULL,
            is_sent     BOOLEAN DEFAULT FALSE,
            sent_at     TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_BASE_NOTIF_USER ON base_notification (user_id)');
        $this->addSql('ALTER TABLE base_notification ADD CONSTRAINT FK_BASE_NOTIF_USER FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('COMMENT ON COLUMN base_notification.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN base_notification.sent_at IS \'(DC2Type:datetime_immutable)\'');

        // Migrate existing alerts
        $this->addSql("INSERT INTO base_notification (user_id, discr, title, type, is_read, created_at, description, category)
            SELECT user_id, 'alert', title, type, is_read, created_at, description, category FROM alert");

        // Migrate existing notifications
        $this->addSql("INSERT INTO base_notification (user_id, discr, title, type, is_read, created_at, message, is_sent, sent_at)
            SELECT user_id, 'notification', title, type, is_read, created_at, message, is_sent, sent_at FROM notification");

        $this->addSql('DROP TABLE IF EXISTS alert');
        $this->addSql('DROP TABLE IF EXISTS notification');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE alert (
            id          SERIAL NOT NULL,
            user_id     INT NOT NULL,
            title       VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            type        VARCHAR(50) DEFAULT \'info\' NOT NULL,
            category    VARCHAR(50) DEFAULT \'general\' NOT NULL,
            is_read     BOOLEAN DEFAULT FALSE NOT NULL,
            created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE TABLE notification (
            id          SERIAL NOT NULL,
            user_id     INT NOT NULL,
            title       VARCHAR(255) NOT NULL,
            message     TEXT NOT NULL,
            type        VARCHAR(50) NOT NULL,
            is_read     BOOLEAN DEFAULT FALSE NOT NULL,
            is_sent     BOOLEAN DEFAULT FALSE NOT NULL,
            sent_at     TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql("INSERT INTO alert (user_id, title, description, type, category, is_read, created_at)
            SELECT user_id, title, COALESCE(description, ''), type, COALESCE(category, 'general'), is_read, created_at
            FROM base_notification WHERE discr = 'alert'");
        $this->addSql("INSERT INTO notification (user_id, title, message, type, is_read, is_sent, sent_at, created_at)
            SELECT user_id, title, COALESCE(message, ''), type, is_read, COALESCE(is_sent, false), sent_at, created_at
            FROM base_notification WHERE discr = 'notification'");
        $this->addSql('DROP TABLE base_notification');
    }
}
