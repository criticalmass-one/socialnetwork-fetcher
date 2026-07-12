<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add public-page configuration columns to profile_group (public feed page per group)';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('profile_group');

        $table->addColumn('public_page_enabled', 'boolean', ['default' => false]);
        $table->addColumn('public_slug', 'string', ['length' => 32, 'notnull' => false]);
        $table->addColumn('public_password_hash', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('public_title', 'string', ['length' => 120, 'notnull' => false]);
        $table->addColumn('public_description', 'text', ['notnull' => false]);
        $table->addColumn('show_photos', 'boolean', ['default' => true]);
        $table->addColumn('show_videos', 'boolean', ['default' => true]);
        $table->addColumn('show_transcript', 'boolean', ['default' => false]);
        $table->addColumn('show_captions', 'boolean', ['default' => true]);
        $table->addColumn('time_window_days', 'integer', ['notnull' => false, 'default' => 30]);

        $table->addUniqueIndex(['public_slug'], 'uniq_group_public_slug');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('profile_group');

        $table->dropIndex('uniq_group_public_slug');

        $table->dropColumn('public_page_enabled');
        $table->dropColumn('public_slug');
        $table->dropColumn('public_password_hash');
        $table->dropColumn('public_title');
        $table->dropColumn('public_description');
        $table->dropColumn('show_photos');
        $table->dropColumn('show_videos');
        $table->dropColumn('show_transcript');
        $table->dropColumn('show_captions');
        $table->dropColumn('time_window_days');
    }
}
