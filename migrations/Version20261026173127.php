<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026173127 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE appel_llm (id UUID NOT NULL, modele VARCHAR(60) NOT NULL, version_prompt VARCHAR(20) NOT NULL, tokens_entree INT NOT NULL, tokens_sortie INT NOT NULL, tokens_cache_ecriture INT NOT NULL, tokens_cache_lecture INT NOT NULL, cout_nano_dollars BIGINT NOT NULL, latence_ms INT NOT NULL, succes BOOLEAN NOT NULL, erreur TEXT DEFAULT NULL, appele_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_appel_llm_date ON appel_llm (appele_le)');
        $this->addSql('CREATE INDEX idx_appel_llm_version ON appel_llm (version_prompt)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE appel_llm');
    }
}
