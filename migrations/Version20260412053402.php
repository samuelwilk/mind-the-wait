<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260412053402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shape table, trip.shape_id, stop_time.shape_dist_traveled, arrival_log.actual_arrival_at for route geometry and arrival detection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shape (id SERIAL NOT NULL, shape_id VARCHAR(64) NOT NULL, lat DOUBLE PRECISION NOT NULL, lon DOUBLE PRECISION NOT NULL, sequence INT NOT NULL, dist_traveled DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_shape_id_seq ON shape (shape_id, sequence)');
        $this->addSql('ALTER TABLE arrival_log ADD actual_arrival_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN arrival_log.actual_arrival_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE stop_time ADD shape_dist_traveled DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE trip ADD shape_id VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shape');
        $this->addSql('ALTER TABLE trip DROP shape_id');
        $this->addSql('ALTER TABLE stop_time DROP shape_dist_traveled');
        $this->addSql('ALTER TABLE arrival_log DROP actual_arrival_at');
    }
}
