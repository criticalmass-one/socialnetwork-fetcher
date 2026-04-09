<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316152552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item ADD photo_paths JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD video_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD media_status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD media_error TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD save_photos BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE profile ADD save_videos BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item DROP photo_paths');
        $this->addSql('ALTER TABLE item DROP video_path');
        $this->addSql('ALTER TABLE item DROP media_status');
        $this->addSql('ALTER TABLE item DROP media_error');
        $this->addSql('ALTER TABLE profile DROP save_photos');
        $this->addSql('ALTER TABLE profile DROP save_videos');
    }
}
