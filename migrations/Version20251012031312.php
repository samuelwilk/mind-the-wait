<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251012031312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE weather_observation (id SERIAL NOT NULL, observed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, temperature_celsius NUMERIC(4, 1) NOT NULL, feels_like_celsius NUMERIC(4, 1) DEFAULT NULL, precipitation_mm NUMERIC(5, 1) DEFAULT NULL, snowfall_cm NUMERIC(4, 1) DEFAULT NULL, snow_depth_cm INT DEFAULT NULL, weather_code INT DEFAULT NULL, weather_condition VARCHAR(50) NOT NULL, visibility_km NUMERIC(5, 2) DEFAULT NULL, wind_speed_kmh NUMERIC(5, 1) DEFAULT NULL, transit_impact VARCHAR(20) NOT NULL, data_source VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_weather_impact_observed_at ON weather_observation (transit_impact, observed_at)');
        $this->addSql('CREATE UNIQUE INDEX observed_at_unique ON weather_observation (observed_at)');
        $this->addSql('COMMENT ON COLUMN weather_observation.observed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN weather_observation.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN weather_observation.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE route_performance_daily ADD weather_observation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE route_performance_daily ADD CONSTRAINT FK_B64F25223AD3422 FOREIGN KEY (weather_observation_id) REFERENCES weather_observation (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_B64F25223AD3422 ON route_performance_daily (weather_observation_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE route_performance_daily DROP CONSTRAINT FK_B64F25223AD3422');
        $this->addSql('DROP TABLE weather_observation');
        $this->addSql('DROP INDEX IDX_B64F25223AD3422');
        $this->addSql('ALTER TABLE route_performance_daily DROP weather_observation_id');
    }
}
