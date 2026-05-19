<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519153030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile_group table and profile_group_profile join table for client-scoped groups of profiles';
    }

    public function up(Schema $schema): void
    {
        $group = $schema->createTable('profile_group');
        $group->addColumn('id', 'integer', ['autoincrement' => true]);
        $group->addColumn('client_id', 'integer');
        $group->addColumn('name', 'string', ['length' => 64]);
        $group->addColumn('description', 'text', ['notnull' => false]);
        $group->addColumn('color', 'string', ['length' => 7, 'notnull' => false]);
        $group->addColumn('created_at', 'datetime_immutable');
        $group->setPrimaryKey(['id']);
        $group->addIndex(['client_id'], 'IDX_9A14DB1719EB6921');
        $group->addUniqueIndex(['client_id', 'name'], 'uniq_group_client_name');
        $group->addForeignKeyConstraint('client', ['client_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_9A14DB1719EB6921');

        $groupProfile = $schema->createTable('profile_group_profile');
        $groupProfile->addColumn('group_id', 'integer');
        $groupProfile->addColumn('profile_id', 'integer');
        $groupProfile->setPrimaryKey(['group_id', 'profile_id']);
        $groupProfile->addIndex(['group_id'], 'IDX_29545BC3FE54D947');
        $groupProfile->addIndex(['profile_id'], 'IDX_29545BC3CCFA12B8');
        $groupProfile->addForeignKeyConstraint('profile_group', ['group_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_29545BC3FE54D947');
        $groupProfile->addForeignKeyConstraint('profile', ['profile_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_29545BC3CCFA12B8');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('profile_group_profile');
        $schema->dropTable('profile_group');
    }
}
