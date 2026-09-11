<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds `tickets` to doc_entry (T-0007): the list of ticket/rules IDs an entry documents.
 *
 * The column default keeps existing rows valid without a separate data migration.
 */
final class Version20260908120321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add doc_entry.tickets (jsonb, not null, default [])';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE doc_entry ADD tickets JSONB NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE doc_entry DROP tickets');
    }
}
