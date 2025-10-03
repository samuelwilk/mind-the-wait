<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002163535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraint on stop_time(trip_id, stop_sequence) for upserts';
    }

    public function up(Schema $schema): void
    {
        // If you expect large tables in prod, use CONCURRENTLY + isTransactional(): false.
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS stop_time_trip_seq_unique ON stop_time (trip_id, stop_sequence)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS stop_time_trip_seq_unique');
    }
}
