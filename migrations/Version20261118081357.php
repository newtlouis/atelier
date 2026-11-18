<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Une demande garde l'envoi de formulaire dont elle vient.
 *
 * L'index unique sur id_netlify est la garantie d'idempotence de l'import :
 * relancer l'import ne peut pas créer deux demandes du même envoi, même si
 * le code qui vérifie avant d'insérer se trompe. PostgreSQL admet plusieurs
 * NULL dans un index unique : les demandes saisies à la main n'en ont pas.
 *
 * La valeur par défaut de champs_formulaire couvre les demandes déjà en base.
 */
final class Version20261118081357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Garder l'envoi de formulaire d'une demande";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande ADD id_netlify VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE demande ADD formulaire VARCHAR(40) DEFAULT NULL');
        $this->addSql("ALTER TABLE demande ADD champs_formulaire JSON DEFAULT '[]' NOT NULL");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2694D7A5E09B24FD ON demande (id_netlify)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_2694D7A5E09B24FD');
        $this->addSql('ALTER TABLE demande DROP champs_formulaire');
        $this->addSql('ALTER TABLE demande DROP formulaire');
        $this->addSql('ALTER TABLE demande DROP id_netlify');
    }
}
