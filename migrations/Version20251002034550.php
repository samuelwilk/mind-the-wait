<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002034550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE route (id SERIAL NOT NULL, gtfs_id VARCHAR(64) NOT NULL, short_name VARCHAR(64) DEFAULT NULL, long_name VARCHAR(255) DEFAULT NULL, colour VARCHAR(6) DEFAULT NULL, route_type INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE stop (id SERIAL NOT NULL, gtfs_id VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, lat DOUBLE PRECISION NOT NULL, long DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE stop_time (id SERIAL NOT NULL, trip_id INT NOT NULL, stop_id INT NOT NULL, stop_sequence INT NOT NULL, arrival_time INT DEFAULT NULL, departure_time INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_85725A5AA5BC2E0E ON stop_time (trip_id)');
        $this->addSql('CREATE INDEX IDX_85725A5A3902063D ON stop_time (stop_id)');
        $this->addSql('CREATE TABLE trip (id SERIAL NOT NULL, route_id INT NOT NULL, gtfs_id VARCHAR(64) NOT NULL, service_id VARCHAR(64) DEFAULT NULL, direction INT NOT NULL, headsign VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7656F53B34ECB4E6 ON trip (route_id)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE stop_time ADD CONSTRAINT FK_85725A5AA5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stop_time ADD CONSTRAINT FK_85725A5A3902063D FOREIGN KEY (stop_id) REFERENCES stop (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE trip ADD CONSTRAINT FK_7656F53B34ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE stop_time DROP CONSTRAINT FK_85725A5AA5BC2E0E');
        $this->addSql('ALTER TABLE stop_time DROP CONSTRAINT FK_85725A5A3902063D');
        $this->addSql('ALTER TABLE trip DROP CONSTRAINT FK_7656F53B34ECB4E6');
        $this->addSql('DROP TABLE route');
        $this->addSql('DROP TABLE stop');
        $this->addSql('DROP TABLE stop_time');
        $this->addSql('DROP TABLE trip');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
