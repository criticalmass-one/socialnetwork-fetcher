<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315083634 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Client entity, client_profile join table, and soft-delete fields on Profile';
    }

    public function up(Schema $schema): void
    {
        $client = $schema->createTable('client');
        $client->addColumn('id', 'integer', ['autoincrement' => true]);
        $client->addColumn('name', 'string', ['length' => 255]);
        $client->addColumn('token', 'string', ['length' => 64]);
        $client->addColumn('enabled', 'boolean', ['default' => true]);
        $client->addColumn('created_at', 'datetime_immutable');
        $client->setPrimaryKey(['id']);
        $client->addUniqueIndex(['name'], 'UNIQ_C74404555E237E06');
        $client->addUniqueIndex(['token'], 'UNIQ_C74404555F37A13B');

        $clientProfile = $schema->createTable('client_profile');
        $clientProfile->addColumn('client_id', 'integer');
        $clientProfile->addColumn('profile_id', 'integer');
        $clientProfile->setPrimaryKey(['client_id', 'profile_id']);
        $clientProfile->addIndex(['client_id'], 'IDX_D36AEE7219EB6921');
        $clientProfile->addIndex(['profile_id'], 'IDX_D36AEE72CCFA12B8');
        $clientProfile->addForeignKeyConstraint('client', ['client_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_D36AEE7219EB6921');
        $clientProfile->addForeignKeyConstraint('profile', ['profile_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_D36AEE72CCFA12B8');

        $profile = $schema->getTable('profile');
        $profile->addColumn('deleted', 'boolean', ['default' => false]);
        $profile->addColumn('deleted_at', 'datetime_immutable', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('client_profile');
        $schema->dropTable('client');

        $profile = $schema->getTable('profile');
        $profile->dropColumn('deleted_at');
        $profile->dropColumn('deleted');
    }
}
