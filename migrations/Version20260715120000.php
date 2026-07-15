<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add public_page_event table for public group page view/click statistics';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('public_page_event');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('group_id', 'integer', ['notnull' => true]);
        $table->addColumn('type', 'string', ['length' => 16, 'notnull' => true]);
        $table->addColumn('url', 'string', ['length' => 500, 'notnull' => false]);
        $table->addColumn('occurred_at', 'datetime_immutable', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['group_id', 'type', 'occurred_at'], 'idx_ppe_group_type_time');
        $table->addForeignKeyConstraint(
            'profile_group',
            ['group_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_ppe_group',
        );
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('public_page_event');
    }
}
