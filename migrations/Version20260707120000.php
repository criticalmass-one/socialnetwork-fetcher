<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add transcribeVideos flag to profile and transcript/transcriptStatus/transcriptError columns to item';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('profile')
            ->addColumn('transcribe_videos', 'boolean', ['default' => false]);

        $item = $schema->getTable('item');
        $item->addColumn('transcript', 'text', ['notnull' => false]);
        $item->addColumn('transcript_status', 'string', ['length' => 20, 'notnull' => false]);
        $item->addColumn('transcript_error', 'text', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('profile')->dropColumn('transcribe_videos');

        $item = $schema->getTable('item');
        $item->dropColumn('transcript');
        $item->dropColumn('transcript_status');
        $item->dropColumn('transcript_error');
    }
}
