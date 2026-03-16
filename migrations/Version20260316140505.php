<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316140505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('COMMENT ON COLUMN client.created_at IS \'\'');
        $this->addSql('ALTER TABLE profile ADD title VARCHAR(255) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN profile.deleted_at IS \'\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('COMMENT ON COLUMN client.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE profile DROP title');
        $this->addSql('COMMENT ON COLUMN profile.deleted_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
