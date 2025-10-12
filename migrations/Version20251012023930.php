<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251012023930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add arrival_log table for historical arrival prediction tracking';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE arrival_log (id BIGSERIAL NOT NULL, route_id INT NOT NULL, stop_id INT NOT NULL, vehicle_id VARCHAR(50) NOT NULL, trip_id VARCHAR(100) NOT NULL, predicted_arrival_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, scheduled_arrival_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, delay_sec INT DEFAULT NULL, confidence VARCHAR(10) NOT NULL, stops_away INT DEFAULT NULL, predicted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EA52F0F334ECB4E6 ON arrival_log (route_id)');
        $this->addSql('CREATE INDEX IDX_EA52F0F33902063D ON arrival_log (stop_id)');
        $this->addSql('CREATE INDEX idx_arrival_log_route_predicted_at ON arrival_log (route_id, predicted_at)');
        $this->addSql('CREATE INDEX idx_arrival_log_stop_predicted_at ON arrival_log (stop_id, predicted_at)');
        $this->addSql('CREATE INDEX idx_arrival_log_trip_predicted_at ON arrival_log (trip_id, predicted_at)');
        $this->addSql('CREATE INDEX idx_arrival_log_predicted_at ON arrival_log (predicted_at)');
        $this->addSql('COMMENT ON COLUMN arrival_log.predicted_arrival_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN arrival_log.scheduled_arrival_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN arrival_log.predicted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN arrival_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE arrival_log ADD CONSTRAINT FK_EA52F0F334ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE arrival_log ADD CONSTRAINT FK_EA52F0F33902063D FOREIGN KEY (stop_id) REFERENCES stop (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX stop_time_trip_seq_unique');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE arrival_log DROP CONSTRAINT FK_EA52F0F334ECB4E6');
        $this->addSql('ALTER TABLE arrival_log DROP CONSTRAINT FK_EA52F0F33902063D');
        $this->addSql('DROP TABLE arrival_log');
        $this->addSql('CREATE UNIQUE INDEX stop_time_trip_seq_unique ON stop_time (trip_id, stop_sequence)');
    }
}
