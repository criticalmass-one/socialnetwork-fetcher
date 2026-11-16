<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile.fetch_provider (switch a profile from RSS.app to ScrapeCreators) and an index on item.permalink';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('profile')->addColumn('fetch_provider', 'string', ['length' => 32, 'notnull' => false]);

        // permalink is a TEXT column: MySQL/MariaDB need a prefix length for the
        // index, PostgreSQL and SQLite ignore the option. Lookups by permalink
        // (POST /media-upload) were full table scans of about 11 s each.
        $schema->getTable('item')->addIndex(['permalink'], 'item_permalink_index', [], ['lengths' => [191]]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('item')->dropIndex('item_permalink_index');
        $schema->getTable('profile')->dropColumn('fetch_provider');
    }
}
