<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unused auto_publish column from profile';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP auto_publish');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD auto_publish BOOLEAN DEFAULT true NOT NULL');
    }
}
