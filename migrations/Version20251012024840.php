<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251012024840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE route_performance_daily (id SERIAL NOT NULL, route_id INT NOT NULL, date DATE NOT NULL, total_predictions INT NOT NULL, high_confidence_count INT NOT NULL, medium_confidence_count INT NOT NULL, low_confidence_count INT NOT NULL, avg_delay_sec INT DEFAULT NULL, median_delay_sec INT DEFAULT NULL, on_time_percentage NUMERIC(5, 2) DEFAULT NULL, late_percentage NUMERIC(5, 2) DEFAULT NULL, early_percentage NUMERIC(5, 2) DEFAULT NULL, bunching_incidents INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B64F25234ECB4E6 ON route_performance_daily (route_id)');
        $this->addSql('CREATE INDEX idx_route_performance_date ON route_performance_daily (date)');
        $this->addSql('CREATE UNIQUE INDEX route_date_unique ON route_performance_daily (route_id, date)');
        $this->addSql('COMMENT ON COLUMN route_performance_daily.date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN route_performance_daily.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN route_performance_daily.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE route_performance_daily ADD CONSTRAINT FK_B64F25234ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE route_performance_daily DROP CONSTRAINT FK_B64F25234ECB4E6');
        $this->addSql('DROP TABLE route_performance_daily');
    }
}
