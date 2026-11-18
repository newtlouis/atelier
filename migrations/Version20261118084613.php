<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La lecture de chaque demande : ce que la chaîne a compris et la suite à donner.
 *
 * L'index unique sur demande_id fait de « une lecture par demande » une
 * garantie de la base ; l'index sur issue sert la liste des demandes en
 * attente de distance. Migration accordée par Louis le 18/11/2026.
 */
final class Version20261118084613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enregistrer la lecture de chaque demande';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lecture_de_demande (id UUID NOT NULL, issue VARCHAR(30) NOT NULL, nature VARCHAR(30) NOT NULL, famille VARCHAR(10) DEFAULT NULL, longueur_mm INT DEFAULT NULL, largeur_mm INT DEFAULT NULL, hauteur_mm INT DEFAULT NULL, hauteur_standard BOOLEAN NOT NULL, surface_metres_carres INT DEFAULT NULL, distance_km INT DEFAULT NULL, support VARCHAR(20) DEFAULT NULL, ville_livraison VARCHAR(120) DEFAULT NULL, champs_manquants JSON NOT NULL, lue_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, demande_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_911EEC6280E95E18 ON lecture_de_demande (demande_id)');
        $this->addSql('CREATE INDEX idx_lecture_issue ON lecture_de_demande (issue)');
        $this->addSql('ALTER TABLE lecture_de_demande ADD CONSTRAINT FK_911EEC6280E95E18 FOREIGN KEY (demande_id) REFERENCES demande (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lecture_de_demande');
    }
}
