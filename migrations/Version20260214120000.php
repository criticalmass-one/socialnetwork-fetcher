<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fetchSource to profile, rawSource and parsedSource to item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD fetch_source BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE item ADD raw_source TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD parsed_source TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP fetch_source');
        $this->addSql('ALTER TABLE item DROP raw_source');
        $this->addSql('ALTER TABLE item DROP parsed_source');
    }
}
