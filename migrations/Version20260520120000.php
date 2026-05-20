<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Promote RSS.app feed ID from additional_data JSON to its own column on profile';
    }

    public function up(Schema $schema): void
    {
        $profile = $schema->getTable('profile');
        $profile->addColumn('rss_app_feed_id', 'string', ['length' => 64, 'notnull' => false]);
        $profile->addIndex(['rss_app_feed_id'], 'IDX_PROFILE_RSS_APP_FEED_ID');
    }

    public function postUp(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, additional_data FROM profile WHERE additional_data IS NOT NULL'
        );

        foreach ($rows as $row) {
            $data = json_decode((string) $row['additional_data'], true);

            if (!is_array($data) || !isset($data['rss_feed_id'])) {
                continue;
            }

            $feedId = (string) $data['rss_feed_id'];
            unset($data['rss_feed_id']);

            $remaining = $data === []
                ? null
                : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $this->connection->update('profile', [
                'rss_app_feed_id' => $feedId,
                'additional_data' => $remaining,
            ], ['id' => $row['id']]);
        }
    }

    public function preDown(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, additional_data, rss_app_feed_id FROM profile WHERE rss_app_feed_id IS NOT NULL'
        );

        foreach ($rows as $row) {
            $data = $row['additional_data'] !== null
                ? (array) json_decode((string) $row['additional_data'], true)
                : [];

            $data['rss_feed_id'] = (string) $row['rss_app_feed_id'];

            $this->connection->update('profile', [
                'additional_data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ], ['id' => $row['id']]);
        }
    }

    public function down(Schema $schema): void
    {
        $profile = $schema->getTable('profile');
        $profile->dropIndex('IDX_PROFILE_RSS_APP_FEED_ID');
        $profile->dropColumn('rss_app_feed_id');
    }
}
