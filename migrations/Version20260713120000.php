<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Allow dots in Instagram usernames in the instagram_profile URL validation
 * pattern. Instagram handles may contain periods (e.g. "torsten.franz.lg");
 * the previous username character class [A-Za-z0-9\-_] rejected them, which
 * blocked POST /profiles and change-identifier for such handles even though
 * dotted profiles already existed in the DB via import.
 */
final class Version20260713120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow dots in Instagram usernames (instagram_profile profileUrlPattern)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE network
            SET profile_url_pattern = '#^https?://(www\.)?instagram\.[A-Za-z]{2,3}/[A-Za-z0-9._\-]{5,}/?$#i'
            WHERE identifier = 'instagram_profile'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE network
            SET profile_url_pattern = '#^https?://(www\.)?instagram\.[A-Za-z]{2,3}/[A-Za-z0-9\-_]{5,}/?$#i'
            WHERE identifier = 'instagram_profile'
        SQL);
    }
}
