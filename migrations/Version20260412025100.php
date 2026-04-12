<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add composite index on arrival_log for analytics queries.
 *
 * The analytics dashboard runs heavy aggregations (vehicle performance,
 * hourly trends) filtering on predicted_at + delay_sec IS NOT NULL and
 * grouping by vehicle_id. This covering index avoids full table scans
 * on the 14.8M-row arrival_log table.
 */
final class Version20260412025100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite index on arrival_log(predicted_at, delay_sec, vehicle_id, route_id) for analytics queries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX CONCURRENTLY idx_arrival_log_analytics ON arrival_log (predicted_at, delay_sec, vehicle_id, route_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_arrival_log_analytics');
    }

    public function isTransactional(): bool
    {
        // CONCURRENTLY cannot run inside a transaction
        return false;
    }
}
