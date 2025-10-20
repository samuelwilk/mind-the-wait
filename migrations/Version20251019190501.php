<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add multi-city support for iOS app expansion.
 */
final class Version20251019190501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multi-city support: City table, foreign keys on route/stop/trip';
    }

    public function up(Schema $schema): void
    {
        // Create city table
        $this->addSql("
            CREATE TABLE city (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(50) UNIQUE NOT NULL,
                country VARCHAR(2) NOT NULL DEFAULT 'CA',
                gtfs_static_url VARCHAR(500),
                gtfs_rt_vehicle_url VARCHAR(500),
                gtfs_rt_trip_url VARCHAR(500),
                gtfs_rt_alert_url VARCHAR(500),
                center_lat NUMERIC(10, 8) NOT NULL,
                center_lon NUMERIC(11, 8) NOT NULL,
                zoom_level SMALLINT DEFAULT 12,
                active BOOLEAN DEFAULT true,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP NOT NULL DEFAULT NOW()
            )
        ");

        // Seed Saskatoon as first city
        $this->addSql("
            INSERT INTO city (name, slug, country, center_lat, center_lon, zoom_level, active)
            VALUES ('Saskatoon', 'saskatoon', 'CA', 52.1324, -106.6689, 12, true)
        ");

        // Add city_id columns to existing tables
        $this->addSql('ALTER TABLE route ADD COLUMN city_id INT');
        $this->addSql('ALTER TABLE stop ADD COLUMN city_id INT');
        $this->addSql('ALTER TABLE trip ADD COLUMN city_id INT');

        // Set all existing data to Saskatoon (city_id = 1)
        $this->addSql('UPDATE route SET city_id = 1');
        $this->addSql('UPDATE stop SET city_id = 1');
        $this->addSql('UPDATE trip SET city_id = 1');

        // Make city_id NOT NULL after backfill
        $this->addSql('ALTER TABLE route ALTER COLUMN city_id SET NOT NULL');
        $this->addSql('ALTER TABLE stop ALTER COLUMN city_id SET NOT NULL');
        $this->addSql('ALTER TABLE trip ALTER COLUMN city_id SET NOT NULL');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE route ADD CONSTRAINT fk_route_city FOREIGN KEY (city_id) REFERENCES city(id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE stop ADD CONSTRAINT fk_stop_city FOREIGN KEY (city_id) REFERENCES city(id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE trip ADD CONSTRAINT fk_trip_city FOREIGN KEY (city_id) REFERENCES city(id) ON DELETE RESTRICT');

        // Create indexes for performance
        $this->addSql('CREATE INDEX idx_route_city ON route (city_id)');
        $this->addSql('CREATE INDEX idx_stop_city ON stop (city_id)');
        $this->addSql('CREATE INDEX idx_trip_city ON trip (city_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop indexes
        $this->addSql('DROP INDEX IF EXISTS idx_route_city');
        $this->addSql('DROP INDEX IF EXISTS idx_stop_city');
        $this->addSql('DROP INDEX IF EXISTS idx_trip_city');

        // Drop foreign key constraints
        $this->addSql('ALTER TABLE route DROP CONSTRAINT IF EXISTS fk_route_city');
        $this->addSql('ALTER TABLE stop DROP CONSTRAINT IF EXISTS fk_stop_city');
        $this->addSql('ALTER TABLE trip DROP CONSTRAINT IF EXISTS fk_trip_city');

        // Drop city_id columns
        $this->addSql('ALTER TABLE route DROP COLUMN city_id');
        $this->addSql('ALTER TABLE stop DROP COLUMN city_id');
        $this->addSql('ALTER TABLE trip DROP COLUMN city_id');

        // Drop city table
        $this->addSql('DROP TABLE city');
    }
}
