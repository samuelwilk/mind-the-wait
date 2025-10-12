<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251012174212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX stop_time_trip_seq_unique ON stop_time (trip_id, stop_sequence)');
        $this->addSql('ALTER INDEX idx_85725a5aa5bc2e0e RENAME TO idx_stop_time_trip');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX stop_time_trip_seq_unique');
        $this->addSql('ALTER INDEX idx_stop_time_trip RENAME TO idx_85725a5aa5bc2e0e');
    }
}
