<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316140505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add title column to profile';
    }

    public function up(Schema $schema): void
    {
        $profile = $schema->getTable('profile');
        $profile->addColumn('title', 'string', ['length' => 255, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $profile = $schema->getTable('profile');
        $profile->dropColumn('title');
    }
}
