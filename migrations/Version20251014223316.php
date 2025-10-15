<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014223316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bunching_incident (id BIGSERIAL NOT NULL, route_id INT NOT NULL, stop_id INT NOT NULL, weather_observation_id INT DEFAULT NULL, detected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, vehicle_count INT NOT NULL, time_window_seconds INT NOT NULL, vehicle_ids TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B1A0A25634ECB4E6 ON bunching_incident (route_id)');
        $this->addSql('CREATE INDEX IDX_B1A0A2563902063D ON bunching_incident (stop_id)');
        $this->addSql('CREATE INDEX IDX_B1A0A25623AD3422 ON bunching_incident (weather_observation_id)');
        $this->addSql('CREATE INDEX idx_bunching_route_detected ON bunching_incident (route_id, detected_at)');
        $this->addSql('CREATE INDEX idx_bunching_stop_detected ON bunching_incident (stop_id, detected_at)');
        $this->addSql('CREATE INDEX idx_bunching_detected_at ON bunching_incident (detected_at)');
        $this->addSql('COMMENT ON COLUMN bunching_incident.detected_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN bunching_incident.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE bunching_incident ADD CONSTRAINT FK_B1A0A25634ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bunching_incident ADD CONSTRAINT FK_B1A0A2563902063D FOREIGN KEY (stop_id) REFERENCES stop (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bunching_incident ADD CONSTRAINT FK_B1A0A25623AD3422 FOREIGN KEY (weather_observation_id) REFERENCES weather_observation (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE bunching_incident DROP CONSTRAINT FK_B1A0A25634ECB4E6');
        $this->addSql('ALTER TABLE bunching_incident DROP CONSTRAINT FK_B1A0A2563902063D');
        $this->addSql('ALTER TABLE bunching_incident DROP CONSTRAINT FK_B1A0A25623AD3422');
        $this->addSql('DROP TABLE bunching_incident');
    }
}
