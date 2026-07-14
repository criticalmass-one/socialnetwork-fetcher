<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add push_subscription table for browser Web Push subscriptions per group';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('push_subscription');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('group_id', 'integer', ['notnull' => true]);
        $table->addColumn('endpoint', 'string', ['length' => 500, 'notnull' => true]);
        $table->addColumn('p256dh', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('auth', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['group_id'], 'idx_push_subscription_group');
        $table->addForeignKeyConstraint(
            'profile_group',
            ['group_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_push_subscription_group',
        );
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('push_subscription');
    }
}
