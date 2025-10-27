<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025185937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE city ALTER country DROP DEFAULT');
        $this->addSql('ALTER TABLE city ALTER zoom_level DROP DEFAULT');
        $this->addSql('ALTER TABLE city ALTER zoom_level SET NOT NULL');
        $this->addSql('ALTER TABLE city ALTER active DROP DEFAULT');
        $this->addSql('ALTER TABLE city ALTER active SET NOT NULL');
        $this->addSql('ALTER TABLE city ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE city ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE city ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE city ALTER updated_at DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN city.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN city.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX city_slug_key RENAME TO UNIQ_2D5B0234989D9B62');
        $this->addSql('ALTER INDEX idx_route_city RENAME TO IDX_2C420798BAC62AF');
        $this->addSql('ALTER TABLE route_performance_daily ADD schedule_realism_ratio NUMERIC(5, 3) DEFAULT NULL');
        $this->addSql('ALTER INDEX idx_stop_city RENAME TO IDX_B95616B68BAC62AF');
        $this->addSql('ALTER INDEX idx_trip_city RENAME TO IDX_7656F53B8BAC62AF');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE route_performance_daily DROP schedule_realism_ratio');
        $this->addSql('ALTER INDEX idx_b95616b68bac62af RENAME TO idx_stop_city');
        $this->addSql('ALTER INDEX idx_7656f53b8bac62af RENAME TO idx_trip_city');
        $this->addSql('ALTER TABLE city ALTER country SET DEFAULT \'CA\'');
        $this->addSql('ALTER TABLE city ALTER zoom_level SET DEFAULT 12');
        $this->addSql('ALTER TABLE city ALTER zoom_level DROP NOT NULL');
        $this->addSql('ALTER TABLE city ALTER active SET DEFAULT true');
        $this->addSql('ALTER TABLE city ALTER active DROP NOT NULL');
        $this->addSql('ALTER TABLE city ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE city ALTER created_at SET DEFAULT \'now()\'');
        $this->addSql('ALTER TABLE city ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE city ALTER updated_at SET DEFAULT \'now()\'');
        $this->addSql('COMMENT ON COLUMN city.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN city.updated_at IS NULL');
        $this->addSql('ALTER INDEX uniq_2d5b0234989d9b62 RENAME TO city_slug_key');
        $this->addSql('ALTER INDEX idx_2c420798bac62af RENAME TO idx_route_city');
    }
}
