<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fetchSource to profile, rawSource and source to item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD fetch_source BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE item ADD raw_source TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD source TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP fetch_source');
        $this->addSql('ALTER TABLE item DROP raw_source');
        $this->addSql('ALTER TABLE item DROP source');
    }
}
