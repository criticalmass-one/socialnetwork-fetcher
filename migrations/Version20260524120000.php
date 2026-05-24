<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260524120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed TikTok network row (fetched via RSS.app)';
    }

    public function up(Schema $schema): void
    {
        $this->connection->insert('network', [
            'identifier' => 'tiktok',
            'name' => 'TikTok',
            'icon' => 'fab fa-tiktok',
            'background_color' => '#000000',
            'text_color' => 'white',
            'profile_url_pattern' => '#^https?://(www\.)?tiktok\.com/@[\w.\-]+/?$#i',
            'cron_expression' => '35 * * * *',
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->connection->delete('network', ['identifier' => 'tiktok']);
    }
}
